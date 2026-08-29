import { test, expect } from '@playwright/test';

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

async function pressTabAndExpect(page, locator) {
    await page.keyboard.press('Tab');
    await expect(locator).toBeFocused();
}

for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000, compact: false },
    { name: 'mobile-390', width: 390, height: 844, compact: true },
]) {
    test(`S10 admin order detail preserves visual and keyboard sequence ${viewport.name}`, async ({ page }) => {
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

        const backLink = page.getByRole('link', { name: 'العودة إلى الطلبات' });
        const itemsRegion = page.getByRole('region', { name: 'بنود الطلب' });
        const reason = page.locator('.s10-sensitive-form textarea[name="reason"]');
        const cancelButton = page.getByRole('button', { name: 'إلغاء الطلب فقط' });

        await expect(backLink).toBeVisible();
        await expect(itemsRegion).toBeVisible();
        await expect(reason).toBeVisible();
        await expect(cancelButton).toBeVisible();
        await backLink.focus();
        await expect(backLink).toBeFocused();

        if (viewport.compact) {
            await pressTabAndExpect(page, reason);
            await pressTabAndExpect(page, cancelButton);
            await pressTabAndExpect(page, itemsRegion);
        } else {
            await pressTabAndExpect(page, itemsRegion);
            await pressTabAndExpect(page, reason);
            await pressTabAndExpect(page, cancelButton);
        }

        const hasOverflow = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        );
        expect(hasOverflow).toBe(false);
        expect(pageErrors).toEqual([]);
        expect(consoleErrors).toEqual([]);
        expect(serverFailures).toEqual([]);
    });
}
