import { defineConfig, devices } from '@playwright/test';

/**
 * URL alvo dos testes E2E.
 * Por padrão aponta para o ambiente de homologação.
 * Sobrescreva com: BASE_URL=http://localhost:8090 npx playwright test
 */
const BASE_URL = process.env.BASE_URL ?? 'https://sematst.protocolosead.com';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,

  reporter: [
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['json', { outputFile: 'tests/results/playwright-raw.json' }],
    ['list'],
  ],

  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    locale: 'pt-BR',
    timezoneId: 'America/Fortaleza',
  },

  projects: [
    {
      name: 'homologacao',
      use: {
        ...devices['Desktop Chrome'],
        baseURL: BASE_URL,
      },
    },
    {
      name: 'local',
      use: {
        ...devices['Desktop Chrome'],
        baseURL: 'http://localhost:8090',
      },
    },
  ],
});
