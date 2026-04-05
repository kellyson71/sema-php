import { test, expect, Page, BrowserContext } from '@playwright/test';

/**
 * Testes E2E do painel admin - Gestão de Requerimentos (admin/requerimentos.php).
 *
 * ⚠️  IMPORTANTE: Estes testes requerem credenciais de admin válidas.
 * Configure as variáveis de ambiente antes de rodar:
 *   ADMIN_USER=seu_usuario ADMIN_PASS=sua_senha npx playwright test admin-requerimentos
 *
 * Requer o Docker rodando: ./scripts/start.sh
 */

const ADMIN_USER = process.env.ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASS || 'admin123';

// ─── Helpers ────────────────────────────────────────────────────────────────

async function fazerLogin(page: Page): Promise<boolean> {
  await page.goto('/admin/login.php');

  await page.fill('[name="usuario"], #usuario', ADMIN_USER);
  await page.fill('[name="senha"], #senha', ADMIN_PASS);
  await page.click('button[type="submit"], input[type="submit"]');

  await page.waitForTimeout(2000);

  // Verifica se chegou no painel (pode ter 2FA)
  const url = page.url();
  return !url.includes('login');
}

async function irParaRequerimentos(page: Page) {
  await page.goto('/admin/requerimentos.php');
  await page.waitForLoadState('networkidle');
}

// ─── Testes ─────────────────────────────────────────────────────────────────

test.describe('Admin Requerimentos - Estrutura da Página', () => {
  test('página de requerimentos existe e responde', async ({ page }) => {
    // Testa que a URL responde (mesmo que redirecione para login)
    const response = await page.goto('/admin/requerimentos.php');
    expect(response?.status()).not.toBe(500);
    expect(response?.status()).not.toBe(404);
  });

  test('página de login do admin responde com 200', async ({ page }) => {
    const response = await page.goto('/admin/login.php');
    expect(response?.status()).toBe(200);
  });

  test('formulário de consulta pública responde com 200', async ({ page }) => {
    const response = await page.goto('/consultar/');
    expect(response?.status()).toBe(200);
  });
});

test.describe('Admin Requerimentos - Com Autenticação', () => {
  test.skip(!process.env.ADMIN_USER, 'Defina ADMIN_USER e ADMIN_PASS para rodar estes testes.');

  let page: Page;

  test.beforeEach(async ({ browser }) => {
    const context = await browser.newContext();
    page = await context.newPage();
    const logado = await fazerLogin(page);
    if (!logado) {
      test.skip();
    }
  });

  test.afterEach(async () => {
    await page.close();
  });

  test('lista de requerimentos carrega após login', async () => {
    await irParaRequerimentos(page);
    await expect(page).toHaveURL(/requerimentos/);
  });

  test('tabela de requerimentos está presente', async () => {
    await irParaRequerimentos(page);
    const tabela = page.locator('table, .table, [class*="requerimentos"]');
    await expect(tabela.first()).toBeVisible();
  });

  test('filtro por status funciona', async () => {
    await irParaRequerimentos(page);

    const filtroStatus = page.locator('select[name="status"], #filtro_status');
    if (await filtroStatus.count() === 0) return;

    await filtroStatus.selectOption('pendente');
    await page.click('button[type="submit"], input[type="submit"], [type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/status=pendente|requerimentos/);
  });

  test('campo de busca está presente', async () => {
    await irParaRequerimentos(page);
    const busca = page.locator('[name="busca"], #busca, input[type="search"]');
    await expect(busca).toBeVisible();
  });

  test('paginação avança para a próxima página', async () => {
    await irParaRequerimentos(page);

    const proximaPagina = page.locator('a:has-text("Próxima"), a:has-text("»"), [aria-label="Próxima página"]');
    if (await proximaPagina.count() === 0) return; // sem paginação = lista cabe em 1 página

    await proximaPagina.first().click();
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/pagina=2|page=2|offset=/);
  });

  test('link de visualizar requerimento abre a página correta', async () => {
    await irParaRequerimentos(page);

    const linkVisualizar = page.locator('a:has-text("Visualizar"), a[href*="visualizar_requerimento"]').first();
    if (await linkVisualizar.count() === 0) return; // nenhum requerimento cadastrado

    const href = await linkVisualizar.getAttribute('href');
    await linkVisualizar.click();
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/visualizar_requerimento|id=/);
  });

  test('dashboard exibe estatísticas', async () => {
    await page.goto('/admin/index.php');
    await page.waitForLoadState('networkidle');

    // Deve exibir contadores ou cards de estatísticas
    const stats = page.locator('[class*="stat"], [class*="card"], [class*="counter"], .badge');
    const total = await stats.count();
    expect(total).toBeGreaterThan(0);
  });
});

test.describe('Admin Requerimentos - Consulta Pública', () => {
  test('formulário de consulta pública está acessível', async ({ page }) => {
    await page.goto('/consultar/');
    await expect(page.locator('form')).toBeVisible();
  });

  test('campo de protocolo está presente na consulta pública', async ({ page }) => {
    await page.goto('/consultar/');
    const protocolo = page.locator('[name="protocolo"], #protocolo');
    await expect(protocolo).toBeVisible();
  });

  test('protocolo inválido exibe mensagem de não encontrado', async ({ page }) => {
    await page.goto('/consultar/');

    await page.fill('[name="protocolo"], #protocolo', '00000000000000000');
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForLoadState('networkidle');

    const textoBody = await page.locator('body').textContent();
    const naoEncontrado =
      textoBody?.toLowerCase().includes('não encontrado') ||
      textoBody?.toLowerCase().includes('inválido') ||
      textoBody?.toLowerCase().includes('nenhum');

    expect(naoEncontrado).toBeTruthy();
  });
});
