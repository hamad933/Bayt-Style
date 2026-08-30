import fs from 'node:fs';
import { expect, test } from '@playwright/test';

fs.mkdirSync('storage/test-artifacts/admin-write-submit-state-quality', { recursive: true });

const adminEmail = process.env.S09_ADMIN_EMAIL;
const adminPassword = process.env.S09_ADMIN_PASSWORD;

async function loginAndOpenFirstCatalogProduct(page) {
    if (!adminEmail || !adminPassword) {
        throw new Error('S09_ADMIN_EMAIL and S09_ADMIN_PASSWORD are required for S10 browser evidence.');
    }

    await page.goto('/admin/login');
    await page.getByLabel('البريد الإلكتروني').fill(adminEmail);
    await page.getByLabel('كلمة المرور').fill(adminPassword);
    await page.getByRole('button', { name: 'دخول آمن' }).click();
    await expect(page).toHaveURL(/\/admin\/catalog(?:\?|$)/);

    const manageLink = page.getByRole('link', { name: 'إدارة' }).first();
    await expect(manageLink).toBeVisible();
    await manageLink.click();
    await expect(page).toHaveURL(/\/admin\/catalog\/\d+\/edit$/);
}

test('[QUALITY][STATE] S10 catalog inventory write exposes a truthful single-flight submitting state', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });

    const failures = [];
    page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
        if (message.type() === 'error') failures.push(`console.error: ${message.text()}`);
    });
    page.on('response', (response) => {
        if (response.status() >= 500) failures.push(`HTTP ${response.status()}: ${response.url()}`);
    });

    await loginAndOpenFirstCatalogProduct(page);

    const form = page.locator('.admin-form--inventory').first();
    await expect(form).toBeVisible();
    await form.scrollIntoViewIfNeeded();

    const button = form.locator('button[type="submit"]').first();
    await expect(button).toBeVisible();
    await expect(button).toHaveText('تسجيل حركة المخزون');
    await expect(form).toHaveAttribute('aria-busy', 'false');

    await page.evaluate(() => {
        const frame = document.createElement('iframe');
        frame.name = 'rp01-admin-write-target';
        frame.hidden = true;
        document.body.append(frame);
    });
    await form.evaluate((node) => node.setAttribute('target', 'rp01-admin-write-target'));

    let requests = 0;
    let observedPayload = '';
    let releaseRequest;
    const requestRelease = new Promise((resolve) => { releaseRequest = resolve; });

    await page.route('**/admin/catalog/*/variants/*/inventory-adjustments', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        requests += 1;
        observedPayload = route.request().postData() ?? '';
        await requestRelease;
        await route.fulfill({
            status: 200,
            contentType: 'text/html',
            body: '<!doctype html><html lang="ar" dir="rtl"><body>ok</body></html>',
        });
    });

    await form.getByLabel('التغيير في الكمية').fill('1');
    await form.getByLabel('سبب التعديل').fill('اختبار حالة إرسال حركة المخزون');
    await button.click();

    await expect.poll(() => requests).toBe(1);
    await expect(form).toHaveAttribute('aria-busy', 'true');
    await expect(button).toBeDisabled();
    await expect(button).toHaveAttribute('aria-busy', 'true');
    await expect(button).toHaveText('جارٍ التنفيذ…');

    const status = form.getByRole('status');
    await expect(status).toBeVisible();
    await expect(status).toHaveText('جارٍ تنفيذ الإجراء…');
    await expect(status).toBeInViewport();

    expect(observedPayload).toContain('quantity_delta=1');
    expect(observedPayload).toContain('reason=');

    await form.evaluate((node) => node.requestSubmit());
    await page.waitForTimeout(100);
    expect(requests).toBe(1);

    const hasHorizontalOverflow = await page.evaluate(() => (
        document.documentElement.scrollWidth > document.documentElement.clientWidth + 1
    ));
    expect(hasHorizontalOverflow).toBe(false);

    await page.screenshot({
        path: 'storage/test-artifacts/admin-write-submit-state-quality/admin-inventory-submitting-390.png',
        fullPage: false,
    });

    releaseRequest();
    await page.waitForTimeout(100);
    expect(failures).toEqual([]);
});
