import { mkdir } from 'node:fs/promises';
import { expect, test } from '@playwright/test';

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

test('[QUALITY][STATE] wishlist removal exposes one truthful visible submitting state', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const runtimeFailures = watchRuntime(page);

    await page.goto('/products/olive-velvet-lounge-chair');
    const wishlistToggle = page.getByTestId('detail-wishlist');
    await expect(wishlistToggle).toBeVisible();
    if ((await wishlistToggle.getAttribute('aria-pressed')) !== 'true') {
        await wishlistToggle.click();
        await expect(wishlistToggle).toHaveAttribute('aria-pressed', 'true');
        await expect(wishlistToggle).toBeEnabled();
    }

    await page.goto('/wishlist');
    const removeButton = page.getByTestId('wishlist-remove-submit').first();
    await expect(removeButton).toBeVisible();
    await removeButton.scrollIntoViewIfNeeded();

    const form = page.getByTestId('wishlist-remove-form').first();
    await form.evaluate((element) => {
        const preventNavigation = (event) => event.preventDefault();
        element.__qualityPreventNavigation = preventNavigation;
        element.addEventListener('submit', preventNavigation, true);
    });

    await removeButton.click();
    await expect(removeButton).toBeDisabled();
    await expect(removeButton).toHaveAttribute('aria-busy', 'true');
    await expect(removeButton).toHaveText('جارٍ الإزالة…');
    await expect(form).toHaveAttribute('aria-busy', 'true');

    const status = page.getByTestId('wishlist-remove-status').first();
    await expect(status).toBeVisible();
    await expect(status).toHaveText('جارٍ إزالة القطعة من المفضلة…');
    await expect(status).toHaveAttribute('aria-live', 'polite');
    await expect(status).toHaveAttribute('aria-atomic', 'true');

    const secondSubmitPrevented = await form.evaluate((element) => {
        element.removeEventListener('submit', element.__qualityPreventNavigation, true);
        delete element.__qualityPreventNavigation;

        const event = new Event('submit', { bubbles: true, cancelable: true });
        element.dispatchEvent(event);
        return event.defaultPrevented;
    });
    expect(secondSubmitPrevented).toBe(true);

    const statusBox = await status.boundingBox();
    expect(statusBox).not.toBeNull();
    expect(statusBox.y).toBeGreaterThanOrEqual(-1);
    expect(statusBox.y + statusBox.height).toBeLessThanOrEqual(845);
    const hasHorizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    expect(hasHorizontalOverflow).toBe(false);

    await mkdir('storage/test-artifacts/wishlist-removal-submitting-state-quality', { recursive: true });
    await page.screenshot({
        path: 'storage/test-artifacts/wishlist-removal-submitting-state-quality/wishlist-remove-submitting-390.png',
        fullPage: false,
    });

    expect(runtimeFailures).toEqual([]);
});
