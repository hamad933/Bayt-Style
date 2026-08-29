import { test, expect } from '@playwright/test';

test('quick add exposes a truthful busy state and suppresses duplicate submissions', async ({ page }) => {
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
        await route.continue();
    });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');

    const quickAdd = page.locator('[data-testid^="quick-add-"]').first();
    await expect(quickAdd).toBeVisible();
    await expect(quickAdd).toBeEnabled();
    await expect(quickAdd).toHaveText('أضف إلى السلة');
    await expect(quickAdd).toHaveAttribute('aria-busy', 'false');

    await quickAdd.click();
    await expect(quickAdd).toBeDisabled();
    await expect(quickAdd).toHaveText('جارٍ الإضافة…');
    await expect(quickAdd).toHaveAttribute('aria-busy', 'true');
    await quickAdd.scrollIntoViewIfNeeded();
    await page.screenshot({ path: 'storage/test-artifacts/quick-add-busy-390.png', fullPage: false });

    await quickAdd.dispatchEvent('click');

    await expect.poll(() => cartRequests).toBe(1);
    await expect(quickAdd).toBeEnabled();
    await expect(quickAdd).toHaveText('أضف إلى السلة');
    await expect(quickAdd).toHaveAttribute('aria-busy', 'false');

    expect(pageErrors).toEqual([]);
    expect(consoleErrors).toEqual([]);
    expect(serverErrors).toEqual([]);
});

test('quick add recovers from an expected request rejection and shows the user the error', async ({ page }) => {
    const pageErrors = [];
    const consoleErrors = [];

    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push(message.text());
    });

    await page.route('**/cart/items', async (route) => {
        await route.fulfill({
            status: 422,
            contentType: 'application/json',
            body: JSON.stringify({ message: 'تعذر إضافة القطعة الآن.' }),
        });
    });

    await page.goto('/');

    const quickAdd = page.locator('[data-testid^="quick-add-"]').first();
    await expect(quickAdd).toBeVisible();
    await quickAdd.click();

    await expect(page.locator('.toast')).toBeVisible();
    await expect(page.locator('.toast')).toHaveText('تعذر إضافة القطعة الآن.');
    await expect(quickAdd).toBeEnabled();
    await expect(quickAdd).toHaveText('أضف إلى السلة');
    await expect(quickAdd).toHaveAttribute('aria-busy', 'false');

    const unexpectedConsoleErrors = consoleErrors.filter(
        (message) => !message.includes('Failed to load resource: the server responded with a status of 422'),
    );

    expect(pageErrors).toEqual([]);
    expect(unexpectedConsoleErrors).toEqual([]);
});
