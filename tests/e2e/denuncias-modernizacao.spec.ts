import { test, expect, Page } from '@playwright/test';

const ADMIN_USER = process.env.ADMIN_USER;
const ADMIN_PASS = process.env.ADMIN_PASS;
const OPERATOR_USER = process.env.OPERATOR_USER;
const OPERATOR_PASS = process.env.OPERATOR_PASS;

async function login(page: Page) {
  await page.goto('/admin/login.php');
  await page.fill('[name="usuario"]', ADMIN_USER!);
  await page.fill('[name="senha"]', ADMIN_PASS!);
  await page.click('button[type="submit"]');
  await page.waitForURL(url => !url.pathname.endsWith('/login.php'));
  const releaseButton = page.getByRole('button', { name: 'Entendido' });
  if (await releaseButton.isVisible().catch(() => false)) {
    await releaseButton.click();
    await page.waitForTimeout(500);
  }
}

async function dismissRelease(page: Page) {
  const releaseButton = page.getByRole('button', { name: 'Entendido' });
  if (await releaseButton.isVisible().catch(() => false)) {
    await releaseButton.click();
    await expect(page.locator('#changelogModal')).not.toBeVisible();
  }
}

test.describe('Modernização de denúncias', () => {
  test.skip(!ADMIN_USER || !ADMIN_PASS, 'Defina ADMIN_USER e ADMIN_PASS para o fluxo autenticado.');

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('lista filtros, anonimato e preferência persistente', async ({ page }) => {
    await page.goto('/admin/denuncias.php?limpar=1');
    await dismissRelease(page);
    await expect(page.locator('.page-title', { hasText: 'Denúncias' })).toBeVisible();
    await expect(page.locator('select[name="origem"] option[value="minhas"]')).toHaveText('Criadas por mim');
    await expect(page.getByText('Denúncia anônima').first()).toBeVisible();

    await page.selectOption('select[name="setor"]', 'obras_urbanismo');
    await page.selectOption('select[name="origem"]', 'publico');
    await page.getByRole('button', { name: 'Aplicar filtros' }).click();
    await page.getByRole('button', { name: 'Salvar como padrão' }).click();
    await expect(page.getByText('Os filtros atuais foram salvos como seu padrão.')).toBeVisible();
    await expect(page.locator('select[name="setor"]')).toHaveValue('obras_urbanismo');
    await expect(page.locator('select[name="origem"]')).toHaveValue('publico');
  });

  test('feed principal mistura tipos e encaminha para a rota correta', async ({ page }) => {
    await page.goto('/admin/requerimentos.php?fonte=todos&encerrados=1');
    await expect(page.locator('.feed-source-chip').filter({ hasText: 'Todos' })).toBeVisible();
    await expect(page.locator('.req-list-item[data-record-type="denuncia"]').first()).toBeVisible();
    await expect(page.locator('.req-list-item:not([data-record-type="denuncia"])').first()).toBeVisible();
    await expect(page.locator('.req-list-item[data-record-type="denuncia"] input[type="checkbox"]')).toHaveCount(0);

    const href = await page.locator('.req-list-item[data-record-type="denuncia"] a.req-open-button').first().getAttribute('href');
    expect(href).toMatch(/^visualizar_denuncia\.php\?id=\d+$/);
  });

  test('busca contextual sugere denúncias filtradas e Enter pesquisa a lista', async ({ page }) => {
    await page.goto('/admin/denuncias.php?setor=meio_ambiente&origem=&status=&anonimo=&concluidas=0');
    await dismissRelease(page);
    const busca = page.locator('#busca');
    await busca.fill('Não');
    await expect(page.locator('#denunciaSuggestions')).toHaveClass(/active/);
    await expect(page.locator('.den-suggestion').first()).toBeVisible();
    const metas = page.locator('.den-suggestion-meta');
    await expect(metas.first()).toContainText('Meio Ambiente');
    expect(await metas.allTextContents()).toEqual(expect.arrayContaining([
      expect.stringContaining('Meio Ambiente'),
    ]));
    for (const meta of await metas.all()) {
      await expect(meta).toContainText('Meio Ambiente');
    }
    await expect(page.locator('.den-suggestion').first()).toHaveAttribute('href', /visualizar_denuncia\.php\?id=\d+/);

    await busca.press('Enter');
    await expect(page).toHaveURL(/busca=N%C3%A3o/);
    await expect(page.locator('.den-card').first()).toBeVisible();
  });

  test('detalhes mantêm ações e se adaptam ao celular', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/admin/visualizar_denuncia.php?id=5');
    await expect(page.getByRole('link', { name: 'Registrar andamento' })).toBeVisible();
    await expect(page.getByText('Denúncia anônima').first()).toBeVisible();
    await expect(page.locator('a[href*="selecionar_denuncia.php"]').first()).toBeVisible();
    await expect(page.locator('.proc-header')).toBeVisible();
    await expect(page.locator('.cmd-bar')).toBeVisible();
    await expect(page.locator('.den-proc-tag.anonima')).toHaveCSS('color', 'rgb(255, 255, 255)');
    await expect(page.locator('.cmd-more-btn')).toBeVisible();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(2);
  });

  test('perfil não administrativo não acessa nem vê Filas por Setor', async ({ page }) => {
    test.skip(!OPERATOR_USER || !OPERATOR_PASS, 'Defina credenciais de um perfil não administrativo.');
    await page.goto('/admin/logout.php');
    await page.goto('/admin/login.php');
    await page.fill('[name="usuario"]', OPERATOR_USER!);
    await page.fill('[name="senha"]', OPERATOR_PASS!);
    await page.click('button[type="submit"]');
    await page.waitForURL(url => !url.pathname.endsWith('/login.php'));

    await page.goto('/admin/fila_setor.php');
    await expect(page).toHaveURL(/\/admin\/requerimentos\.php$/);
    await expect(page.locator('.sidebar-link[href*="fila_setor.php"]')).toHaveCount(0);
    await expect(page.locator('[data-search-item][href*="fila_setor.php"]')).toHaveCount(0);
  });
});
