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

async function expectSecondarySemanticPage(page, route) {
    const runtimeFailures = watchRuntime(page);
    const response = await page.goto(route);
    expect(response?.status(), `${route} should render without a server failure`).toBeLessThan(500);
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('main')).toHaveCount(1);
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.getByRole('banner')).toHaveCount(1);
    await expect(page.getByRole('contentinfo')).toHaveCount(1);

    const unnamedVisibleInteractive = await page.locator('button, a[href], input, select, textarea').evaluateAll((elements) => elements
        .filter((element) => {
            const style = getComputedStyle(element);
            return style.display !== 'none' && style.visibility !== 'hidden' && element.getClientRects().length > 0;
        })
        .filter((element) => element.getAttribute('type') !== 'hidden')
        .filter((element) => {
            const labelledBy = (element.getAttribute('aria-labelledby') || '').split(/\s+/).filter(Boolean)
                .map((id) => document.getElementById(id)?.textContent?.trim() || '').join(' ').trim();
            const labels = Array.from(element.labels || []).map((label) => label.textContent?.trim() || '').join(' ').trim();
            const name = element.getAttribute('aria-label')?.trim()
                || labelledBy
                || labels
                || element.getAttribute('title')?.trim()
                || element.textContent?.trim()
                || '';
            return !name;
        })
        .map((element) => `${element.tagName.toLowerCase()}#${element.id || '-'}[name="${element.getAttribute('name') || ''}"]`));

    expect(unnamedVisibleInteractive, `Unnamed visible interactive controls on ${route}`).toEqual([]);
    expect(runtimeFailures).toEqual([]);
}

for (const route of [
    '/products/olive-velvet-lounge-chair',
    '/wishlist',
    '/compare',
]) {
    test(`[QUALITY][SEMANTICS][SECONDARY] ${route} keeps Arabic RTL landmarks and named visible controls`, async ({ page }) => {
        await page.setViewportSize({ width: 430, height: 932 });
        await expectSecondarySemanticPage(page, route);
    });
}
