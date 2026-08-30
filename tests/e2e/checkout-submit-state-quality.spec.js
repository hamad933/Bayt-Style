import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/checkout-submit-state-quality');
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

async function createCheckout(page) {
  await page.goto('/products/olive-velvet-lounge-chair');
  const sand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
  await sand.click();
  await expect(sand).toHaveAttribute('aria-pressed', 'true');
  await page.getByTestId('add-to-cart').click();
  await expect(page.locator('.cart-badge')).toHaveText('1');
  await page.goto('/checkout');
  await expect(page.getByTestId('checkout-page')).toBeVisible();
}

test('[QUALITY][CHECKOUT SUBMIT] confirmation exposes a visible single-flight submitting state on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  await createCheckout(page);

  await page.getByLabel('الاسم الكامل').fill('عميل تجريبي');
  await page.getByLabel('البريد الإلكتروني').fill('customer@example.test');
  await page.getByLabel('رقم الجوال').fill('0500000000');
  await page.getByLabel('المنطقة / المحافظة').fill('الرياض');
  await page.getByLabel('المدينة').fill('الرياض');
  await page.getByLabel('الشارع / سطر العنوان').fill('شارع الاختبار 1');
  await page.getByRole('checkbox', { name: /أوافق على إرسال بيانات هذا الطلب/ }).check();

  const form = page.getByTestId('checkout-form');
  const submit = page.getByTestId('confirm-checkout');
  const status = page.getByTestId('checkout-submitting-status');

  const firstSubmissionAccepted = await form.evaluate((element) => {
    const submitter = element.querySelector('[data-testid="confirm-checkout"]');
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
  await expect(submit).toHaveText('جارٍ تأكيد الطلب…');
  await expect(status).toBeVisible();
  await expect(status).toHaveText('جارٍ إرسال الطلب. يرجى عدم إغلاق الصفحة أو إعادة الإرسال.');

  const secondSubmissionAccepted = await form.evaluate((element) => {
    const submitter = element.querySelector('[data-testid="confirm-checkout"]');
    return element.dispatchEvent(new SubmitEvent('submit', {
      bubbles: true,
      cancelable: true,
      submitter,
    }));
  });
  expect(secondSubmissionAccepted).toBe(false);

  const box = await status.boundingBox();
  expect(box).not.toBeNull();
  expect(box.y).toBeGreaterThanOrEqual(0);
  expect(box.y).toBeLessThan(844);

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
  expect(runtimeFailures).toEqual([]);

  await page.screenshot({
    path: path.join(outputDir, 'checkout-submitting-state-390.png'),
    fullPage: false,
  });
});
