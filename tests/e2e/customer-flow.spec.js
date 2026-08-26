import { expect, test } from '@playwright/test';

async function fillCheckout(page) {
    await page.getByLabel('الاسم الأول').fill('مها');
    await page.getByLabel('اسم العائلة').fill('العمري');
    await page.getByLabel('البريد الإلكتروني').fill('maha@example.test');
    await page.getByLabel('رقم الجوال').fill('0500000000');
    await page.getByLabel('الدولة / السوق').selectOption('SA');
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
    // The server-rendered button is visible before Alpine has bound its dynamic
    // aria/click state. Wait for the initial reactive value so the following
    // click verifies real product behavior rather than racing hydration.
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
    await page.getByRole('link', { name: 'متابعة الدفع' }).click();
    await expect(page).toHaveURL(/\/checkout/);
    await expect(page.getByText('السلة لا تحجز المخزون')).toBeVisible();
    await fillCheckout(page);
    await page.getByRole('button', { name: 'تأكيد الطلب' }).click();
    await expect(page).toHaveURL(/\/checkout\/confirmation\/BAS-/);
    await expect(page.getByText('بانتظار الدفع')).toBeVisible();
    await expect(page.getByText('لم يتم حجز المخزون بعد')).toBeVisible();
    await expect(page.getByText('لم يبدأ التنفيذ بعد')).toBeVisible();
});

test('S05 wishlist and comparison remain functional after S06 integration', async ({ page }) => {
    await page.goto('/catalog');
    const card = page.locator('[data-testid="product-card"]').first();
    const wishlist = card.getByTestId('wishlist-toggle');
    await wishlist.click();
    await expect(wishlist).toHaveAttribute('aria-pressed', 'true');

    const comparison = card.getByTestId('comparison-toggle');
    await comparison.click();
    await expect(comparison).toHaveAttribute('aria-pressed', 'true');
    await page.getByRole('link', { name: /المقارنة/ }).click();
    await expect(page).toHaveURL(/\/comparison/);
    await expect(page.locator('.comparison-card')).toHaveCount(1);
});

test('single-Variant quick add remains exact and multi-Variant product still requires configuration', async ({ page }) => {
    await page.goto('/catalog');
    const singleVariantCard = page.locator('[data-testid="product-card"]').filter({ hasText: 'مصباح طاولة سيراميك' });
    await singleVariantCard.getByTestId('quick-add').click();
    await expect(page.locator('.cart-badge')).toHaveText('1');

    const multiVariantCard = page.locator('[data-testid="product-card"]').filter({ hasText: 'كرسي استرخاء مخملي' });
    await expect(multiVariantCard.getByTestId('configure-product')).toBeVisible();
    await expect(multiVariantCard.getByTestId('quick-add')).toHaveCount(0);
});

for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'tablet-820', width: 820, height: 1000 },
    { name: 'mobile-390', width: 390, height: 844 },
]) {
    test(`responsive S01-S06 evidence ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await page.goto('/');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await page.goto('/catalog');
        await expect(page.getByTestId('catalog-search')).toBeVisible();
        await page.goto('/products/olive-velvet-lounge-chair');
        await expect(page.getByTestId('product-title')).toBeVisible();
        await expect(page.getByTestId('add-to-cart')).toBeVisible();
        await page.goto('/checkout');
        await expect(page.getByRole('heading', { name: 'إتمام الطلب' })).toBeVisible();
    });
}
