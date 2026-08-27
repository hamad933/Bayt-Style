import { expect, test } from '@playwright/test';

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

async function assertAccessibleSurface(page, surface) {
  const defects = await page.evaluate(() => {
    const defects = [];
    const visible = (element) => {
      const style = window.getComputedStyle(element);
      return style.display !== 'none' && style.visibility !== 'hidden' && element.getClientRects().length > 0;
    };
    const labelledByText = (element) => (element.getAttribute('aria-labelledby') || '')
      .split(/\s+/)
      .filter(Boolean)
      .map((id) => document.getElementById(id)?.textContent?.trim() || '')
      .join(' ')
      .trim();
    const labelText = (element) => Array.from(element.labels || [])
      .map((label) => label.textContent?.trim() || '')
      .join(' ')
      .trim();
    const accessibleName = (element) => (
      element.getAttribute('aria-label')?.trim()
      || labelledByText(element)
      || labelText(element)
      || element.getAttribute('alt')?.trim()
      || element.getAttribute('title')?.trim()
      || element.textContent?.trim()
      || ''
    );

    if (document.documentElement.lang !== 'ar') defects.push(`html lang=${JSON.stringify(document.documentElement.lang)}`);
    if (document.documentElement.dir !== 'rtl') defects.push(`html dir=${JSON.stringify(document.documentElement.dir)}`);
    if (!document.title.trim()) defects.push('document title is empty');
    if (document.documentElement.scrollWidth > document.documentElement.clientWidth + 1) {
      defects.push(`horizontal overflow ${document.documentElement.scrollWidth}>${document.documentElement.clientWidth}`);
    }

    const ids = Array.from(document.querySelectorAll('[id]')).map((element) => element.id).filter(Boolean);
    const duplicateIds = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))];
    if (duplicateIds.length) defects.push(`duplicate ids: ${duplicateIds.join(', ')}`);

    for (const image of document.querySelectorAll('img')) {
      if (!visible(image)) continue;
      if (!image.hasAttribute('alt')) defects.push(`visible image missing alt: ${image.getAttribute('src') || '<dynamic>'}`);
    }
    for (const control of document.querySelectorAll('input, select, textarea')) {
      if (!visible(control) || control.type === 'hidden') continue;
      if (!accessibleName(control)) defects.push(`unnamed form control: ${control.tagName.toLowerCase()}#${control.id || '<no-id>'}`);
    }
    for (const interactive of document.querySelectorAll('button, a[href]')) {
      if (!visible(interactive)) continue;
      if (!accessibleName(interactive)) defects.push(`unnamed interactive element: ${interactive.tagName.toLowerCase()}#${interactive.id || '<no-id>'}`);
    }
    return defects;
  });

  expect(defects, `${surface} quality defects`).toEqual([]);
}

async function createPendingOrder(page) {
  await page.goto('/products/olive-velvet-lounge-chair');
  const sand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
  await sand.click();
  await expect(sand).toHaveAttribute('aria-pressed', 'true');
  await expect(page.getByTestId('variant-sku')).toHaveText('BAS-CHAIR-SAND-01');
  await page.getByTestId('add-to-cart').click();
  await page.goto('/checkout');
  await expect(page.getByTestId('checkout-page')).toContainText('BAS-CHAIR-SAND-01');
}

async function fillCheckout(page) {
  await page.getByLabel('الاسم الكامل').fill('عميل جودة تجريبي');
  await page.getByLabel('البريد الإلكتروني').fill('quality@example.test');
  await page.getByLabel('رقم الجوال').fill('+966500000088');
  await page.getByLabel('المنطقة / المحافظة').fill('الرياض');
  await page.getByLabel('المدينة').fill('الرياض');
  await page.getByLabel('الحي اختياري').fill('حي الجودة');
  await page.getByLabel('الشارع / سطر العنوان').fill('شارع الجودة 88');
  await page.getByLabel('المبنى / الوحدة اختياري').fill('وحدة 8');
  await page.getByLabel('الرمز البريدي عند انطباقه').fill('00000');
  await page.locator('input[name="terms"]').check();
}

for (const viewport of [
  { width: 1440, height: 1000 },
  { width: 390, height: 844 },
]) {
  test(`[QUALITY][TRANSACTION] checkout through returns stays accessible and runtime-clean at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    const runtimeFailures = watchRuntime(page);

    await createPendingOrder(page);
    await assertAccessibleSurface(page, 'checkout');
    await expect(page.getByTestId('checkout-page')).toContainText('BAS-CHAIR-SAND-01');

    await fillCheckout(page);
    await page.getByTestId('confirm-checkout').click();
    await expect(page).toHaveURL(/\/checkout\/confirmation\//);
    await expect(page.getByTestId('payment-state')).toHaveText('الدفع لم يكتمل بعد');
    await expect(page.getByTestId('reservation-state')).toHaveText('المخزون غير محجوز حتى الآن');
    await assertAccessibleSurface(page, 'confirmation');

    await page.getByRole('link', { name: 'عرض حالة الطلب' }).click();
    await expect(page).toHaveURL(/\/orders\/BAS-/);
    await expect(page.getByTestId('status-payment')).toHaveText('الدفع لم يكتمل بعد');
    await expect(page.getByTestId('status-reservation')).toHaveText('المخزون غير محجوز حتى الآن');
    await expect(page.getByTestId('status-fulfillment')).toHaveText('تجهيز الطلب لم يبدأ بعد');
    await assertAccessibleSurface(page, 'order status');

    await page.getByTestId('open-returns').click();
    await expect(page).toHaveURL(/\/orders\/BAS-.*\/returns/);
    await expect(page.getByTestId('return-eligibility-state')).toContainText('طلب المرتجع غير متاح حاليًا');
    await expect(page.getByTestId('store-credit-balance')).toContainText('0.00');
    await assertAccessibleSurface(page, 'returns/refunds/store credit');

    expect(runtimeFailures, `transaction runtime failures at ${viewport.width}px`).toEqual([]);
  });
}
