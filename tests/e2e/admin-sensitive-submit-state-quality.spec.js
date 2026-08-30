import fs from 'node:fs';
import { expect, test } from '@playwright/test';

fs.mkdirSync('storage/test-artifacts/admin-sensitive-submit-state-quality', { recursive: true });

const adminEmail = process.env.S09_ADMIN_EMAIL;
const adminPassword = process.env.S09_ADMIN_PASSWORD;

async function loginAndOpenEvidenceOrder(page) {
    if (!adminEmail || !adminPassword) {
        throw new Error('S09_ADMIN_EMAIL and S09_ADMIN_PASSWORD are required for S10 browser evidence.');
    }

    await page.goto('/admin/login');
    await page.getByLabel('البريد الإلكتروني').fill(adminEmail);
    await page.getByLabel('كلمة المرور').fill(adminPassword);
    await page.getByRole('button', { name: 'دخول آمن' }).click();
    await expect(page).toHaveURL(/\/admin\/catalog(?:\?|$)/);

    await page.getByRole('link', { name: 'الطلبات والمدفوعات والمرتجعات' }).click();
    const row = page.getByRole('row').filter({ hasText: 'BAS-S10-EVIDENCE' });
    await expect(row).toHaveCount(1);
    await row.getByRole('link', { name: 'فتح' }).click();
    await expect(page).toHaveURL(/\/admin\/orders\/BAS-S10-EVIDENCE$/);
}

test('[QUALITY][STATE] S10 sensitive admin action exposes a truthful single-flight submitting state', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });

    const failures = [];
    page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
        if (message.type() === 'error') failures.push(`console.error: ${message.text()}`);
    });
    page.on('response', (response) => {
        if (response.status() >= 500) failures.push(`HTTP ${response.status()}: ${response.url()}`);
    });

    await loginAndOpenEvidenceOrder(page);

    const form = page.locator('.s10-sensitive-form').first();
    await expect(form).toBeVisible();
    await form.scrollIntoViewIfNeeded();

    const button = form.getByRole('button', { name: 'إلغاء الطلب فقط' });
    await expect(button).toBeVisible();
    await expect(form).toHaveAttribute('aria-busy', 'false');

    let requests = 0;
    await page.route('**/admin/orders/BAS-S10-EVIDENCE/cancel', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        requests += 1;
        await new Promise((resolve) => setTimeout(resolve, 900));
        await route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html lang="ar" dir="rtl"><body>ok</body></html>' });
    });

    await form.getByLabel('سبب القرار').fill('اختبار حالة الإرسال المصرح بها');
    await button.click({ noWaitAfter: true });

    await expect(form).toHaveAttribute('aria-busy', 'true');
    await expect(button).toBeDisabled();
    await expect(button).toHaveAttribute('aria-busy', 'true');
    await expect(button).toHaveText('جارٍ التنفيذ…');

    const status = form.getByRole('status');
    await expect(status).toBeVisible();
    await expect(status).toHaveText('جارٍ تنفيذ الإجراء…');

    const box = await status.boundingBox();
    expect(box).not.toBeNull();
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(391);
    expect(box.y).toBeGreaterThanOrEqual(0);
    expect(box.y + box.height).toBeLessThanOrEqual(845);

    const secondSubmitPrevented = await form.evaluate((node) => !node.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    expect(secondSubmitPrevented).toBe(true);
    await expect.poll(() => requests).toBe(1);

    const hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    expect(hasOverflow).toBe(false);
    expect(failures).toEqual([]);

    await page.screenshot({
        path: 'storage/test-artifacts/admin-sensitive-submit-state-quality/s10-admin-sensitive-busy-390.png',
        fullPage: false,
    });
});
