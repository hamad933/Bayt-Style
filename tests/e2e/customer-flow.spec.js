import fs from 'node:fs';
import { test, expect } from '@playwright/test';

fs.mkdirSync('storage/test-artifacts/visual', { recursive: true });

test('home → catalog → Arabic search/filter → product → wishlist → quantity → cart', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('دفء المنزل يبدأ');

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
    await expect(galleryPanels.nth(0)).toBeVisible();

    const secondGalleryTab = page.getByRole('tab', { name: 'عرض الصورة 2' });
    await secondGalleryTab.click();
    await expect(secondGalleryTab).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('#product-media-1')).toBeVisible();
    await expect(page.locator('#product-media-0')).toBeHidden();

    const fourthGalleryTab = page.getByRole('tab', { name: 'عرض الصورة 4' });
    await fourthGalleryTab.click();
    await expect(fourthGalleryTab).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('#product-media-3')).toBeVisible();
    await expect(page.locator('#product-media-1')).toBeHidden();

    const wishlist = page.getByTestId('detail-wishlist');
    await wishlist.click();
    await expect(wishlist).toHaveAttribute('aria-pressed', 'true');

    await page.getByRole('button', { name: 'زيادة الكمية' }).click();
    await expect(page.getByTestId('quantity-value')).toHaveText('2');

    await page.getByTestId('add-to-cart').click();
    await expect(page.locator('.cart-badge')).toHaveText('2');

    await page.goto('/catalog');
    await expect(page.locator('.cart-badge')).toHaveText('2');
});

for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'tablet-820', width: 820, height: 1180 },
    { name: 'mobile-390', width: 390, height: 844 },
]) {
    test(`responsive visual verification ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        const routes = [
            ['s01-home', '/'],
            ['s02-catalog', '/catalog'],
            ['s03-product', '/products/olive-velvet-lounge-chair'],
        ];

        for (const [surface, route] of routes) {
            await page.goto(route);
            await expect(page.locator('main')).toBeVisible();
            const hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
            expect(hasOverflow, `${surface} must not overflow horizontally at ${viewport.width}px`).toBe(false);
            await page.screenshot({
                path: `storage/test-artifacts/visual/${surface}-${viewport.name}.png`,
                fullPage: true,
            });
        }
    });
}
