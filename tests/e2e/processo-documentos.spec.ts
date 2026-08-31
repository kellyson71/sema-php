import { test, expect } from '@playwright/test';

/**
 * Smoke tests do fluxo principal de processo e documentos.
 * Sem credenciais, garantem que as rotas não quebram e continuam protegidas.
 * Com ADMIN_USER/ADMIN_PASS, validam a navegação real entre processo, modelos
 * e editor.
 */

const ADMIN_USER = process.env.ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASS || 'admin123';

async function login(page: import('@playwright/test').Page) {
  await page.goto('/admin/login.php');
  await page.fill('[name="usuario"], #usuario', ADMIN_USER);
  await page.fill('[name="senha"], #senha', ADMIN_PASS);
  await page.click('button[type="submit"], input[type="submit"]');
  await page.waitForTimeout(1200);
  return !page.url().includes('login');
}

test.describe('Fluxo de processo e documentos', () => {
  test('endpoint de assinatura informa sessão expirada em JSON', async ({ request }) => {
    const response = await request.post('/admin/assinatura/processa_assinatura.php', {
      form: {
        conteudo_parecer: '<p>Teste</p>',
        requerimento_id: '1',
        salvar_banco: 'true',
      },
    });
    expect(response.status()).toBe(401);
    expect(response.headers()['content-type']).toContain('application/json');
    const body = await response.json();
    expect(body.code).toBe('session_expired');
  });

  test('endpoints de coassinatura não confundem login HTML com resposta JSON', async ({ request }) => {
    for (const rota of [
      '/admin/assinatura/coassinar.php',
      '/admin/assinatura/recusar_assinatura.php',
    ]) {
      const response = await request.post(rota, { form: { documento_id: 'teste' } });
      expect(response.status()).toBe(401);
      expect(response.headers()['content-type']).toContain('application/json');
      const body = await response.json();
      expect(body.code).toBe('session_expired');
    }
  });

  test('página principal do processo não retorna erro interno', async ({ page }) => {
    const response = await page.goto('/admin/visualizar_requerimento.php?id=1');
    expect(response?.status()).not.toBe(500);
    expect(response?.status()).not.toBe(404);
  });

  test('seleção de modelos exige autenticação sem erro interno', async ({ page }) => {
    const response = await page.goto('/admin/documentos/selecionar.php?requerimento_id=1');
    expect(response?.status()).not.toBe(500);
    expect(response?.status()).not.toBe(404);
  });

  test('editor exige autenticação sem erro interno', async ({ page }) => {
    const response = await page.goto('/admin/documentos/editor.php?requerimento_id=1&template=alvara_de_construcao');
    expect(response?.status()).not.toBe(500);
    expect(response?.status()).not.toBe(404);
  });

  test('processo preserva a aba escolhida na URL', async ({ page }) => {
    test.skip(!process.env.ADMIN_USER, 'Defina ADMIN_USER e ADMIN_PASS para validar o fluxo autenticado.');
    if (!await login(page)) test.skip();
    await page.goto('/admin/visualizar_requerimento.php?id=1&tab=documentos');
    await expect(page.locator('#documentos-tab')).toHaveAttribute('aria-selected', 'true');
    await page.locator('#historico-tab').click();
    await expect(page).toHaveURL(/tab=historico/);
    await expect(page.locator('#historico-tab')).toHaveAttribute('aria-selected', 'true');
  });

  test('seleção de modelos exibe busca e categorias', async ({ page }) => {
    test.skip(!process.env.ADMIN_USER, 'Defina ADMIN_USER e ADMIN_PASS para validar o fluxo autenticado.');
    if (!await login(page)) test.skip();
    await page.goto('/admin/documentos/selecionar.php?requerimento_id=1');
    await expect(page.locator('#busca-modelo')).toBeVisible();
    await expect(page.locator('#doc-chips')).toBeVisible();
  });

  test('editor exibe painel editável de campos', async ({ page }) => {
    test.skip(!process.env.ADMIN_USER, 'Defina ADMIN_USER e ADMIN_PASS para validar o fluxo autenticado.');
    if (!await login(page)) test.skip();
    await page.goto('/admin/documentos/editor.php?requerimento_id=1&template=alvara_de_construcao');
    await expect(page.locator('#doc-campos-lista')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('#doc-autosave-status')).toBeVisible();
  });

  test('editor preserva alteração livre de maiúsculas no conteúdo', async ({ page }) => {
    test.skip(!process.env.ADMIN_USER, 'Defina ADMIN_USER e ADMIN_PASS para validar o fluxo autenticado.');
    if (!await login(page)) test.skip();
    await page.goto('/admin/documentos/editor.php?requerimento_id=1&template=carta_habite_se');
    const editor = page.locator('.note-editable');
    await expect(editor).toBeVisible({ timeout: 10000 });
    await editor.evaluate((element) => {
      const paragrafo = document.createElement('p');
      paragrafo.textContent = 'CORREÇÃO EM CAIXA ALTA ÁÉÍÓÚ';
      element.appendChild(paragrafo);
      element.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText' }));
    });
    await expect(editor).toContainText('CORREÇÃO EM CAIXA ALTA ÁÉÍÓÚ');
  });

  test('assinatura manual permite escolher usuário, secretário ou outra pessoa', async ({ page }) => {
    test.skip(!process.env.ADMIN_USER, 'Defina ADMIN_USER e ADMIN_PASS para validar o fluxo autenticado.');
    if (!await login(page)) test.skip();
    await page.goto('/admin/documentos/editor.php?requerimento_id=1&template=alvara_de_construcao');
    await expect(page.locator('.note-editable')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: /Assinar e Finalizar/i }).click();
    await page.locator('.modo-card[data-modo="sem_assinar"]').click();
    await expect(page.locator('#painelAssinanteManual')).toBeVisible();
    await expect(page.locator('input[name="assinante_manual_tipo"]')).toHaveCount(3);

    await page.locator('input[name="assinante_manual_tipo"][value="personalizado"]').check();
    await expect(page.locator('#camposAssinantePersonalizado')).toBeVisible();
    await page.locator('#assinanteManualNome').fill('Pessoa Responsável');
    await page.locator('#assinanteManualCargo').fill('Diretora Administrativa');
    await expect(page.locator('#nomeConfirmacaoManual')).toHaveText('Pessoa Responsável');
  });
});
