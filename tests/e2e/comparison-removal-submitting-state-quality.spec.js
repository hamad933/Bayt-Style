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

test('[QUALITY][STATE] comparison removal exposes one truthful visible submitting state', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const runtimeFailures = watchRuntime(page);

    await page.goto('/catalog');
    const compareButton = page.getByTestId('comparison-toggle').first();
    await expect(compareButton).toBeVisible();
    if ((await compareButton.getAttribute('aria-pressed')) !== 'true') {
        await compareButton.click();
        await expect(compareButton).toHaveAttribute('aria-pressed', 'true');
    }

    await page.goto('/comparison');
    const removeButton = page.getByTestId('comparison-remove').first();
    await expect(removeButton).toBeVisible();
    await removeButton.scrollIntoViewIfNeeded();

    const form = removeButton.locator('..');
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
    const status = form.getByRole('status');
    await expect(status).toBeVisible();
    await expect(status).toHaveText('جارٍ إزالة المنتج من المقارنة…');
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

    await mkdir('storage/test-artifacts/comparison-removal-submitting-state-quality', { recursive: true });
    await page.screenshot({
        path: 'storage/test-artifacts/comparison-removal-submitting-state-quality/comparison-remove-submitting-390.png',
        fullPage: false,
    });

    expect(runtimeFailures).toEqual([]);
});
