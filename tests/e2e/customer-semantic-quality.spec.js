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

async function expectSemanticPage(page, route) {
    const runtimeFailures = watchRuntime(page);
    await page.goto(route);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('main')).toHaveCount(1);
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('header')).toHaveCount(1);
    await expect(page.locator('footer')).toHaveCount(1);

    const unlabeledControls = await page.locator('input, select, textarea').evaluateAll((controls) => controls
        .filter((control) => !control.disabled && control.type !== 'hidden')
        .filter((control) => {
            const id = control.getAttribute('id');
            const hasForLabel = id ? Boolean(document.querySelector(`label[for="${CSS.escape(id)}"]`)) : false;
            const wrapped = Boolean(control.closest('label'));
            const ariaLabel = Boolean(control.getAttribute('aria-label')?.trim());
            const ariaLabelledBy = Boolean(control.getAttribute('aria-labelledby')?.trim());
            return !(hasForLabel || wrapped || ariaLabel || ariaLabelledBy);
        })
        .map((control) => `${control.tagName.toLowerCase()}#${control.id || '-'}[name="${control.getAttribute('name') || ''}"]`));
    expect(unlabeledControls, `Unlabelled form controls on ${route}`).toEqual([]);
    expect(runtimeFailures).toEqual([]);
}

for (const route of ['/', '/catalog', '/cart']) {
    test(`[QUALITY][SEMANTICS] ${route} exposes Arabic RTL landmarks, one visible h1, and labelled controls`, async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await expectSemanticPage(page, route);
    });
}
