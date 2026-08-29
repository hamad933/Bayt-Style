import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/checkout-validation-quality');
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

async function assertNoPageOverflow(page) {
  const metrics = await page.evaluate(() => ({
    documentWidth: document.documentElement.scrollWidth,
    viewportWidth: document.documentElement.clientWidth,
  }));
  expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
}

async function assertInsideInitialViewport(locator, viewportHeight) {
  const box = await locator.boundingBox();
  expect(box).not.toBeNull();
  expect(box.y).toBeGreaterThanOrEqual(0);
  expect(box.y).toBeLessThan(viewportHeight);
}

async function createCheckout(page) {
  await page.goto('/products/olive-velvet-lounge-chair');
  const sand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
  await sand.click();
  await expect(sand).toHaveAttribute('aria-pressed', 'true');
  await expect(page.getByTestId('variant-sku')).toHaveText('BAS-CHAIR-SAND-01');
  await page.getByTestId('add-to-cart').click();
  await page.goto('/checkout');
  await expect(page.getByTestId('checkout-page')).toContainText('BAS-CHAIR-SAND-01');
}

test('[QUALITY][CHECKOUT VALIDATION] server validation is focused, visible, recoverable, and programmatically associated on mobile', async ({ page }) => {
  const viewport = { width: 390, height: 844 };
  await page.setViewportSize(viewport);
  const runtimeFailures = watchRuntime(page);
  await createCheckout(page);

  await page.getByLabel('البريد الإلكتروني').fill('invalid-address');
  await page.getByTestId('checkout-form').evaluate((form) => { form.noValidate = true; });
  await page.getByTestId('confirm-checkout').click();

  await expect(page).toHaveURL(/\/checkout$/);
  const summary = page.getByTestId('checkout-errors');
  await expect(summary).toBeVisible();
  await expect(summary).toBeFocused();
  await expect(summary).toHaveAttribute('role', 'alert');
  await expect(summary).toContainText('راجع الحقول التالية:');
  await expect(summary).toContainText('حقل الاسم الكامل مطلوب.');
  await expect(summary).toContainText('أدخل البريد الإلكتروني بصيغة صحيحة.');
  await expect(summary).toContainText('حقل رقم الجوال مطلوب.');
  await expect(summary).toContainText('حقل المنطقة / المحافظة مطلوب.');
  await expect(summary).toContainText('حقل المدينة مطلوب.');
  await expect(summary).toContainText('حقل العنوان مطلوب.');
  await expect(summary).toContainText('يجب الموافقة صراحةً على الشروط المعروضة قبل تأكيد الطلب.');

  const associations = [
    ['الاسم الكامل', 'full-name-error', 'حقل الاسم الكامل مطلوب.'],
    ['البريد الإلكتروني', 'email-error', 'أدخل البريد الإلكتروني بصيغة صحيحة.'],
    ['رقم الجوال', 'phone-error', 'حقل رقم الجوال مطلوب.'],
    ['المنطقة / المحافظة', 'region-error', 'حقل المنطقة / المحافظة مطلوب.'],
    ['المدينة', 'city-error', 'حقل المدينة مطلوب.'],
    ['الشارع / سطر العنوان', 'address-line-error', 'حقل العنوان مطلوب.'],
  ];

  for (const [label, errorId, message] of associations) {
    const field = page.getByLabel(label);
    await expect(field).toHaveAttribute('aria-invalid', 'true');
    await expect(field).toHaveAttribute('aria-describedby', errorId);
    const inlineError = page.locator(`#${errorId}`);
    await expect(inlineError).toBeVisible();
    await expect(inlineError).toHaveText(message);
  }

  const terms = page.getByRole('checkbox', { name: /أوافق على إرسال بيانات هذا الطلب/ });
  await expect(terms).toHaveAttribute('aria-invalid', 'true');
  await expect(terms).toHaveAttribute('aria-describedby', 'terms-error');
  await expect(page.locator('#terms-error')).toBeVisible();
  await expect(page.locator('#terms-error')).toHaveText('يجب الموافقة صراحةً على الشروط المعروضة قبل تأكيد الطلب.');

  await expect(page.getByLabel('البريد الإلكتروني')).toHaveValue('invalid-address');
  await assertInsideInitialViewport(summary, viewport.height);
  await assertNoPageOverflow(page);
  expect(runtimeFailures).toEqual([]);

  await page.screenshot({
    path: path.join(outputDir, 'checkout-validation-errors-focused-390.png'),
    fullPage: false,
  });
});
