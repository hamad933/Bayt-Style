import fs from 'node:fs';
import { expect, test } from '@playwright/test';

fs.mkdirSync('storage/test-artifacts/admin-logout-submit-state-quality', { recursive: true });

const adminEmail = process.env.S09_ADMIN_EMAIL;
const adminPassword = process.env.S09_ADMIN_PASSWORD;

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

async function login(page) {
    if (!adminEmail || !adminPassword) {
        throw new Error('S09_ADMIN_EMAIL and S09_ADMIN_PASSWORD are required for admin browser evidence.');
    }

    await page.goto('/admin/login');
    await page.getByLabel('البريد الإلكتروني').fill(adminEmail);
    await page.getByLabel('كلمة المرور').fill(adminPassword);
    await page.getByRole('button', { name: 'دخول آمن' }).click();
    await expect(page).toHaveURL(/\/admin\/catalog(?:\?|$)/);
}

test('[QUALITY][ADMIN LOGOUT] logout exposes a visible single-flight submitting state', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const runtimeFailures = watchRuntime(page);

    await login(page);

    const form = page.locator('.admin-sidebar__foot form[action$="/admin/logout"]');
    const submit = form.locator('button[type="submit"]');
    await expect(form).toBeVisible();
    await expect(submit).toBeVisible();
    await expect(submit).toHaveText('تسجيل الخروج');
    await submit.scrollIntoViewIfNeeded();

    await page.evaluate(() => {
        const frame = document.createElement('iframe');
        frame.name = 'rp01-admin-logout-target';
        frame.hidden = true;
        document.body.append(frame);
    });
    await form.evaluate((node) => node.setAttribute('target', 'rp01-admin-logout-target'));

    let requests = 0;
    let releaseRequest;
    const requestRelease = new Promise((resolve) => { releaseRequest = resolve; });
    await page.route('**/admin/logout', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        requests += 1;
        await requestRelease;
        await route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html lang="ar" dir="rtl"><body>ok</body></html>' });
    });

    await submit.click();

    await expect.poll(() => requests).toBe(1);
    await expect(form).toHaveAttribute('aria-busy', 'true');
    await expect(submit).toBeDisabled();
    await expect(submit).toHaveAttribute('aria-busy', 'true');
    await expect(submit).toHaveText('جارٍ تسجيل الخروج…');

    const status = form.getByRole('status');
    await expect(status).toBeVisible();
    await expect(status).toHaveText('جارٍ إنهاء الجلسة بأمان…');

    const payload = await form.evaluate((node) => Object.fromEntries(new FormData(node).entries()));
    expect(payload._token).toBeTruthy();

    const secondSubmitPrevented = await form.evaluate((node) => !node.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    expect(secondSubmitPrevented).toBe(true);
    await expect.poll(() => requests).toBe(1);

    const box = await status.boundingBox();
    expect(box).not.toBeNull();
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(391);
    expect(box.y).toBeGreaterThanOrEqual(0);
    expect(box.y + box.height).toBeLessThanOrEqual(845);

    const hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    expect(hasOverflow).toBe(false);
    expect(runtimeFailures).toEqual([]);

    await page.screenshot({
        path: 'storage/test-artifacts/admin-logout-submit-state-quality/admin-logout-submitting-state-390.png',
        fullPage: false,
    });

    releaseRequest();
});
