import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/dialog-focus-management-quality');
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

test('[QUALITY][A11Y] login dialog captures, traps, and restores keyboard focus', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    const runtimeFailures = watchRuntime(page);
    await page.goto('/');

    const trigger = page.locator('.text-action.desktop-only');
    const dialog = page.locator('#login-dialog');
    const close = dialog.getByRole('button', { name: 'إغلاق' });
    const continueAsGuest = dialog.getByRole('button', { name: 'متابعة كزائر' });

    await trigger.focus();
    await expect(trigger).toBeFocused();
    await trigger.press('Enter');

    await expect(dialog).toBeVisible();
    await expect(close).toBeFocused();

    await close.press('Shift+Tab');
    await expect(continueAsGuest).toBeFocused();
    await continueAsGuest.press('Tab');
    await expect(close).toBeFocused();

    await page.screenshot({ path: path.join(outputDir, 'login-dialog-keyboard-focus-1440.png'), fullPage: false });

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(trigger).toBeFocused();
    expect(runtimeFailures).toEqual([]);
});

test('[QUALITY][A11Y] cart dialog captures, contains, and restores keyboard focus', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const runtimeFailures = watchRuntime(page);
    await page.goto('/');

    const trigger = page.locator('.icon-action');
    const drawer = page.locator('#cart-drawer');
    const close = drawer.getByRole('button', { name: 'إغلاق السلة' });

    await trigger.focus();
    await expect(trigger).toBeFocused();
    await trigger.press('Enter');

    await expect(drawer).toBeVisible();
    await expect(close).toBeFocused();

    await close.press('Tab');
    await expect(close).toBeFocused();

    await page.screenshot({ path: path.join(outputDir, 'cart-drawer-keyboard-focus-390.png'), fullPage: false });

    await page.keyboard.press('Escape');
    await expect(drawer).toBeHidden();
    await expect(trigger).toBeFocused();
    expect(runtimeFailures).toEqual([]);
});
