import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/filter-drawer-keyboard-quality');
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

test('[QUALITY][A11Y][CATALOG] mobile filter drawer traps focus and restores its opener on Escape', async ({ page }) => {
    const viewport = { width: 390, height: 844 };
    await page.setViewportSize(viewport);
    const runtimeFailures = watchRuntime(page);

    await page.goto('/catalog');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    const trigger = page.getByRole('button', { name: 'تصفية المنتجات' });
    await expect(trigger).toBeVisible();
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await trigger.focus();
    await expect(trigger).toBeFocused();
    await trigger.click();
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');

    const dialog = page.getByRole('dialog', { name: 'تصفية المنتجات' });
    await expect(dialog).toBeVisible();
    await expect(dialog).toHaveAttribute('aria-modal', 'true');

    const close = dialog.getByRole('button', { name: 'إغلاق' });
    await expect(close).toBeFocused();

    const backdrop = dialog.locator('..');
    await expect(backdrop).toBeVisible();
    await expect.poll(async () => backdrop.evaluate((element) => getComputedStyle(element).opacity)).toBe('1');

    const category = dialog.getByLabel('الفئة');
    const minPrice = dialog.getByLabel('السعر الأدنى');
    const maxPrice = dialog.getByLabel('السعر الأعلى');
    const submit = dialog.getByRole('button', { name: 'تطبيق التصفية' });
    const clear = dialog.getByRole('link', { name: 'مسح الفلاتر' });

    await page.keyboard.press('Tab');
    await expect(category).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(minPrice).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(maxPrice).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(submit).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(clear).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(close).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(clear).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(close).toBeFocused();

    await expectInsideViewport(dialog, viewport);
    await expectInsideViewport(close, viewport);
    const documentWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    expect(documentWidth).toBeLessThanOrEqual(viewport.width + 1);
    expect(runtimeFailures).toEqual([]);

    await page.screenshot({
        path: path.join(outputDir, 'filter-drawer-focus-390.png'),
        fullPage: false,
    });

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await expect(trigger).toBeFocused();
    expect(runtimeFailures).toEqual([]);
});
