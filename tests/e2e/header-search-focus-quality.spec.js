import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/header-search-focus-quality');
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

test('[QUALITY][A11Y] desktop header search exposes a visible keyboard focus indicator', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    const runtimeFailures = watchRuntime(page);
    await page.goto('/');

    const search = page.locator('.header-search input');
    await expect(search).toBeVisible();
    await search.focus();
    await expect(search).toBeFocused();

    const focusStyle = await search.evaluate((element) => {
        const style = getComputedStyle(element);
        return {
            outlineStyle: style.outlineStyle,
            outlineWidth: Number.parseFloat(style.outlineWidth),
            outlineColor: style.outlineColor,
            outlineOffset: Number.parseFloat(style.outlineOffset),
        };
    });

    expect(focusStyle.outlineStyle).not.toBe('none');
    expect(focusStyle.outlineWidth).toBeGreaterThanOrEqual(3);
    expect(focusStyle.outlineOffset).toBeGreaterThanOrEqual(3);
    expect(focusStyle.outlineColor).not.toBe('rgba(0, 0, 0, 0)');

    await page.screenshot({ path: path.join(outputDir, 'header-search-focus-1440.png'), fullPage: false });
    expect(runtimeFailures).toEqual([]);
});
