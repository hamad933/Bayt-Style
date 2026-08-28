import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/saved-empty-state-quality');
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

async function expectInInitialViewport(locator, viewportHeight) {
  await expect(locator).toBeVisible();
  const box = await locator.boundingBox();
  expect(box, 'Visible element must expose a rendered bounding box').not.toBeNull();
  expect(box.y, 'Empty-state content should begin inside the initial viewport').toBeGreaterThanOrEqual(0);
  expect(box.y + Math.min(box.height, 48), 'Meaningful empty-state content should be visible without scrolling').toBeLessThanOrEqual(viewportHeight);
}

for (const surface of [
  {
    route: '/wishlist',
    testId: 'wishlist-empty',
    heading: 'ابدأ من التشكيلة التي تناسب مساحتك.',
    action: 'استكشف المنتجات',
    screenshot: 'wishlist-empty-390.png',
  },
  {
    route: '/comparison',
    testId: 'comparison-empty',
    heading: /اختر حتى .* منتجات من الكتالوج أو صفحات التفاصيل\./,
    action: 'اذهب إلى الكتالوج',
    screenshot: 'comparison-empty-390.png',
  },
]) {
  test(`[QUALITY][EMPTY-STATE][MOBILE] ${surface.route} exposes a truthful empty state without scrolling`, async ({ page }) => {
    const viewport = { width: 390, height: 844 };
    await page.setViewportSize(viewport);
    const runtimeFailures = watchRuntime(page);

    const response = await page.goto(surface.route);
    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    const emptyState = page.getByTestId(surface.testId);
    const heading = emptyState.getByRole('heading', { name: surface.heading });
    const action = emptyState.getByRole('link', { name: surface.action });

    await expect(emptyState).toBeVisible();
    await expectInInitialViewport(heading, viewport.height);
    await expectInInitialViewport(action, viewport.height);
    await assertNoPageOverflow(page);
    expect(runtimeFailures).toEqual([]);

    await page.screenshot({ path: path.join(outputDir, surface.screenshot), fullPage: true });
  });
}
