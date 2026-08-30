import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/cart-page-submit-state-quality');
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

test('[QUALITY][STATE][CART] full cart item update is single-flight and visibly busy on mobile', async ({ page }) => {
    const viewport = { width: 390, height: 844 };
    await page.setViewportSize(viewport);
    const failures = watchRuntime(page);

    await page.goto('/products/olive-velvet-lounge-chair');
    const sand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
    await sand.click();
    await expect(sand).toHaveAttribute('aria-pressed', 'true');
    await page.getByTestId('add-to-cart').click();
    await page.goto('/cart');

    const line = page.getByTestId('cart-line').first();
    const form = line.locator('form').filter({ has: page.getByRole('button', { name: 'تحديث' }) }).first();
    const button = form.getByRole('button', { name: 'تحديث' });
    await form.scrollIntoViewIfNeeded();
    await expect(form).toHaveAttribute('aria-busy', 'false');

    await page.evaluate(() => {
        const frame = document.createElement('iframe');
        frame.name = 'rp01-cart-page-target';
        frame.hidden = true;
        document.body.append(frame);
    });
    await form.evaluate((node) => node.setAttribute('target', 'rp01-cart-page-target'));

    let requests = 0;
    let observedPayload = '';
    let releaseRequest;
    const requestRelease = new Promise((resolve) => { releaseRequest = resolve; });

    await page.route('**/cart/items/*', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        requests += 1;
        observedPayload = route.request().postData() ?? '';
        await requestRelease;
        await route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html lang="ar" dir="rtl"><body>ok</body></html>' });
    });

    await form.getByRole('spinbutton', { name: 'الكمية' }).fill('2');
    await button.click();
    await expect.poll(() => requests).toBe(1);
    await expect(form).toHaveAttribute('aria-busy', 'true');
    await expect(button).toBeDisabled();
    await expect(button).toHaveAttribute('aria-busy', 'true');
    await expect(button).toHaveText('جارٍ التحديث…');

    const status = form.getByRole('status');
    await expect(status).toBeVisible();
    await expect(status).toHaveText('جارٍ تحديث الكمية…');
    await expect(status).toBeInViewport();
    expect(observedPayload).toContain('quantity=2');

    await form.evaluate((node) => node.requestSubmit());
    await page.waitForTimeout(100);
    expect(requests).toBe(1);

    const hasHorizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    expect(hasHorizontalOverflow).toBe(false);

    await page.screenshot({ path: path.join(outputDir, 'cart-page-update-busy-390.png'), fullPage: false });
    releaseRequest();
    await page.waitForTimeout(100);
    expect(failures).toEqual([]);
});
