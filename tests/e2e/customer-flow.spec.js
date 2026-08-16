import fs from 'node:fs';
import { test, expect } from '@playwright/test';

fs.mkdirSync('storage/test-artifacts/visual', { recursive: true });

test('S01 → S05 critical customer flow with exact Variant cart semantics', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('دفء المنزل يبدأ');
    await page.keyboard.press('Tab');
    await expect(page.locator('.skip-link')).toBeFocused();
    await expect(page.locator('.skip-link')).toHaveCSS('transform', 'none');
    await page.getByRole('link', { name: 'تسوق التشكيلة' }).click();
    await expect(page).toHaveURL(/\/catalog/);
    await page.getByTestId('catalog-search').fill('كرسي');
    await page.getByTestId('catalog-search').press('Enter');
    await expect(page.getByText('كرسي استرخاء مخملي', { exact: true })).toBeVisible();
    await page.locator('#desktop-category').selectOption('seating');
    await page.locator('.desktop-filters').getByTestId('apply-filters').click();
    await expect(page).toHaveURL(/category=seating/);
    await page.getByText('كرسي استرخاء مخملي', { exact: true }).first().click();
    await expect(page).toHaveURL(/\/products\/olive-velvet-lounge-chair/);
    const galleryPanels = page.locator('.gallery-main img[role="tabpanel"]');
    await expect(galleryPanels).toHaveCount(4);
    const gallerySources = await galleryPanels.evaluateAll((images) => images.map((image) => new URL(image.src).pathname));
    expect(new Set(gallerySources).size).toBe(4);
    await expect(galleryPanels.nth(0)).toHaveAttribute('src', /chair-main\.jpg$/);
    await expect(galleryPanels.nth(1)).toHaveAttribute('src', /chair-detail-side\.jpg$/);
    await expect(galleryPanels.nth(2)).toHaveAttribute('src', /chair-detail-seat\.jpg$/);
    await expect(galleryPanels.nth(3)).toHaveAttribute('src', /chair-detail-back\.jpg$/);
    const secondGalleryTab = page.getByRole('tab', { name: 'عرض الصورة 2' });
    await secondGalleryTab.click();
    await expect(secondGalleryTab).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('#product-media-1')).toBeVisible();
    const colorSand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
    await colorSand.focus();
    await colorSand.press('Enter');
    await expect(colorSand).toBeFocused();
    await expect(colorSand).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByTestId('variant-sku')).toHaveText('BAS-CHAIR-SAND-01');
    await expect(page.getByTestId('variant-price')).toContainText('2,050');
    await expect(page.getByTestId('variant-availability')).toContainText('متاح');
    const unavailableFinish = page.locator('[data-option-key="finish"][data-option-value="بلوط طبيعي"]');
    await expect(unavailableFinish).toBeDisabled();
    await expect(unavailableFinish).toHaveAttribute('title', /غير متاح/);
    const wishlist = page.getByTestId('detail-wishlist');
    await wishlist.click();
    await expect(wishlist).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('a[aria-label="المفضلة"] .commerce-count')).toHaveText('1');
    await page.getByRole('link', { name: 'المفضلة' }).first().click();
    await expect(page).toHaveURL(/\/wishlist$/);
    await expect(page.getByTestId('wishlist-list')).toContainText('كرسي استرخاء مخملي');
    const wishlistCompare = page.getByTestId('wishlist-comparison-toggle');
    await wishlistCompare.click();
    await expect(wishlistCompare).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('a[aria-label="المقارنة"] .commerce-count')).toHaveText('1');
    await page.getByRole('link', { name: 'المقارنة' }).first().click();
    await expect(page).toHaveURL(/\/comparison$/);
    await expect(page.getByTestId('comparison-grid')).toContainText('كرسي استرخاء مخملي');
    await expect(page.getByTestId('comparison-grid')).toContainText('مخمل');
    await expect(page.getByTestId('comparison-grid')).toContainText('1,950');
    await page.getByTestId('comparison-remove').click();
    await expect(page.getByTestId('comparison-empty')).toBeVisible();
    await page.goto('/products/olive-velvet-lounge-chair');
    await expect(page.getByTestId('detail-wishlist')).toContainText('محفوظ في المفضلة');
    const cartColorSand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
    await cartColorSand.click();
    await expect(page.getByTestId('variant-sku')).toHaveText('BAS-CHAIR-SAND-01');
    await page.getByRole('button', { name: 'زيادة الكمية' }).click();
    await expect(page.getByTestId('quantity-value')).toHaveText('2');
    await page.getByTestId('add-to-cart').click();
    await expect(page.locator('.cart-badge')).toHaveText('2');
    await page.getByRole('button', { name: 'فتح السلة' }).click();
    await expect(page.locator('#cart-drawer')).toContainText('BAS-CHAIR-SAND-01');
    await expect(page.locator('#cart-drawer')).toContainText('مخمل رملي · جوزي داكن');
    await page.keyboard.press('Escape');
    await page.goto('/catalog');
    await expect(page.locator('.cart-badge')).toHaveText('2');
});

test('Home quick-add requires configuration for multi-Variant products and remains exact for single-Variant products', async ({ page }) => {
    await page.goto('/');
    const chairCard = page.getByTestId('product-card').filter({ hasText: 'كرسي استرخاء مخملي' });
    await expect(chairCard.getByRole('link', { name: 'اختر الخيارات' })).toBeVisible();
    await expect(chairCard.getByRole('button', { name: 'أضف إلى السلة' })).toHaveCount(0);
    const lampCard = page.getByTestId('product-card').filter({ hasText: 'مصباح طاولة سيراميك' });
    const lampQuickAdd = lampCard.getByRole('button', { name: 'أضف إلى السلة' });
    await expect(lampQuickAdd).toBeVisible();
    await lampQuickAdd.click();
    await expect(page.locator('.cart-badge')).toHaveText('1');
    await page.getByRole('button', { name: 'فتح السلة' }).click();
    await expect(page.locator('#cart-drawer')).toContainText('BAS-LAMP-CER-01');
});

for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'tablet-820', width: 820, height: 1180 },
    { name: 'mobile-390', width: 390, height: 844 },
]) {
    test(`responsive S01-S05 verification ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        const routes = [['s01-home', '/'], ['s02-catalog', '/catalog'], ['s03-s04-product', '/products/olive-velvet-lounge-chair']];
        for (const [surface, route] of routes) {
            await page.goto(route);
            await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
            await expect(page.locator('main')).toBeVisible();
            const hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
            expect(hasOverflow, `${surface} must not overflow horizontally at ${viewport.width}px`).toBe(false);
            await page.screenshot({ path: `storage/test-artifacts/visual/${surface}-${viewport.name}.png`, fullPage: true });
        }
        await page.goto('/catalog');
        await page.getByTestId('wishlist-toggle').first().click();
        await page.goto('/wishlist');
        await expect(page.getByTestId('wishlist-list')).toBeVisible();
        let hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
        expect(hasOverflow, `s05-wishlist must not overflow horizontally at ${viewport.width}px`).toBe(false);
        await page.screenshot({ path: `storage/test-artifacts/visual/s05-wishlist-${viewport.name}.png`, fullPage: true });
        await page.goto('/catalog');
        const comparisonButtons = page.getByTestId('comparison-toggle');
        await expect(comparisonButtons).toHaveCount(6);
        for (let index = 0; index < 3; index += 1) {
            await comparisonButtons.nth(index).click();
            await expect(comparisonButtons.nth(index)).toHaveAttribute('aria-pressed', 'true');
        }
        await page.goto('/comparison');
        await expect(page.getByTestId('comparison-grid').locator('.comparison-item')).toHaveCount(3);
        hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
        expect(hasOverflow, `s05-comparison must not overflow horizontally at ${viewport.width}px`).toBe(false);
        await page.screenshot({ path: `storage/test-artifacts/visual/s05-comparison-${viewport.name}.png`, fullPage: true });
    });
}
