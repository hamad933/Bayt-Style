import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/product-gallery-keyboard-quality');
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

test('[QUALITY][A11Y] product gallery arrow navigation keeps selection and focus aligned', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    const runtimeFailures = watchRuntime(page);
    await page.goto('/products/olive-velvet-lounge-chair');

    const tabs = page.locator('.gallery-thumbs [role="tab"]');
    await expect(tabs).toHaveCount(4);

    const first = tabs.nth(0);
    const second = tabs.nth(1);
    const last = tabs.nth(3);

    await first.focus();
    await expect(first).toBeFocused();
    await expect(first).toHaveAttribute('aria-selected', 'true');
    await expect(first).toHaveAttribute('tabindex', '0');

    await first.press('ArrowLeft');
    await expect(second).toHaveAttribute('aria-selected', 'true');
    await expect(second).toHaveAttribute('tabindex', '0');
    await expect(second).toBeFocused();
    await expect(first).toHaveAttribute('aria-selected', 'false');
    await expect(first).toHaveAttribute('tabindex', '-1');
    await expect(page.locator('#product-media-1')).toBeVisible();

    await page.screenshot({ path: path.join(outputDir, 'product-gallery-keyboard-focus-1440.png'), fullPage: false });

    await second.press('ArrowRight');
    await expect(first).toHaveAttribute('aria-selected', 'true');
    await expect(first).toBeFocused();

    await first.press('ArrowRight');
    await expect(last).toHaveAttribute('aria-selected', 'true');
    await expect(last).toHaveAttribute('tabindex', '0');
    await expect(last).toBeFocused();
    await expect(page.locator('#product-media-3')).toBeVisible();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    expect(overflow).toBe(false);
    expect(runtimeFailures).toEqual([]);
});
