import { test, expect } from '@playwright/test';

test('PDP add-to-cart exposes a visible live busy state and suppresses duplicate requests', async ({ page }) => {
    const pageErrors = [];
    const consoleErrors = [];
    const serverErrors = [];
    let cartRequests = 0;

    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push(message.text());
    });
    page.on('response', (response) => {
        if (response.status() >= 500) serverErrors.push(`${response.status()} ${response.url()}`);
    });

    await page.route('**/cart/items', async (route) => {
        cartRequests += 1;
        await new Promise((resolve) => setTimeout(resolve, 500));
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ count: 1, total: '1790', items: [] }),
        });
    });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/products/olive-velvet-lounge-chair');

    const addToCart = page.getByTestId('add-to-cart');
    const busyStatus = addToCart.locator('[role="status"]');
    await expect(addToCart).toBeVisible();
    await expect(addToCart).toBeEnabled();
    await expect(addToCart).toHaveAttribute('aria-busy', 'false');

    await addToCart.click();
    await expect(addToCart).toBeDisabled();
    await expect(addToCart).toHaveAttribute('aria-busy', 'true');
    await expect(busyStatus).toBeVisible();
    await expect(busyStatus).toHaveText('جارٍ الإضافة…');
    await expect(busyStatus).toHaveAttribute('aria-live', 'polite');
    await addToCart.scrollIntoViewIfNeeded();

    const busyBox = await busyStatus.boundingBox();
    expect(busyBox).not.toBeNull();
    expect(busyBox.x).toBeGreaterThanOrEqual(0);
    expect(busyBox.y).toBeGreaterThanOrEqual(0);
    expect(busyBox.x + busyBox.width).toBeLessThanOrEqual(390);
    expect(busyBox.y + busyBox.height).toBeLessThanOrEqual(844);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

    await page.screenshot({ path: 'storage/test-artifacts/pdp-add-to-cart-busy-390.png', fullPage: false });

    await addToCart.dispatchEvent('click');
    await expect.poll(() => cartRequests).toBe(1);
    await expect(addToCart).toBeEnabled();
    await expect(addToCart).toHaveAttribute('aria-busy', 'false');
    await expect(busyStatus).toBeHidden();

    expect(pageErrors).toEqual([]);
    expect(consoleErrors).toEqual([]);
    expect(serverErrors).toEqual([]);
});
