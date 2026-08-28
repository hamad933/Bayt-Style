import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/touch-target-quality');
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

async function expectMinimumTarget(locator, label, minimum = 24) {
    await expect(locator, `${label} should be visible`).toBeVisible();
    const box = await locator.boundingBox();
    expect(box, `${label} should have a rendered box`).not.toBeNull();
    expect(box.width, `${label} rendered width`).toBeGreaterThanOrEqual(minimum);
    expect(box.height, `${label} rendered height`).toBeGreaterThanOrEqual(minimum);
}

async function expectNoPageOverflow(page) {
    const metrics = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: document.documentElement.clientWidth,
    }));
    expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
}

test('[QUALITY][TOUCH] critical mobile header and overlay controls keep usable rendered targets', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const runtimeFailures = watchRuntime(page);
    await page.goto('/');

    const menu = page.getByRole('button', { name: 'فتح القائمة' });
    const cart = page.getByRole('button', { name: 'فتح السلة' });

    await expectMinimumTarget(menu, 'mobile menu trigger');
    await expectMinimumTarget(cart, 'cart trigger');

    await menu.click();
    const mobileNav = page.getByRole('navigation', { name: 'تنقل الجوال' });
    await expect(mobileNav).toBeVisible();
    const mobileLinks = mobileNav.getByRole('link');
    const mobileLinkCount = await mobileLinks.count();
    expect(mobileLinkCount).toBeGreaterThan(0);
    for (let index = 0; index < mobileLinkCount; index += 1) {
        await expectMinimumTarget(mobileLinks.nth(index), `mobile navigation link ${index + 1}`);
    }

    const mobileLogin = mobileNav.getByRole('button', { name: 'تسجيل الدخول' });
    await expectMinimumTarget(mobileLogin, 'mobile login trigger');
    await mobileLogin.click();

    const loginBackdrop = page.locator('.dialog-backdrop');
    const loginDialog = page.getByRole('dialog', { name: 'تسجيل الدخول' });
    await expect(loginDialog).toBeVisible();
    await expect(loginBackdrop).toHaveCSS('opacity', '1');
    await expectMinimumTarget(loginDialog.getByRole('button', { name: 'إغلاق' }), 'login dialog close');
    await expectMinimumTarget(loginDialog.getByRole('button', { name: 'متابعة كزائر' }), 'continue as guest');
    await page.screenshot({ path: path.join(outputDir, 'login-dialog-touch-targets-390.png'), fullPage: true });
    await page.keyboard.press('Escape');
    await expect(loginDialog).toBeHidden();

    await cart.click();
    const drawerBackdrop = page.locator('.drawer-backdrop');
    const cartDrawer = page.getByRole('dialog', { name: 'مختاراتك الحالية' });
    await expect(cartDrawer).toBeVisible();
    await expect(drawerBackdrop).toHaveCSS('opacity', '1');
    await expectMinimumTarget(cartDrawer.getByRole('button', { name: 'إغلاق السلة' }), 'cart drawer close');
    await page.screenshot({ path: path.join(outputDir, 'cart-drawer-touch-targets-390.png'), fullPage: true });

    await expectNoPageOverflow(page);
    expect(runtimeFailures).toEqual([]);
});
