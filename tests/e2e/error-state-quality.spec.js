import fs from 'node:fs';
import { test, expect } from '@playwright/test';

fs.mkdirSync('storage/test-artifacts/visual', { recursive: true });

const expectedNavigation404ConsoleError = 'Failed to load resource: the server responded with a status of 404 (Not Found)';

for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'mobile-390', width: 390, height: 844 },
]) {
    test(`404 state is visible, recoverable, and runtime-clean at ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });

        const pageErrors = [];
        const consoleErrors = [];
        const unexpectedClientErrors = [];
        const serverErrors = [];
        page.on('pageerror', error => pageErrors.push(error.message));
        page.on('console', message => {
            if (message.type() === 'error' && message.text() !== expectedNavigation404ConsoleError) {
                consoleErrors.push(message.text());
            }
        });
        page.on('response', response => {
            const status = response.status();
            const resourceType = response.request().resourceType();

            if (status >= 400 && status < 500 && resourceType !== 'document') {
                unexpectedClientErrors.push(`${status} ${resourceType} ${response.url()}`);
            }
            if (status >= 500) serverErrors.push(`${status} ${response.url()}`);
        });

        const response = await page.goto('/__rp01_missing_page_quality_probe__');
        expect(response, '404 navigation must return an HTTP response').not.toBeNull();
        expect(response.status(), 'missing route must remain a truthful 404').toBe(404);

        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

        const state = page.getByRole('status');
        await expect(state).toBeVisible();
        await expect(state.getByText('خطأ 404', { exact: true })).toBeVisible();
        await expect(state.getByRole('heading', { name: 'الصفحة غير موجودة', level: 1 })).toBeVisible();
        await expect(state.getByText('تعذر العثور على الصفحة التي طلبتها.', { exact: true })).toBeVisible();

        const homeLink = state.getByRole('link', { name: 'العودة إلى الرئيسية' });
        const catalogLink = state.getByRole('link', { name: 'استعراض الكتالوج' });
        await expect(homeLink).toBeVisible();
        await expect(catalogLink).toBeVisible();

        const headingBox = await state.getByRole('heading', { name: 'الصفحة غير موجودة', level: 1 }).boundingBox();
        expect(headingBox, '404 heading must have a rendered box').not.toBeNull();
        expect(headingBox.y, '404 heading must start inside the initial viewport').toBeGreaterThanOrEqual(0);
        expect(headingBox.y + headingBox.height, '404 heading must remain inside the initial viewport').toBeLessThanOrEqual(viewport.height);

        const hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
        expect(hasOverflow, `404 page must not horizontally overflow at ${viewport.width}px`).toBe(false);

        expect(pageErrors, 'page errors').toEqual([]);
        expect(consoleErrors, 'unexpected console errors').toEqual([]);
        expect(unexpectedClientErrors, 'unexpected non-document HTTP 4xx responses').toEqual([]);
        expect(serverErrors, 'HTTP 5xx responses').toEqual([]);

        await page.screenshot({
            path: `storage/test-artifacts/visual/error-404-${viewport.name}.png`,
            fullPage: false,
        });

        await catalogLink.click();
        await expect(page).toHaveURL(/\/catalog$/);
        await expect(page.getByTestId('catalog-results')).toBeVisible();
    });
}
