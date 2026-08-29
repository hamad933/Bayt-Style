import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/toast-live-region-quality');
fs.mkdirSync(outputDir, { recursive: true });

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

async function expectInsideViewport(locator, viewport) {
    const box = await locator.boundingBox();
    expect(box).not.toBeNull();
    expect(box.x).toBeGreaterThanOrEqual(-1);
    expect(box.y).toBeGreaterThanOrEqual(-1);
    expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + 1);
    expect(box.y + box.height).toBeLessThanOrEqual(viewport.height + 1);
}

for (const viewport of [
    { width: 1440, height: 1000, suffix: '1440' },
    { width: 390, height: 844, suffix: '390' },
]) {
    test(`[QUALITY][A11Y] action feedback is visible and exposed through the polite live region at ${viewport.width}px`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        const runtimeFailures = watchRuntime(page);
        await page.goto('/catalog');

        const region = page.locator('.toast-region');
        await expect(region).toHaveAttribute('aria-live', 'polite');
        await expect(region).toHaveAttribute('aria-atomic', 'true');

        const wishlistButton = page.getByTestId('wishlist-toggle').first();
        await expect(wishlistButton).toBeVisible();
        await wishlistButton.click();
        await expect(wishlistButton).toHaveAttribute('aria-pressed', 'true');

        const toast = region.locator('.toast');
        await expect(toast).toBeVisible();
        await expect(toast).toHaveText('تم حفظ القطعة في المفضلة.');
        await expectInsideViewport(toast, viewport);

        await page.screenshot({
            path: path.join(outputDir, `wishlist-toast-${viewport.suffix}.png`),
            fullPage: false,
        });

        const documentWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        expect(documentWidth).toBeLessThanOrEqual(viewport.width + 1);
        expect(runtimeFailures).toEqual([]);
    });
}
