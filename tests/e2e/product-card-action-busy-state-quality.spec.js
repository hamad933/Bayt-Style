import { test, expect } from '@playwright/test';

function collectRuntimeFailures(page) {
    const pageErrors = [];
    const consoleErrors = [];
    const serverErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push(message.text());
    });
    page.on('response', (response) => {
        if (response.status() >= 500) serverErrors.push(`${response.status()} ${response.url()}`);
    });
    return { pageErrors, consoleErrors, serverErrors };
}

test('product-card wishlist exposes a visible truthful busy state and suppresses duplicate toggles', async ({ page }) => {
    const failures = collectRuntimeFailures(page);
    let requests = 0;

    await page.route('**/wishlist/*/toggle', async (route) => {
        requests += 1;
        await new Promise((resolve) => setTimeout(resolve, 500));
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ saved: true, count: 1 }) });
    });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/catalog');

    const wishlist = page.getByTestId('wishlist-toggle').first();
    await expect(wishlist).toBeVisible();
    await expect(wishlist).toBeEnabled();
    await expect(wishlist).toHaveAttribute('aria-busy', 'false');

    await wishlist.click();
    await expect(wishlist).toBeDisabled();
    await expect(wishlist).toHaveAttribute('aria-busy', 'true');
    await expect(wishlist).toHaveAccessibleName(/جارٍ تحديث مفضلة/);
    await expect(wishlist).toContainText('…');
    await wishlist.scrollIntoViewIfNeeded();
    await page.screenshot({ path: 'storage/test-artifacts/product-card-wishlist-busy-390.png', fullPage: false });

    await wishlist.dispatchEvent('click');
    await expect.poll(() => requests).toBe(1);
    await expect(wishlist).toBeEnabled();
    await expect(wishlist).toHaveAttribute('aria-busy', 'false');
    await expect(wishlist).toHaveAttribute('aria-pressed', 'true');

    expect(failures.pageErrors).toEqual([]);
    expect(failures.consoleErrors).toEqual([]);
    expect(failures.serverErrors).toEqual([]);
});

test('product-card comparison exposes a visible truthful busy state and suppresses duplicate toggles', async ({ page }) => {
    const failures = collectRuntimeFailures(page);
    let requests = 0;

    await page.route('**/comparison/*', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        requests += 1;
        await new Promise((resolve) => setTimeout(resolve, 500));
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ count: 1, limit: 3, already_present: false }) });
    });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/catalog');

    const comparison = page.getByTestId('comparison-toggle').first();
    await expect(comparison).toBeVisible();
    await expect(comparison).toBeEnabled();
    await expect(comparison).toHaveAttribute('aria-busy', 'false');

    await comparison.click();
    await expect(comparison).toBeDisabled();
    await expect(comparison).toHaveAttribute('aria-busy', 'true');
    await expect(comparison).toContainText('جارٍ التحديث…');
    await comparison.scrollIntoViewIfNeeded();
    await page.screenshot({ path: 'storage/test-artifacts/product-card-comparison-busy-390.png', fullPage: false });

    await comparison.dispatchEvent('click');
    await expect.poll(() => requests).toBe(1);
    await expect(comparison).toBeEnabled();
    await expect(comparison).toHaveAttribute('aria-busy', 'false');
    await expect(comparison).toHaveAttribute('aria-pressed', 'true');

    expect(failures.pageErrors).toEqual([]);
    expect(failures.consoleErrors).toEqual([]);
    expect(failures.serverErrors).toEqual([]);
});
