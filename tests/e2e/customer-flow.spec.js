import fs from 'node:fs';
import { test, expect } from '@playwright/test';

fs.mkdirSync('storage/test-artifacts/visual', { recursive: true });

const forbiddenCustomerTerms = [
    'S06', 'S07', 'workstream', 'Variant', 'Checkout', 'SLA',
    'demo_unconfigured_zero', 'manual_pending_demo', 'rp01-s06-development-consent-v1',
    'pending_payment', 'not_reserved', 'not_started',
];

async function expectCustomerCopyClean(page) {
    const visibleText = await page.locator('body').innerText();
    for (const term of forbiddenCustomerTerms) expect(visibleText).not.toContain(term);
}

async function chooseSandChair(page, quantity = 1) {
    await page.goto('/products/olive-velvet-lounge-chair');
    const colorSand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
    // The server-rendered control can be visible before Alpine has bound its
    // reactive aria/click state. Wait for hydration before interacting.
    await expect(colorSand).toHaveAttribute('aria-pressed', 'false');
    await colorSand.click();
    await expect(colorSand).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByTestId('variant-sku')).toHaveText('BAS-CHAIR-SAND-01');
    for (let i = 1; i < quantity; i += 1) {
        await page.getByRole('button', { name: 'زيادة الكمية' }).click();
    }
    await page.getByTestId('add-to-cart').click();
    await expect(page.locator('.cart-badge')).toHaveText(String(quantity));
}

async function fillCheckout(page) {
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
}

test('S01 → S06 critical customer flow preserves exact Variant and pending boundaries', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('دفء المنزل يبدأ');
    await page.keyboard.press('Tab');
    await expect(page.locator('.skip-link')).toBeFocused();
    await page.getByRole('link', { name: 'تسوق التشكيلة' }).click();
    await expect(page).toHaveURL(/\/catalog/);
    await page.getByTestId('catalog-search').fill('كرسي');
    await page.getByTestId('catalog-search').press('Enter');
    await expect(page.getByText('كرسي استرخاء مخملي', { exact: true })).toBeVisible();
    await page.getByText('كرسي استرخاء مخملي', { exact: true }).first().click();
    await expect(page).toHaveURL(/\/products\/olive-velvet-lounge-chair/);
    const galleryPanels = page.locator('.gallery-main img[role="tabpanel"]');
    await expect(galleryPanels).toHaveCount(4);
    await expect(galleryPanels.nth(0)).toHaveAttribute('src', /chair-main\.jpg$/);
    const colorSand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
    // Wait for Alpine hydration before clicking so the test exercises the real
    // reactive control rather than racing the server-rendered HTML.
    await expect(colorSand).toHaveAttribute('aria-pressed', 'false');
    await colorSand.click();
    await expect(colorSand).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByTestId('variant-sku')).toHaveText('BAS-CHAIR-SAND-01');
    await expect(page.getByTestId('variant-price')).toContainText('2,050');
    const unavailableFinish = page.locator('[data-option-key="finish"][data-option-value="بلوط طبيعي"]');
    await expect(unavailableFinish).toBeDisabled();
    const wishlist = page.getByTestId('detail-wishlist');
    await wishlist.click();
    await expect(wishlist).toHaveAttribute('aria-pressed', 'true');
    await page.getByRole('button', { name: 'زيادة الكمية' }).click();
    await expect(page.getByTestId('quantity-value')).toHaveText('2');
    await page.getByTestId('add-to-cart').click();
    await expect(page.locator('.cart-badge')).toHaveText('2');
    await page.getByRole('button', { name: 'فتح السلة' }).click();
    await expect(page.locator('#cart-drawer')).toContainText('BAS-CHAIR-SAND-01');
    await expect(page.locator('#cart-drawer')).toContainText('مخمل رملي · جوزي داكن');
    await page.getByRole('link', { name: 'عرض السلة وإتمام الطلب' }).click();
    await expect(page).toHaveURL(/\/cart$/);
    await expect(page.getByTestId('cart-line')).toContainText('BAS-CHAIR-SAND-01');
    await expect(page.getByTestId('cart-subtotal')).toContainText('4,100');
    await expectCustomerCopyClean(page);
    const quantity = page.locator('input[name="quantity"]');
    await quantity.fill('3');
    await page.getByRole('button', { name: 'تحديث' }).click();
    await expect(page).toHaveURL(/\/cart$/);
    await expect(page.getByTestId('cart-subtotal')).toContainText('6,150');
    await page.getByTestId('proceed-checkout').click();
    await expect(page).toHaveURL(/\/checkout$/);
    await expect(page.getByTestId('checkout-page')).toContainText('BAS-CHAIR-SAND-01');
    await expect(page.getByTestId('checkout-total')).toContainText('6,185');
    await expect(page.getByText('الدفع غير مكتمل بعد')).toBeVisible();
    await expectCustomerCopyClean(page);
    await fillCheckout(page);
    await page.getByTestId('confirm-checkout').click();
    await expect(page).toHaveURL(/\/checkout\/confirmation\//);
    await expect(page.getByTestId('order-reference')).toContainText('BAS-');
    await expect(page.getByTestId('payment-state')).toHaveText('الدفع لم يكتمل بعد');
    await expect(page.getByTestId('reservation-state')).toHaveText('المخزون غير محجوز حتى الآن');
    await expect(page.getByTestId('confirmation-total')).toContainText('6,185');
    await expect(page.getByText('تم الدفع بنجاح')).toHaveCount(0);
    await expect(page.getByText('تم حجز المخزون')).toHaveCount(0);
    await expectCustomerCopyClean(page);
});

test('S05 wishlist and comparison remain functional after S06 integration', async ({ page }) => {
    await page.goto('/catalog');
    const wishlistButton = page.getByTestId('wishlist-toggle').first();
    await wishlistButton.click();
    await expect(wishlistButton).toHaveAttribute('aria-pressed', 'true');
    await page.goto('/wishlist');
    await expect(page.getByTestId('wishlist-list')).toBeVisible();
    await page.goto('/catalog');
    const comparisonButtons = page.getByTestId('comparison-toggle');
    await expect(comparisonButtons).toHaveCount(6);
    for (let index = 0; index < 3; index += 1) {
        await comparisonButtons.nth(index).click();
        await expect(comparisonButtons.nth(index)).toHaveAttribute('aria-pressed', 'true');
    }
    await page.goto('/comparison');
    await expect(page.getByTestId('comparison-grid').locator('.comparison-item')).toHaveCount(3);
});

test('single-Variant quick add remains exact and multi-Variant product still requires configuration', async ({ page }) => {
    await page.goto('/');
    const chairCard = page.getByTestId('product-card').filter({ hasText: 'كرسي استرخاء مخملي' });
    await expect(chairCard.getByRole('link', { name: 'اختر الخيارات' })).toBeVisible();
    await expect(chairCard.getByRole('button', { name: 'أضف إلى السلة' })).toHaveCount(0);
    const lampCard = page.getByTestId('product-card').filter({ hasText: 'مصباح طاولة سيراميك' });
    await lampCard.getByRole('button', { name: 'أضف إلى السلة' }).click();
    await expect(page.locator('.cart-badge')).toHaveText('1');
    await page.getByRole('button', { name: 'فتح السلة' }).click();
    await expect(page.locator('#cart-drawer')).toContainText('BAS-LAMP-CER-01');
});

for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'tablet-820', width: 820, height: 1180 },
    { name: 'mobile-390', width: 390, height: 844 },
]) {
    test(`responsive S01-S06 evidence ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        for (const [surface, route] of [
            ['s01-home', '/'], ['s02-catalog', '/catalog'], ['s03-s04-product', '/products/olive-velvet-lounge-chair'],
            ['s05-wishlist', '/wishlist'], ['s05-comparison', '/comparison'],
        ]) {
            await page.goto(route);
            await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
            const hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
            expect(hasOverflow, `${surface} must not overflow at ${viewport.width}px`).toBe(false);
            await page.screenshot({ path: `storage/test-artifacts/visual/${surface}-${viewport.name}.png`, fullPage: true });
        }
        await chooseSandChair(page, 1);
        await page.goto('/cart');
        await expect(page.getByTestId('cart-page')).toBeVisible();
        await expectCustomerCopyClean(page);
        let hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
        expect(hasOverflow, `s06-cart must not overflow at ${viewport.width}px`).toBe(false);
        await page.screenshot({ path: `storage/test-artifacts/visual/s06-cart-${viewport.name}.png`, fullPage: true });
        await page.getByTestId('proceed-checkout').click();
        await expect(page.getByTestId('checkout-page')).toBeVisible();
        await expectCustomerCopyClean(page);
        await fillCheckout(page);
        hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
        expect(hasOverflow, `s06-checkout must not overflow at ${viewport.width}px`).toBe(false);
        await page.screenshot({ path: `storage/test-artifacts/visual/s06-checkout-${viewport.name}.png`, fullPage: true });
        await page.getByTestId('confirm-checkout').click();
        await expect(page.getByTestId('confirmation-page')).toBeVisible();
        await expect(page.getByTestId('payment-state')).toHaveText('الدفع لم يكتمل بعد');
        await expect(page.getByTestId('reservation-state')).toHaveText('المخزون غير محجوز حتى الآن');
        await expectCustomerCopyClean(page);
        hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
        expect(hasOverflow, `s06-confirmation must not overflow at ${viewport.width}px`).toBe(false);
        await page.screenshot({ path: `storage/test-artifacts/visual/s06-confirmation-${viewport.name}.png`, fullPage: true });
    });
}
