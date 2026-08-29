import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/mobile-nav-focus-order-quality');
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
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.y).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + 1);
    expect(box.y + box.height).toBeLessThanOrEqual(viewport.height + 1);
}

async function expectNoHorizontalOverflow(page) {
    const metrics = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
    }));
    expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.clientWidth + 1);
}

for (const viewport of [
    { width: 430, height: 932 },
    { width: 390, height: 844 },
]) {
    test(`[QUALITY][KEYBOARD][MOBILE-NAV] focus order remains coherent at ${viewport.width}px`, async ({ page }) => {
        await page.setViewportSize(viewport);
        const runtimeFailures = watchRuntime(page);
        await page.goto('/');

        const trigger = page.locator('.menu-toggle');
        await expect(trigger).toBeVisible();
        await expect(trigger).toHaveAccessibleName('فتح القائمة');
        await trigger.focus();
        await trigger.press('Enter');
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        await expect(trigger).toHaveAccessibleName('إغلاق القائمة');

        const navigation = page.getByRole('navigation', { name: 'تنقل الجوال' });
        const firstLink = navigation.getByRole('link', { name: 'المنتجات' });
        await expect(navigation).toBeVisible();
        await expect(firstLink).toBeVisible();
        await expectInsideViewport(firstLink, viewport);

        await page.keyboard.press('Tab');
        await expect(firstLink).toBeFocused();

        await page.keyboard.press('Shift+Tab');
        await expect(trigger).toBeFocused();

        await page.screenshot({
            path: path.join(outputDir, `mobile-nav-focus-order-${viewport.width}.png`),
            fullPage: false,
        });

        await expectNoHorizontalOverflow(page);
        expect(runtimeFailures).toEqual([]);
    });
}
