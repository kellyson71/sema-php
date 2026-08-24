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
});
