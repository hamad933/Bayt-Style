import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/admin-login-submit-state-quality');
fs.mkdirSync(outputDir, { recursive: true });

function watchRuntime(page) {
  const failures = [];
  page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`));
  page.on('console', (message) => {
    if (message.type() === 'error') failures.push(`console.error: ${message.text()}`);
  });
  page.on('response', (response) => {
    if (response.status() >= 500) failures.push(`HTTP ${response.status()}: ${response.url()}`);
  });
  return failures;
}

test('[QUALITY][ADMIN LOGIN] login exposes a visible single-flight submitting state', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);

  await page.goto('/admin/login');
  const form = page.locator('.admin-login-card form');
  const email = page.getByLabel('البريد الإلكتروني');
  const password = page.getByLabel('كلمة المرور');
  const submit = form.locator('button[type="submit"]');

  await email.fill('admin-quality@example.test');
  await password.fill('quality-only-password');
  await expect(submit).toHaveText('دخول آمن');
  await submit.scrollIntoViewIfNeeded();

  const firstSubmissionAccepted = await form.evaluate((element) => {
    const submitter = element.querySelector('button[type="submit"]');
    return element.dispatchEvent(new SubmitEvent('submit', {
      bubbles: true,
      cancelable: true,
      submitter,
    }));
  });
  expect(firstSubmissionAccepted).toBe(true);

  await expect(form).toHaveAttribute('aria-busy', 'true');
  await expect(submit).toBeDisabled();
  await expect(submit).toHaveAttribute('aria-busy', 'true');
  await expect(submit).toHaveText('جارٍ الدخول…');

  const status = page.getByRole('status');
  await expect(status).toBeVisible();
  await expect(status).toHaveText('جارٍ التحقق من بيانات الدخول…');
  await expect(email).toBeEnabled();
  await expect(password).toBeEnabled();

  const payload = await form.evaluate((element) => Object.fromEntries(new FormData(element).entries()));
  expect(payload.email).toBe('admin-quality@example.test');
  expect(payload.password).toBe('quality-only-password');
  expect(payload._token).toBeTruthy();

  const secondSubmissionAccepted = await form.evaluate((element) => {
    const submitter = element.querySelector('button[type="submit"]');
    return element.dispatchEvent(new SubmitEvent('submit', {
      bubbles: true,
      cancelable: true,
      submitter,
    }));
  });
  expect(secondSubmissionAccepted).toBe(false);

  const statusBox = await status.boundingBox();
  expect(statusBox).not.toBeNull();
  expect(statusBox.y).toBeGreaterThanOrEqual(0);
  expect(statusBox.y + statusBox.height).toBeLessThanOrEqual(844);

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
  expect(runtimeFailures).toEqual([]);

  await page.screenshot({
    path: path.join(outputDir, 'admin-login-submitting-state-390.png'),
    fullPage: false,
  });
});
