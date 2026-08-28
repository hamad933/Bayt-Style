import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/skip-link-quality');
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

async function assertNoHorizontalOverflow(page) {
    const metrics = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: document.documentElement.clientWidth,
    }));
    expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
}

for (const viewport of [
    { width: 1440, height: 1000, label: '1440' },
    { width: 390, height: 844, label: '390' },
]) {
    test(`[QUALITY][KEYBOARD] skip link exposes focus and moves focus to main content at ${viewport.label}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        const runtimeFailures = watchRuntime(page);
        await page.goto('/');

        const skipLink = page.getByRole('link', { name: 'انتقل إلى المحتوى الرئيسي' });
        const main = page.locator('#main-content');

        await expect(skipLink).toBeAttached();
        await expect(main).toHaveAttribute('tabindex', '-1');

        await page.keyboard.press('Tab');
        await expect(skipLink).toBeFocused();
        await expect(skipLink).toBeVisible();

        const skipBox = await skipLink.boundingBox();
        expect(skipBox).not.toBeNull();
        expect(skipBox.y).toBeGreaterThanOrEqual(0);
        expect(skipBox.y).toBeLessThan(viewport.height);

        await page.screenshot({
            path: path.join(outputDir, `skip-link-focused-${viewport.label}.png`),
            fullPage: false,
        });

        await page.keyboard.press('Enter');
        await expect(main).toBeFocused();
        await expect(page).toHaveURL(/#main-content$/);

        const mainBox = await main.boundingBox();
        expect(mainBox).not.toBeNull();
        expect(mainBox.y).toBeLessThan(viewport.height);

        await assertNoHorizontalOverflow(page);
        expect(runtimeFailures).toEqual([]);
    });
}
