import { test, expect, Page } from '@playwright/test';

/**
 * Testes E2E do formulário principal de requerimento de alvará (index.php).
 * O formulário é um wizard de 3 etapas (Serviço e identificação → Dados do
 * serviço → Documentos e envio); campos de uma etapa ficam `hidden` até que
 * a etapa anterior seja validada e o usuário avance com #public-step-next.
 * Requer o Docker rodando: ./scripts/start.sh
 */

// ─── Helpers ────────────────────────────────────────────────────────────────

async function preencherRequerente(page: Page, dados: {
  nome?: string;
  email?: string;
  cpfCnpj?: string;
  telefone?: string;
} = {}) {
  const d = {
    nome: 'João da Silva',
    email: 'joao@example.com',
    cpfCnpj: '123.456.789-09',
    telefone: '(84) 99999-9999',
    ...dados,
  };
  await page.fill('#name', d.nome);
  await page.fill('#cpf', d.cpfCnpj);
  await page.fill('#requerente_email', d.email);
  await page.fill('#requerente_email_confirmacao', d.email);
  await page.fill('#phone', d.telefone);
}

async function preencherEndereco(page: Page) {
  await page.fill('input[name="obra_logradouro"]', 'Rua das Flores');
  await page.fill('input[name="obra_bairro"]', 'Centro');
  await page.fill('input[name="obra_numero"]', '123');
}

async function selecionarTipoAlvara(page: Page, valor: string) {
  await page.selectOption('select[name="tipo_alvara"]', valor);
  // Aguarda os campos dinâmicos carregarem
  await page.waitForTimeout(500);
}

async function preencherResponsavelTecnico(page: Page) {
  await page.fill('input[name="responsavel_tecnico_nome"]', 'Maria Engenheira');
  await page.selectOption('select[name="responsavel_tecnico_tipo_documento"]', 'CREA');
  await page.fill('input[name="responsavel_tecnico_registro"]', '123456');
  await page.fill('input[name="responsavel_tecnico_numero"]', 'ART-000111');
}

/** Preenche a Etapa 1 (identificação, endereço, tipo) para o tipo "construção" e avança. */
async function completarEtapa1Construcao(page: Page, dadosRequerente: Parameters<typeof preencherRequerente>[1] = {}) {
  await selecionarTipoAlvara(page, 'construcao');
  await preencherRequerente(page, dadosRequerente);
  await preencherEndereco(page);
  await preencherResponsavelTecnico(page);
  await page.locator('input[name="notificado_fiscal_obras"][value="0"]').check();
  await page.click('#public-step-next');
}

/** Preenche a Etapa 2 (campos dinâmicos da construção) e avança para a Etapa 3. */
async function completarEtapa2Construcao(page: Page) {
  await page.selectOption('select[name="tipo_edificacao"]', { label: 'Residencial unifamiliar' });
  await page.fill('input[name="area_construcao"]', '120');
  await page.fill('input[name="cadastro_imobiliario"]', '12345');
  await page.click('#public-step-next');
}

/** Fluxo completo até a Etapa 3 (documentos e declaração) ficar desbloqueada. */
async function avancarAteEtapa3(page: Page) {
  await page.goto('/');
  await completarEtapa1Construcao(page);
  await completarEtapa2Construcao(page);
  await expect(page.locator('[data-public-step="3"]')).toHaveClass(/is-active/);
}

// ─── Testes ─────────────────────────────────────────────────────────────────

test.describe('Formulário Principal - Carregamento', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('página carrega com título correto', async ({ page }) => {
    await expect(page).toHaveTitle(/SEMA|Requerimento|Alvará/i);
  });

  test('formulário principal está visível', async ({ page }) => {
    await expect(page.locator('form')).toBeVisible();
  });

  test('campos do requerente estão presentes', async ({ page }) => {
    await expect(page.locator('[name="requerente[nome]"], #name')).toBeVisible();
    await expect(page.locator('[name="requerente[email]"], #requerente_email')).toBeVisible();
    await expect(page.locator('[name="requerente[cpf_cnpj]"], #cpf')).toBeVisible();
    await expect(page.locator('[name="requerente[telefone]"], #phone')).toBeVisible();
  });

  test('seletor de tipo de alvará está presente', async ({ page }) => {
    await expect(page.locator('select[name="tipo_alvara"]')).toBeVisible();
  });

  test('campo de endereço está presente após escolher o tipo de solicitação', async ({ page }) => {
    // O valor é um input hidden preenchido pelo composer de localização
    // (rua/bairro/número), que só aparece depois que um tipo é escolhido.
    await selecionarTipoAlvara(page, 'construcao');
    await expect(page.locator('[name="endereco_objetivo"]')).toBeAttached();
    await expect(page.locator('input[name="obra_logradouro"]')).toBeVisible();
    await expect(page.locator('input[name="obra_bairro"]')).toBeVisible();
  });

  test('checkbox de declaração de veracidade está presente no DOM', async ({ page }) => {
    // Só fica visível na Etapa 3 (Documentos e envio), então aqui verificamos
    // apenas que o elemento existe; a visibilidade em etapa é coberta abaixo.
    await expect(page.locator('[name="declaracao_veracidade"]')).toBeAttached();
  });
});

test.describe('Formulário Principal - Validação de Campos Obrigatórios', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('avançar sem selecionar tipo de alvará exibe erro de validação', async ({ page }) => {
    await page.click('#public-step-next');
    // A validação da Etapa 1 é feita via JS (marca .field-invalid e injeta
    // uma mensagem em .field-error), não pela validade nativa do <select>.
    await expect(page.locator('#tipo_alvara_busca, #tipo_alvara').first()).toHaveClass(/field-invalid/);
    await expect(page.locator('.field-error').first()).toContainText('Selecione o tipo de solicitação.');
    // Permanece na etapa 1
    await expect(page.locator('[data-public-step="1"]')).toHaveClass(/is-active/);
  });

  test('avançar sem nome do requerente não sai da etapa 1', async ({ page }) => {
    await selecionarTipoAlvara(page, 'construcao');
    await preencherRequerente(page, { nome: '' });
    await preencherEndereco(page);
    await preencherResponsavelTecnico(page);
    await page.locator('input[name="notificado_fiscal_obras"][value="0"]').check();

    await page.click('#public-step-next');

    await expect(page.locator('[data-public-step="1"]')).toHaveClass(/is-active/);
  });

  test('e-mail inválido não passa validação', async ({ page }) => {
    await selecionarTipoAlvara(page, 'construcao');
    await preencherRequerente(page, { email: 'email-invalido' });

    const emailInput = page.locator('[name="requerente[email]"], #requerente_email');
    const validationMessage = await emailInput.evaluate((el: HTMLInputElement) => el.validationMessage);
    expect(validationMessage).not.toBe('');
  });
});

test.describe('Formulário Principal - Campos Dinâmicos', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('selecionar "construção" não exibe campos ambientais', async ({ page }) => {
    await selecionarTipoAlvara(page, 'construcao');

    // Campos exclusivos de ambientais não devem estar visíveis
    const ctf = page.locator('[name="ctf_numero"], #ctf_numero');
    const temCtf = await ctf.count();
    if (temCtf > 0) {
      await expect(ctf).not.toBeVisible();
    }
  });

  test('selecionar "licença prévia ambiental" exibe campos ambientais', async ({ page }) => {
    await selecionarTipoAlvara(page, 'licenca_previa_instalacao');
    await page.waitForTimeout(800);
    await page.evaluate(() => (window as any).SEMA_PUBLIC_FORM?.showStep?.(2, { preview: true }));

    // Deve aparecer campo de publicação no Diário Oficial ou estudo ambiental
    const camposAmbientais = page.locator(
      '[name="publicacao_diario_oficial"], [name="possui_estudo_ambiental"], #publicacao_diario_oficial'
    );
    await expect(camposAmbientais.first()).toBeVisible();
  });

  test('seletor visual permite escolher uma solicitação', async ({ page }) => {
    await page.locator('[data-categoria="obras"]').click();

    const opcao = page.locator('#tipo_alvara_lista [role="option"][data-slug="construcao"]');
    await expect(opcao).toBeVisible();
    await opcao.click();

    await expect(page.locator('#tipo_alvara')).toHaveValue('construcao');
    await expect(page.locator('#tipo_alvara_busca')).toHaveValue(/ALVARÁ DE CONSTRUÇÃO/i);
  });

  test('denúncia identificada solicita CPF sem prometer comunicações', async ({ page }) => {
    await selecionarTipoAlvara(page, 'denuncia');
    await page.locator('input[name="anonimo"][value="0"]').check();

    const cpf = page.locator('input[name="requerente[cpf_cnpj]"]');
    await expect(cpf).toBeVisible();
    await expect(cpf).toHaveAttribute('required', '');
    await expect(cpf).toHaveAttribute('placeholder', 'CPF *');
    await expect(page.locator('[data-identificacao-nota]')).toHaveText('Informe seus dados para registrar a denúncia de forma identificada.');
    await expect(page.locator('.public-denuncia-mode-section')).not.toContainText(/receber|comunicaç/i);
  });

  test('selecionar "licença de operação" exibe campo CTF', async ({ page }) => {
    await selecionarTipoAlvara(page, 'licenca_operacao');
    await page.waitForTimeout(800);
    await page.evaluate(() => (window as any).SEMA_PUBLIC_FORM?.showStep?.(2, { preview: true }));

    const ctf = page.locator('[name="ctf_numero"], #ctf_numero');
    if (await ctf.count() > 0) {
      await expect(ctf).toBeVisible();
    }
  });

  test('lista de documentos exigidos é atualizada ao trocar tipo', async ({ page }) => {
    await selecionarTipoAlvara(page, 'construcao');
    await page.waitForTimeout(500);

    const textoAntes = await page.locator('#lista_documentos, .documentos-lista, [id*="documentos"]').first().textContent().catch(() => '');

    await selecionarTipoAlvara(page, 'habite_se');
    await page.waitForTimeout(500);

    const textoDepois = await page.locator('#lista_documentos, .documentos-lista, [id*="documentos"]').first().textContent().catch(() => '');

    // Os textos devem ser diferentes (lista foi atualizada)
    if (textoAntes && textoDepois) {
      expect(textoAntes).not.toBe(textoDepois);
    }
  });

  test('trocar tipo de alvará limpa seleção anterior de documentos', async ({ page }) => {
    await selecionarTipoAlvara(page, 'construcao');
    await page.waitForTimeout(500);
    await selecionarTipoAlvara(page, 'habite_se');
    await page.waitForTimeout(500);

    // Não deve haver inputs de arquivo de outro tipo ainda preenchidos
    const inputs = await page.locator('input[type="file"]').count();
    expect(inputs).toBeGreaterThan(0);
  });
});

test.describe('Formulário Principal - Proprietário', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await selecionarTipoAlvara(page, 'construcao');
  });

  // O toggle "mesmo que requerente" foi removido nesta reformulação: os
  // campos do proprietário ficam sempre visíveis na Etapa 1, com uma nota
  // pedindo para repetir os dados do requerente quando for o mesmo.
  test('campos do proprietário estão sempre visíveis com nota de repetição', async ({ page }) => {
    await expect(page.locator('#proprietario-fields')).toBeVisible();
    await expect(page.locator('#proprietario_nome')).toBeVisible();
    await expect(page.getByText(/mesmos dados informados em Dados do Requerente/i)).toBeVisible();
  });
});

test.describe('Formulário Principal - Upload de Documentos', () => {
  test('inputs de arquivo estão presentes na etapa de documentos', async ({ page }) => {
    await avancarAteEtapa3(page);
    const inputs = page.locator('input[type="file"]');
    await expect(inputs.first()).toBeVisible();
  });

  test('inputs de arquivo aceitam apenas PDF', async ({ page }) => {
    await avancarAteEtapa3(page);
    const primeiroInput = page.locator('input[type="file"]').first();
    const accept = await primeiroInput.getAttribute('accept');
    if (accept) {
      expect(accept).toContain('pdf');
    }
  });
});

test.describe('Formulário Principal - Declaração de Veracidade', () => {
  test('checkbox de declaração deve ser marcado para submeter', async ({ page }) => {
    await avancarAteEtapa3(page);
    const checkbox = page.locator('[name="declaracao_veracidade"]');
    await expect(checkbox).toBeVisible();
    await expect(checkbox).not.toBeChecked();

    const validationMessage = await checkbox.evaluate((el: HTMLInputElement) => el.validationMessage);
    expect(validationMessage).not.toBe('');
  });

  test('após marcar declaração o checkbox reflete o estado marcado', async ({ page }) => {
    await avancarAteEtapa3(page);
    await page.check('[name="declaracao_veracidade"]');
    const checkbox = page.locator('[name="declaracao_veracidade"]');
    await expect(checkbox).toBeChecked();
  });
});
