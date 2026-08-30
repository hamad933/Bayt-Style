import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/pdp-comparison-busy-state-quality');
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

async function assertNoPageOverflow(page) {
    const metrics = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: document.documentElement.clientWidth,
    }));
    expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
}

test('[QUALITY][STATE][PDP] comparison toggle is single-flight and visibly busy on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const failures = watchRuntime(page);
    await page.goto('/products/olive-velvet-lounge-chair');

    const button = page.getByTestId('detail-comparison');
    await button.scrollIntoViewIfNeeded();
    await expect(button).toHaveAttribute('aria-busy', 'false');

    let requests = 0;
    let releaseRequest;
    const requestRelease = new Promise((resolve) => { releaseRequest = resolve; });

    await page.route('**/comparison/*', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        requests += 1;
        await requestRelease;
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ count: 1, limit: 3, already_present: false }),
        });
    });

    await button.click();
    await expect.poll(() => requests).toBe(1);
    await expect(button).toBeDisabled();
    await expect(button).toHaveAttribute('aria-busy', 'true');
    await expect(button).toContainText('جارٍ التحديث…');

    const status = page.getByRole('status', { name: '' }).filter({ hasText: 'جارٍ تحديث المقارنة…' });
    await expect(status).toBeVisible();
    const box = await status.boundingBox();
    expect(box).not.toBeNull();
    expect(box.y).toBeGreaterThanOrEqual(0);
    expect(box.y + box.height).toBeLessThanOrEqual(844);

    await button.evaluate((node) => node.click());
    await page.waitForTimeout(100);
    expect(requests).toBe(1);

    await assertNoPageOverflow(page);
    expect(failures).toEqual([]);
    await page.screenshot({ path: path.join(outputDir, 'pdp-comparison-busy-390.png'), fullPage: false });

    releaseRequest();
    await expect(button).toHaveAttribute('aria-busy', 'false');
});
