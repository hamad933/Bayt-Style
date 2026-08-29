import { expect, test } from '@playwright/test';

test('[QUALITY][A11Y] cart trigger exposes the current action name', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = [];
  page.on('pageerror', (error) => runtimeFailures.push(`pageerror: ${error.message}`));
  page.on('console', (message) => {
    if (message.type() === 'error') runtimeFailures.push(`console.error: ${message.text()}`);
  });
  page.on('response', (response) => {
    if (response.status() >= 500) runtimeFailures.push(`HTTP ${response.status()}: ${response.url()}`);
  });

  await page.goto('/');

  const openTrigger = page.getByRole('button', { name: 'فتح السلة' });
  await expect(openTrigger).toBeVisible();
  await expect(openTrigger).toHaveAttribute('aria-expanded', 'false');

  await openTrigger.click();

  const closeTrigger = page.getByRole('button', { name: 'إغلاق السلة' }).first();
  await expect(closeTrigger).toHaveAttribute('aria-expanded', 'true');
  await expect(page.getByRole('dialog', { name: 'مختاراتك الحالية' })).toBeVisible();

  await page.getByRole('button', { name: 'إغلاق السلة' }).last().click();
  await expect(page.getByRole('button', { name: 'فتح السلة' })).toHaveAttribute('aria-expanded', 'false');
  expect(runtimeFailures).toEqual([]);
});
