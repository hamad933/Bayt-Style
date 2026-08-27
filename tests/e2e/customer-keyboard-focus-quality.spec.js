import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/keyboard-focus-quality');
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

async function expectNoPageOverflow(page) {
    const metrics = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: document.documentElement.clientWidth,
    }));
    expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
}

test('[QUALITY][KEYBOARD] mobile navigation closes with Escape and restores focus', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const runtimeFailures = watchRuntime(page);
    await page.goto('/');

    const trigger = page.getByRole('button', { name: 'فتح القائمة' });
    await expect(trigger).toBeVisible();
    await trigger.focus();
    await trigger.press('Enter');
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expect(page.getByRole('navigation', { name: 'تنقل الجوال' })).toBeVisible();

    await page.screenshot({ path: path.join(outputDir, 'mobile-navigation-open-390.png'), fullPage: true });
    await page.keyboard.press('Escape');

    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await expect(page.getByRole('navigation', { name: 'تنقل الجوال' })).toBeHidden();
    await expect(trigger).toBeFocused();
    await expectNoPageOverflow(page);
    expect(runtimeFailures).toEqual([]);
});

test('[QUALITY][KEYBOARD] login dialog traps focus and returns it to the opener', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    const runtimeFailures = watchRuntime(page);
    await page.goto('/');

    const trigger = page.getByRole('button', { name: 'تسجيل الدخول' }).first();
    await trigger.focus();
    await trigger.press('Enter');

    const backdrop = page.locator('.dialog-backdrop');
    const dialog = page.getByRole('dialog', { name: 'تسجيل الدخول' });
    const close = dialog.getByRole('button', { name: 'إغلاق' });
    const continueAsGuest = dialog.getByRole('button', { name: 'متابعة كزائر' });
    await expect(dialog).toBeVisible();
    await expect(close).toBeFocused();

    await page.keyboard.press('Shift+Tab');
    await expect(continueAsGuest).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(close).toBeFocused();
    await expect(backdrop).toHaveCSS('opacity', '1');

    await page.screenshot({ path: path.join(outputDir, 'login-dialog-focus-1440.png'), fullPage: true });
    await page.keyboard.press('Escape');

    await expect(dialog).toBeHidden();
    await expect(trigger).toBeFocused();
    await expectNoPageOverflow(page);
    expect(runtimeFailures).toEqual([]);
});

test('[QUALITY][KEYBOARD] cart drawer traps empty-state focus and restores it on Escape', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const runtimeFailures = watchRuntime(page);
    await page.goto('/');

    const trigger = page.getByRole('button', { name: 'فتح السلة' });
    await trigger.focus();
    await trigger.press('Enter');

    const backdrop = page.locator('.drawer-backdrop');
    const drawer = page.getByRole('dialog', { name: 'مختاراتك الحالية' });
    const close = drawer.getByRole('button', { name: 'إغلاق السلة' });
    await expect(drawer).toBeVisible();
    await expect(drawer.getByText('لم تضف أي قطعة بعد.')).toBeVisible();
    await expect(close).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(close).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(close).toBeFocused();
    await expect(backdrop).toHaveCSS('opacity', '1');

    await page.screenshot({ path: path.join(outputDir, 'cart-drawer-empty-focus-390.png'), fullPage: true });
    await page.keyboard.press('Escape');

    await expect(drawer).toBeHidden();
    await expect(trigger).toBeFocused();
    await expectNoPageOverflow(page);
    expect(runtimeFailures).toEqual([]);
});
