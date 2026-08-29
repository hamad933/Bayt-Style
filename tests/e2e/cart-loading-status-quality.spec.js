import { mkdir } from 'node:fs/promises';
import { expect, test } from '@playwright/test';

function watchRuntime(page) {
    const failures = [];
    page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
        if (message.type() === 'error') failures.push(`console.error: ${message.text()}`);
    });
    page.on('response', (response) => {
        if (response.status() >= 500) failures.push(`HTTP ${response.status()}: ${response.url()}`);
    });
    return failures;
}

test('[QUALITY][A11Y] mini-cart loading is a visible polite status message', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const runtimeFailures = watchRuntime(page);
    let releaseSummary;
    const summaryGate = new Promise((resolve) => { releaseSummary = resolve; });

    await page.route('**/cart/summary', async (route) => {
        await summaryGate;
        await route.continue();
    });

    await page.goto('/catalog');
    const cartButton = page.getByRole('button', { name: 'فتح السلة' });
    await expect(cartButton).toBeVisible();
    await cartButton.click();

    const drawerBackdrop = page.locator('.drawer-backdrop');
    const drawer = page.getByRole('dialog', { name: 'مختاراتك الحالية' });
    await expect(drawer).toBeVisible();
    await expect.poll(() => drawerBackdrop.evaluate((element) => getComputedStyle(element).opacity)).toBe('1');

    const status = drawer.getByRole('status');
    await expect(status).toBeVisible();
    await expect(status).toHaveText('جارٍ تحديث السلة…');
    await expect(status).toHaveAttribute('aria-live', 'polite');
    await expect(status).toHaveAttribute('aria-atomic', 'true');

    const box = await status.boundingBox();
    expect(box).not.toBeNull();
    expect(box.y).toBeGreaterThanOrEqual(-1);
    expect(box.y + box.height).toBeLessThanOrEqual(845);

    await mkdir('storage/test-artifacts/cart-loading-status-quality', { recursive: true });
    await page.screenshot({
        path: 'storage/test-artifacts/cart-loading-status-quality/mini-cart-loading-status-390.png',
        fullPage: false,
    });

    releaseSummary();
    await expect(status).toHaveCount(0);
    expect(runtimeFailures).toEqual([]);
});
