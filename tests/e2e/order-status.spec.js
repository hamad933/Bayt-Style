import fs from 'node:fs';
import { test, expect } from '@playwright/test';

fs.mkdirSync('storage/test-artifacts/visual', { recursive: true });

const forbiddenCustomerTerms = [
    'S06', 'S07', 'workstream', 'Checkout', 'SLA',
    'pending_payment', 'not_reserved', 'not_started',
    'policy_not_activated', 'demo_unconfigured_zero', 'manual_pending_demo',
    'rp01-s06-development-consent-v1', 'demo_standard',
];

async function expectCustomerCopyClean(page) {
    const visibleText = await page.locator('body').innerText();
    for (const term of forbiddenCustomerTerms) expect(visibleText).not.toContain(term);
}

async function createOrderAndOpenStatus(page) {
    await page.goto('/products/olive-velvet-lounge-chair');
    await page.locator('[data-option-key="color"][data-option-value="رملي"]').click();
    await expect(page.getByTestId('variant-sku')).toHaveText('BAS-CHAIR-SAND-01');
    await page.getByTestId('add-to-cart').click();

    await page.goto('/cart');
    await page.getByTestId('proceed-checkout').click();

    await page.getByLabel('الاسم الكامل').fill('عميل متصفح تجريبي');
    await page.getByLabel('البريد الإلكتروني').fill('browser@example.test');
    await page.getByLabel('رقم الجوال').fill('+966500000002');
    await page.getByLabel('المنطقة / المحافظة').fill('الرياض');
    await page.getByLabel('المدينة').fill('الرياض');
    await page.getByLabel('الحي اختياري').fill('حي تجريبي');
    await page.getByLabel('الشارع / سطر العنوان').fill('شارع اختبار 20');
    await page.getByLabel('المبنى / الوحدة اختياري').fill('وحدة 4');
    await page.getByLabel('الرمز البريدي عند انطباقه').fill('00000');
    await page.locator('input[name="terms"]').check();
    await page.getByTestId('confirm-checkout').click();

    await expect(page).toHaveURL(/\/checkout\/confirmation\//);
    await page.getByRole('link', { name: 'عرض حالة الطلب' }).click();
    await expect(page).toHaveURL(/\/orders\/BAS-/);
    await expect(page.getByTestId('order-status-page')).toBeVisible();
}

for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'tablet-820', width: 820, height: 1180 },
    { name: 'mobile-390', width: 390, height: 844 },
]) {
    test(`S07 truthful order-status evidence ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await createOrderAndOpenStatus(page);

        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByTestId('overall-order-state')).toHaveText('تم استلام طلبك');
        await expect(page.getByTestId('status-payment')).toHaveText('الدفع لم يكتمل بعد');
        await expect(page.getByTestId('status-reservation')).toHaveText('المخزون غير محجوز حتى الآن');
        await expect(page.getByTestId('status-fulfillment')).toHaveText('تجهيز الطلب لم يبدأ بعد');
        await expect(page.getByTestId('shipment-empty')).toContainText('لا توجد شحنة حتى الآن');
        await expect(page.getByTestId('shipment-empty')).toContainText('لا يوجد ناقل أو رقم تتبع أو موعد تسليم مؤكد');
        await expect(page.getByTestId('order-event')).toHaveCount(1);
        await expect(page.getByTestId('order-timeline')).toContainText('تم استلام الطلب');
        await expect(page.getByTestId('saved-order-items')).toContainText('كرسي استرخاء مخملي');
        await expect(page.getByTestId('saved-order-items')).toContainText('الكمية: 1');
        await expect(page.getByTestId('status-total')).toContainText('2,085');
        await expect(page.getByTestId('delivery-summary')).toContainText('الرياض');
        await expect(page.getByTestId('delivery-summary')).toContainText('المنتهي بـ 0002');

        const body = await page.locator('body').innerText();
        expect(body).not.toContain('شارع اختبار 20');
        expect(body).not.toContain('+966500000002');
        expect(body).not.toContain('browser@example.test');
        await expect(page.locator('[data-testid="tracking-number"]')).toHaveCount(0);
        await expect(page.locator('[data-testid="carrier-name"]')).toHaveCount(0);
        await expect(page.locator('[data-testid="delivery-eta"]')).toHaveCount(0);
        await expectCustomerCopyClean(page);

        const hasOverflow = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        );
        expect(hasOverflow, `s07-order-status must not overflow at ${viewport.width}px`).toBe(false);

        await page.screenshot({
            path: `storage/test-artifacts/visual/s07-order-status-${viewport.name}.png`,
            fullPage: true,
        });
    });
}
