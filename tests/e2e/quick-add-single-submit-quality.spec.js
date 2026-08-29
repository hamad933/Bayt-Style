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

    await quickAdd.dispatchEvent('click');

    await expect.poll(() => cartRequests).toBe(1);
    await expect(quickAdd).toBeEnabled();
    await expect(quickAdd).toHaveText('أضف إلى السلة');
    await expect(quickAdd).toHaveAttribute('aria-busy', 'false');

    expect(pageErrors).toEqual([]);
    expect(consoleErrors).toEqual([]);
    expect(serverErrors).toEqual([]);
});
