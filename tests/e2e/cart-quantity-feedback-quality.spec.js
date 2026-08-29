import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/cart-quantity-feedback-quality');
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

async function expectInsideViewport(locator, viewport) {
    const box = await locator.boundingBox();
    expect(box).not.toBeNull();
    expect(box.x).toBeGreaterThanOrEqual(-1);
    expect(box.y).toBeGreaterThanOrEqual(-1);
    expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + 1);
    expect(box.y + box.height).toBeLessThanOrEqual(viewport.height + 1);
}

test('[QUALITY][A11Y][CART] item mutations are single-flight and visibly confirmed on mobile', async ({ page }) => {
    const viewport = { width: 390, height: 844 };
    await page.setViewportSize(viewport);
    const runtimeFailures = watchRuntime(page);

    await page.goto('/products/olive-velvet-lounge-chair');
    const sand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
    await sand.click();
    await expect(sand).toHaveAttribute('aria-pressed', 'true');
    await page.getByTestId('add-to-cart').click();

    await page.getByRole('button', { name: 'فتح السلة' }).click();
    const drawer = page.getByRole('dialog', { name: 'مختاراتك الحالية' });
    await expect(drawer).toBeVisible();
    await expect(drawer.getByText('جارٍ تحديث السلة…')).toBeHidden();

    const productName = (await drawer.locator('.cart-line-copy > strong').first().textContent())?.trim();
    expect(productName).toBeTruthy();
    const line = drawer.locator('.cart-line').first();
    const quantity = drawer.getByRole('group', { name: `كمية ${productName}` });
    const quantityValue = quantity.locator('span');
    const decrease = quantity.getByRole('button', { name: `تقليل كمية ${productName}` });
    const increase = quantity.getByRole('button', { name: `زيادة كمية ${productName}` });
    const remove = drawer.getByRole('button', { name: `إزالة ${productName} من السلة` });
    const itemBusyStatus = line.getByRole('status', { name: '' });

    await expect(quantity).toBeVisible();
    await expect(decrease).toBeDisabled();
    await expect(increase).toBeEnabled();
    await expect(remove).toBeVisible();
    await expect(quantityValue).toHaveText('1');

    let patchRequests = 0;
    let deleteRequests = 0;
    await page.route('**/cart/items/*', async (route) => {
        const method = route.request().method();
        if (method === 'DELETE') {
            deleteRequests += 1;
            await route.continue();
            return;
        }
        if (method !== 'PATCH') {
            await route.continue();
            return;
        }
        patchRequests += 1;
        await new Promise((resolve) => setTimeout(resolve, 1200));
        await route.continue();
    });

    await increase.click();
    await expect.poll(() => patchRequests).toBe(1);
    await expect(line).toHaveAttribute('aria-busy', 'true');
    await expect(increase).toBeDisabled();
    await expect(remove).toBeDisabled();
    await expect(itemBusyStatus).toBeVisible();
    await expect(itemBusyStatus).toHaveText('جارٍ تحديث هذه القطعة…');
    await expectInsideViewport(itemBusyStatus, viewport);

    await page.screenshot({
        path: path.join(outputDir, 'cart-quantity-busy-390.png'),
        fullPage: false,
    });

    await page.evaluate(() => {
        const cart = window.Alpine.store('cart');
        const item = cart.items[0];
        cart.setQuantity(item.variant_id, item.quantity + 2);
        cart.remove(item.variant_id);
    });
    await expect.poll(() => patchRequests).toBe(1);
    expect(deleteRequests).toBe(0);

    await expect(quantityValue).toHaveText('2');
    await expect(line).toHaveAttribute('aria-busy', 'false');
    await expect(itemBusyStatus).toBeHidden();
    await expect(decrease).toBeEnabled();
    await expect(increase).toBeEnabled();
    await expect(remove).toBeEnabled();

    const region = page.locator('.toast-region');
    await expect(region).toHaveAttribute('aria-live', 'polite');
    await expect(region).toHaveAttribute('aria-atomic', 'true');
    const toast = region.locator('.toast');
    await expect(toast).toBeVisible();
    await expect(toast).toHaveText('تم تحديث كمية القطعة في السلة.');
    await expect.poll(async () => toast.evaluate((element) => getComputedStyle(element).opacity)).toBe('1');
    await expectInsideViewport(toast, viewport);

    const documentWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    expect(documentWidth).toBeLessThanOrEqual(viewport.width + 1);
    expect(runtimeFailures).toEqual([]);

    await page.screenshot({
        path: path.join(outputDir, 'cart-quantity-feedback-390.png'),
        fullPage: false,
    });
});
