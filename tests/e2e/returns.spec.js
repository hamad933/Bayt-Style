import fs from 'node:fs';
import { test, expect } from '@playwright/test';

fs.mkdirSync('storage/test-artifacts/visual', { recursive: true });

const forbiddenCustomerTerms = [
    'S08', 'workstream', 'pending_payment', 'not_reserved', 'not_started',
    'policy_not_activated', 'checkout_session', 'disposition_decided',
    'authoritative_return_state', 'demo_unconfigured_zero',
];

async function createOrderAndOpenReturns(page) {
    await page.goto('/products/olive-velvet-lounge-chair');
    await page.locator('[data-option-key="color"][data-option-value="رملي"]').click();
    await expect(page.getByTestId('variant-sku')).toHaveText('BAS-CHAIR-SAND-01');
    await page.getByTestId('add-to-cart').click();

    await page.goto('/cart');
    await page.getByTestId('proceed-checkout').click();

    await page.getByLabel('الاسم الكامل').fill('عميل مرتجعات تجريبي');
    await page.getByLabel('البريد الإلكتروني').fill('returns-browser@example.test');
    await page.getByLabel('رقم الجوال').fill('+966500000008');
    await page.getByLabel('المنطقة / المحافظة').fill('الرياض');
    await page.getByLabel('المدينة').fill('الرياض');
    await page.getByLabel('الحي اختياري').fill('حي تجريبي');
    await page.getByLabel('الشارع / سطر العنوان').fill('شارع اختبار المرتجعات 8');
    await page.getByLabel('المبنى / الوحدة اختياري').fill('وحدة 8');
    await page.getByLabel('الرمز البريدي عند انطباقه').fill('00000');
    await page.locator('input[name="terms"]').check();
    await page.getByTestId('confirm-checkout').click();

    await expect(page).toHaveURL(/\/checkout\/confirmation\//);
    await page.getByRole('link', { name: 'عرض حالة الطلب' }).click();
    await expect(page).toHaveURL(/\/orders\/BAS-/);
    await page.getByTestId('open-returns').click();
    await expect(page).toHaveURL(/\/orders\/BAS-.*\/returns/);
    await expect(page.getByTestId('returns-page')).toBeVisible();
}

for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'tablet-820', width: 820, height: 1180 },
    { name: 'mobile-390', width: 390, height: 844 },
]) {
    test(`truthful returns and credit evidence ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await createOrderAndOpenReturns(page);

        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByTestId('return-eligibility-state')).toContainText('طلب المرتجع غير متاح حاليًا');
        await expect(page.getByTestId('return-eligibility-state')).toContainText('لن نفترض التسليم أو الأهلية');
        await expect(page.getByTestId('line-return-unavailable')).toBeVisible();
        await expect(page.getByTestId('return-cases-empty')).toContainText('لا توجد طلبات مرتجع مسجّلة');
        await expect(page.getByTestId('refund-empty')).toContainText('لا توجد عملية استرداد مسجّلة');
        await expect(page.getByTestId('store-credit-balance')).toContainText('0.00');
        await expect(page.getByTestId('store-credit-empty')).toContainText('لم يصدر رصيد متجر');
        await expect(page.locator('[data-testid="start-return"]')).toHaveCount(0);

        const body = await page.locator('body').innerText();
        for (const term of forbiddenCustomerTerms) expect(body).not.toContain(term);
        expect(body).not.toContain('اكتمل الاسترداد');
        expect(body).not.toContain('رسوم إعادة تخزين');
        expect(body).not.toContain('أيام عمل');
        expect(body).not.toContain('انتهاء الرصيد');
        expect(body).not.toContain('شارع اختبار المرتجعات 8');
        expect(body).not.toContain('+966500000008');
        expect(body).not.toContain('returns-browser@example.test');

        const hasOverflow = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        );
        expect(hasOverflow, `returns page must not overflow at ${viewport.width}px`).toBe(false);

        await page.screenshot({
            path: `storage/test-artifacts/visual/s08-returns-refunds-credit-${viewport.name}.png`,
            fullPage: true,
        });
    });
}
