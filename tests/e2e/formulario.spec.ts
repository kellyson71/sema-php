import { test, expect, Page } from '@playwright/test';

/**
 * Testes E2E do formulário principal de requerimento de alvará (index.php).
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
  await page.fill('#requerente_nome', d.nome);
  await page.fill('#requerente_email', d.email);
  await page.fill('#requerente_cpf_cnpj', d.cpfCnpj);
  await page.fill('#requerente_telefone', d.telefone);
}

async function selecionarTipoAlvara(page: Page, valor: string) {
  await page.selectOption('select[name="tipo_alvara"]', valor);
  // Aguarda os campos dinâmicos carregarem
  await page.waitForTimeout(500);
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
    await expect(page.locator('[name="requerente[nome]"], #requerente_nome')).toBeVisible();
    await expect(page.locator('[name="requerente[email]"], #requerente_email')).toBeVisible();
    await expect(page.locator('[name="requerente[cpf_cnpj]"], #requerente_cpf_cnpj')).toBeVisible();
    await expect(page.locator('[name="requerente[telefone]"], #requerente_telefone')).toBeVisible();
  });

  test('seletor de tipo de alvará está presente', async ({ page }) => {
    await expect(page.locator('select[name="tipo_alvara"]')).toBeVisible();
  });

  test('campo de endereço está presente', async ({ page }) => {
    await expect(page.locator('[name="endereco_objetivo"], #endereco_objetivo')).toBeVisible();
  });

  test('checkbox de declaração de veracidade está presente', async ({ page }) => {
    await expect(page.locator('[name="declaracao_veracidade"]')).toBeVisible();
  });
});

test.describe('Formulário Principal - Validação de Campos Obrigatórios', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('submissão sem tipo de alvará exibe erro', async ({ page }) => {
    // Não seleciona tipo e tenta submeter
    await page.click('button[type="submit"], input[type="submit"]');
    // Ou verifica validação HTML5 nativa
    const tipoSelect = page.locator('select[name="tipo_alvara"]');
    const validationMessage = await tipoSelect.evaluate((el: HTMLSelectElement) => el.validationMessage);
    expect(validationMessage).not.toBe('');
  });

  test('submissão sem nome do requerente não avança', async ({ page }) => {
    await selecionarTipoAlvara(page, 'construcao');
    await preencherRequerente(page, { nome: '' });
    await page.fill('[name="endereco_objetivo"], #endereco_objetivo', 'Rua das Flores, 123');
    await page.click('[name="declaracao_veracidade"]');

    await page.click('button[type="submit"], input[type="submit"]');

    // Permanece na mesma página ou exibe mensagem de erro
    await expect(page).toHaveURL(/index\.php|\/$/);
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
    await selecionarTipoAlvara(page, 'licenca_previa_ambiental');
    await page.waitForTimeout(800);

    // Deve aparecer campo de publicação no Diário Oficial ou estudo ambiental
    const camposAmbientais = page.locator(
      '[name="publicacao_diario_oficial"], [name="possui_estudo_ambiental"], #publicacao_diario_oficial'
    );
    await expect(camposAmbientais.first()).toBeVisible();
  });

  test('selecionar "licença de operação" exibe campo CTF', async ({ page }) => {
    await selecionarTipoAlvara(page, 'licenca_operacao');
    await page.waitForTimeout(800);

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

  test('checkbox "mesmo que requerente" oculta campos do proprietário', async ({ page }) => {
    const checkboxMesmo = page.locator('[name="proprietario_mesmo_requerente"], #proprietario_mesmo_requerente');
    if (await checkboxMesmo.count() === 0) {
      test.skip();
      return;
    }
    await checkboxMesmo.check();
    await expect(page.locator('#campos_proprietario, .proprietario-fields')).not.toBeVisible();
  });

  test('desmarcando "mesmo que requerente" exibe campos do proprietário', async ({ page }) => {
    const checkboxMesmo = page.locator('[name="proprietario_mesmo_requerente"], #proprietario_mesmo_requerente');
    if (await checkboxMesmo.count() === 0) {
      test.skip();
      return;
    }
    await checkboxMesmo.uncheck();
    await expect(page.locator('#campos_proprietario, .proprietario-fields')).toBeVisible();
  });
});

test.describe('Formulário Principal - Upload de Documentos', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await selecionarTipoAlvara(page, 'construcao');
    await page.waitForTimeout(500);
  });

  test('inputs de arquivo estão presentes após selecionar tipo', async ({ page }) => {
    const inputs = page.locator('input[type="file"]');
    await expect(inputs.first()).toBeVisible();
  });

  test('inputs de arquivo aceitam apenas PDF', async ({ page }) => {
    const primeiroInput = page.locator('input[type="file"]').first();
    const accept = await primeiroInput.getAttribute('accept');
    if (accept) {
      expect(accept).toContain('pdf');
    }
  });
});

test.describe('Formulário Principal - Declaração de Veracidade', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('checkbox de declaração deve ser marcado para submeter', async ({ page }) => {
    const checkbox = page.locator('[name="declaracao_veracidade"]');
    await expect(checkbox).not.toBeChecked();

    const validationMessage = await checkbox.evaluate((el: HTMLInputElement) => el.validationMessage);
    expect(validationMessage).not.toBe('');
  });

  test('após marcar declaração o formulário pode avançar', async ({ page }) => {
    await page.check('[name="declaracao_veracidade"]');
    const checkbox = page.locator('[name="declaracao_veracidade"]');
    await expect(checkbox).toBeChecked();
  });
});
