import fs from 'node:fs';
import { test, expect } from '@playwright/test';

fs.mkdirSync('storage/test-artifacts/visual', { recursive: true });

for (const viewport of [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'mobile-390', width: 390, height: 844 },
]) {
    test(`catalog no-results state is visible, recoverable, and runtime-clean at ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });

        const pageErrors = [];
        const consoleErrors = [];
        const serverErrors = [];
        page.on('pageerror', error => pageErrors.push(error.message));
        page.on('console', message => {
            if (message.type() === 'error') consoleErrors.push(message.text());
        });
        page.on('response', response => {
            if (response.status() >= 500) serverErrors.push(`${response.status()} ${response.url()}`);
        });

        await page.goto('/catalog?q=__rp01_no_results_probe__');

        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

        const noResults = page.getByTestId('no-results');
        await expect(noResults).toBeVisible();
        await expect(noResults.getByText('لا توجد نتائج', { exact: true })).toBeVisible();
        await expect(noResults.getByRole('heading', { name: 'لم نجد قطعًا مطابقة', level: 2 })).toBeVisible();
        await expect(noResults.getByText('جرّب تقليل عدد الفلاتر أو استخدام عبارة بحث أقصر. يمكنك أيضًا العودة إلى التشكيلة الكاملة.')).toBeVisible();

        const recovery = noResults.getByRole('link', { name: 'عرض كل المنتجات' });
        await expect(recovery).toBeVisible();
        await expect(recovery).toHaveAttribute('href', /\/catalog$/);

        const box = await noResults.boundingBox();
        expect(box, 'no-results panel must have a rendered box').not.toBeNull();
        expect(box.y, 'no-results state must begin inside the initial viewport').toBeLessThan(viewport.height);

        const hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
        expect(hasOverflow, `catalog no-results must not horizontally overflow at ${viewport.width}px`).toBe(false);

        expect(pageErrors, 'page errors').toEqual([]);
        expect(consoleErrors, 'console errors').toEqual([]);
        expect(serverErrors, 'HTTP 5xx responses').toEqual([]);

        await page.screenshot({
            path: `storage/test-artifacts/visual/catalog-no-results-${viewport.name}.png`,
            fullPage: true,
        });

        await recovery.click();
        await expect(page).toHaveURL(/\/catalog$/);
        await expect(page.getByTestId('catalog-results')).toBeVisible();
    });
}
