import { expect, test } from '@playwright/test';

test('[QUALITY][A11Y] mobile menu exposes the current action name', async ({ page }) => {
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

  const toggle = page.getByRole('button', { name: 'فتح القائمة' });
  await expect(toggle).toBeVisible();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');

  await toggle.click();

  const closeToggle = page.getByRole('button', { name: 'إغلاق القائمة' });
  await expect(closeToggle).toBeVisible();
  await expect(closeToggle).toHaveAttribute('aria-expanded', 'true');
  await expect(page.getByRole('navigation', { name: 'تنقل الجوال' })).toBeVisible();

  await closeToggle.click();
  await expect(page.getByRole('button', { name: 'فتح القائمة' })).toHaveAttribute('aria-expanded', 'false');
  expect(runtimeFailures).toEqual([]);
});
