import fs from 'node:fs';
import { test, expect } from '@playwright/test';

fs.mkdirSync('storage/test-artifacts/visual', { recursive: true });

const adminEmail = process.env.S09_ADMIN_EMAIL;
const adminPassword = process.env.S09_ADMIN_PASSWORD;

async function login(page) {
    if (!adminEmail || !adminPassword) {
        throw new Error('S09_ADMIN_EMAIL and S09_ADMIN_PASSWORD are required for S10 browser evidence.');
    }

    await page.goto('/admin/login');
    await page.getByLabel('البريد الإلكتروني').fill(adminEmail);
    await page.getByLabel('كلمة المرور').fill(adminPassword);
    await page.getByRole('button', { name: 'دخول آمن' }).click();
    await expect(page).toHaveURL(/\/admin\/catalog(?:\?|$)/);
}

async function openEvidenceOrder(page) {
    await login(page);
    await page.getByRole('link', { name: 'الطلبات والمدفوعات والمرتجعات' }).click();
    await expect(page).toHaveURL(/\/admin\/orders(?:\?|$)/);

    const row = page.getByRole('row').filter({ hasText: 'BAS-S10-EVIDENCE' });
    await expect(row).toHaveCount(1);
    await row.getByRole('link', { name: 'فتح' }).click();
    await expect(page).toHaveURL(/\/admin\/orders\/BAS-S10-EVIDENCE$/);
}

async function expectInsideInitialViewport(locator, viewport) {
    await expect(locator).toBeVisible();
    const box = await locator.boundingBox();
    expect(box).not.toBeNull();
    expect(box.y).toBeGreaterThanOrEqual(0);
    expect(box.y + box.height).toBeLessThanOrEqual(viewport.height + 1);
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + 1);
}

for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'tablet-820', width: 820, height: 1000 },
    { name: 'tablet-768', width: 768, height: 1024 },
    { name: 'mobile-430', width: 430, height: 932 },
    { name: 'mobile-390', width: 390, height: 844 },
]) {
    test(`S10 admin order detail exposes primary truth in initial viewport ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });

        const pageErrors = [];
        const consoleErrors = [];
        const serverFailures = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));
        page.on('console', (message) => {
            if (message.type() === 'error') consoleErrors.push(message.text());
        });
        page.on('response', (response) => {
            if (response.status() >= 500) serverFailures.push(`${response.status()} ${response.url()}`);
        });

        await openEvidenceOrder(page);

        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expectInsideInitialViewport(page.getByText('BAS-S10-EVIDENCE', { exact: true }).first(), viewport);
        await expectInsideInitialViewport(page.getByRole('heading', { name: 'الدفع', exact: true }), viewport);
        await expectInsideInitialViewport(page.getByText('لا توجد سلطة نجاح دفع في المتصفح.', { exact: true }), viewport);

        if (viewport.width <= 1000) {
            const sequence = await page.evaluate(() => {
                const grid = document.querySelector('.s10-order-head + .s10-grid.s10-grid--two');
                const items = grid?.querySelector('.s10-table--compact')?.closest('.admin-panel');
                const payment = grid?.querySelector('.s10-state--financial')?.closest('.admin-panel');
                if (!grid || !items || !payment) return null;
                return {
                    firstIsPayment: grid.firstElementChild === payment,
                    secondIsItems: payment.nextElementSibling === items,
                    paymentTop: payment.getBoundingClientRect().top,
                    itemsTop: items.getBoundingClientRect().top,
                };
            });
            expect(sequence).not.toBeNull();
            expect(sequence.firstIsPayment).toBe(true);
            expect(sequence.secondIsItems).toBe(true);
            expect(sequence.paymentTop).toBeLessThan(sequence.itemsTop);
        }

        const hasOverflow = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        );
        expect(hasOverflow).toBe(false);
        expect(pageErrors).toEqual([]);
        expect(consoleErrors).toEqual([]);
        expect(serverFailures).toEqual([]);

        await page.screenshot({
            path: `storage/test-artifacts/visual/s10-admin-order-detail-initial-${viewport.name}.png`,
            fullPage: false,
        });
    });
}
