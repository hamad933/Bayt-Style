import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/session-expiry-quality');
fs.mkdirSync(outputDir, { recursive: true });

function watchRuntime(page) {
  const failures = [];
  page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`));
  page.on('console', (message) => {
    if (message.type() !== 'error') return;

    const text = message.text();
    const isExpectedDocument419 = text === 'Failed to load resource: the server responded with a status of 419 (unknown status)';
    if (!isExpectedDocument419) failures.push(`console.error: ${text}`);
  });
  page.on('response', (response) => {
    if (response.status() >= 500) failures.push(`HTTP ${response.status()}: ${response.url()}`);
    if (response.status() === 419 && response.request().resourceType() !== 'document') {
      failures.push(`unexpected HTTP 419: ${response.url()}`);
    }
  });
  return failures;
}

async function assertNoPageOverflow(page) {
  const metrics = await page.evaluate(() => ({
    documentWidth: document.documentElement.scrollWidth,
    viewportWidth: document.documentElement.clientWidth,
  }));
  expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
}

async function startCsrfEnabledServer() {
  const port = 8019;
  const baseUrl = `http://127.0.0.1:${port}`;
  const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`], {
    cwd: process.cwd(),
    env: { ...process.env, APP_ENV: 'local', APP_URL: baseUrl },
    stdio: 'ignore',
  });

  for (let attempt = 0; attempt < 30; attempt += 1) {
    try {
      const response = await fetch(`${baseUrl}/up`);
      if (response.ok) return { server, baseUrl };
    } catch {
      // Server is still starting.
    }
    await new Promise((resolve) => setTimeout(resolve, 250));
  }

  server.kill('SIGTERM');
  throw new Error('CSRF-enabled Laravel evidence server did not become ready.');
}

test('[QUALITY][419] expired customer session is Arabic, branded, recoverable and runtime-clean', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  const { server, baseUrl } = await startCsrfEnabledServer();

  try {
    await page.goto(`${baseUrl}/cart`);
    const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    expect(token).toBeTruthy();

    await page.context().clearCookies();

    const [response] = await Promise.all([
      page.waitForResponse((candidate) => candidate.url().endsWith('/cart/items') && candidate.request().resourceType() === 'document'),
      page.evaluate(({ action, csrfToken }) => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '_token';
        tokenInput.value = csrfToken;
        form.appendChild(tokenInput);

        document.body.appendChild(form);
        form.submit();
      }, { action: `${baseUrl}/cart/items`, csrfToken: token }),
    ]);

    expect(response.status()).toBe(419);
    await page.waitForLoadState('domcontentloaded');
    await page.screenshot({ path: path.join(outputDir, 'session-expired-390.png'), fullPage: true });

    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.getByRole('heading', { name: 'انتهت جلسة التصفح' })).toBeVisible();
    await expect(page.getByText('انتهت صلاحية الجلسة قبل إكمال الإجراء المطلوب.')).toBeVisible();
    const cartRecovery = page.getByRole('link', { name: 'العودة إلى السلة' });
    await expect(cartRecovery).toBeVisible();
    await expect(cartRecovery).toHaveAttribute('href', `${baseUrl}/cart`);
    await expect(page.getByRole('link', { name: 'العودة إلى الرئيسية' })).toBeVisible();
    await assertNoPageOverflow(page);
    expect(runtimeFailures).toEqual([]);
  } finally {
    server.kill('SIGTERM');
  }
});
