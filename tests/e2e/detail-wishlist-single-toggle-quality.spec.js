import { test, expect } from '@playwright/test';

test('detail wishlist exposes a truthful busy state and suppresses duplicate toggles', async ({ page }) => {
    const pageErrors = [];
    const consoleErrors = [];
    const serverErrors = [];
    let wishlistRequests = 0;

    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push(message.text());
    });
    page.on('response', (response) => {
        if (response.status() >= 500) serverErrors.push(`${response.status()} ${response.url()}`);
    });

    await page.route('**/wishlist/*/toggle', async (route) => {
        wishlistRequests += 1;
        await new Promise((resolve) => setTimeout(resolve, 500));
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ saved: true, count: 1 }),
        });
    });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/products/olive-velvet-lounge-chair');

    const wishlist = page.getByTestId('detail-wishlist');
    await expect(wishlist).toBeVisible();
    await expect(wishlist).toBeEnabled();
    await expect(wishlist).toHaveAttribute('aria-busy', 'false');

    await wishlist.click();
    await expect(wishlist).toBeDisabled();
    await expect(wishlist).toHaveAttribute('aria-busy', 'true');
    await expect(wishlist).toContainText('جارٍ التحديث…');
    await wishlist.scrollIntoViewIfNeeded();
    await page.screenshot({ path: 'storage/test-artifacts/detail-wishlist-busy-390.png', fullPage: false });

    await wishlist.dispatchEvent('click');

    await expect.poll(() => wishlistRequests).toBe(1);
    await expect(wishlist).toBeEnabled();
    await expect(wishlist).toHaveAttribute('aria-busy', 'false');
    await expect(wishlist).toHaveAttribute('aria-pressed', 'true');
    await expect(wishlist).toContainText('محفوظ في المفضلة');

    expect(pageErrors).toEqual([]);
    expect(consoleErrors).toEqual([]);
    expect(serverErrors).toEqual([]);
});
