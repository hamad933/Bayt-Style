import fs from 'node:fs';
import { test, expect } from '@playwright/test';

fs.mkdirSync('storage/test-artifacts/visual', { recursive: true });

async function createOrderAndOpenStatus(page) {
    await page.goto('/products/olive-velvet-lounge-chair');
    const colorSand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
    await expect(colorSand).toHaveAttribute('aria-pressed', 'false');
    await colorSand.click();
    await expect(colorSand).toHaveAttribute('aria-pressed', 'true');
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
    await page.getByRole('link', { name: 'عرض حالة الطلب' }).click();
    await expect(page.getByTestId('order-status-page')).toBeVisible();
}

async function expectInsideInitialViewport(locator, viewport) {
    await expect(locator).toBeVisible();
    const box = await locator.boundingBox();
    expect(box).not.toBeNull();
    expect(box.y).toBeGreaterThanOrEqual(0);
    expect(box.y + box.height).toBeLessThanOrEqual(viewport.height + 1);
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + 1);
}

for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'mobile-390', width: 390, height: 844 },
]) {
    test(`S07 order-status truth is visible in the initial viewport ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });

        const pageErrors = [];
        const consoleErrors = [];
        const serverFailures = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));
        page.on('console', (message) => {
            if (message.type() === 'error') consoleErrors.push(message.text());
        });
        page.on('response', (response) => {
            if (response.status() >= 500) serverFailures.push(`${response.status()} ${response.url()}`);
        });

        await createOrderAndOpenStatus(page);

        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expectInsideInitialViewport(page.getByTestId('overall-order-state'), viewport);
        await expect(page.getByTestId('overall-order-state')).toHaveText('تم استلام طلبك');
        await expectInsideInitialViewport(page.getByTestId('status-payment'), viewport);
        await expect(page.getByTestId('status-payment')).toHaveText('الدفع لم يكتمل بعد');

        const hasOverflow = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        );
        expect(hasOverflow).toBe(false);
        expect(pageErrors).toEqual([]);
        expect(consoleErrors).toEqual([]);
        expect(serverFailures).toEqual([]);

        await page.screenshot({
            path: `storage/test-artifacts/visual/s07-order-status-initial-${viewport.name}.png`,
            fullPage: false,
        });
    });
}
