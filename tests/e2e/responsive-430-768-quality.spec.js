import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/responsive-430-768-quality');
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

async function expectNoHorizontalOverflow(page) {
  const metrics = await page.evaluate(() => ({
    documentWidth: document.documentElement.scrollWidth,
    viewportWidth: document.documentElement.clientWidth,
  }));
  expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
}

const surfaces = [
  { route: '/', slug: 'home' },
  { route: '/catalog', slug: 'catalog' },
  { route: '/wishlist', slug: 'wishlist' },
  { route: '/comparison', slug: 'comparison' },
];

for (const viewport of [
  { width: 430, height: 932 },
  { width: 768, height: 1024 },
]) {
  for (const surface of surfaces) {
    test(`[QUALITY][RESPONSIVE] ${surface.route} remains usable at ${viewport.width}px`, async ({ page }) => {
      await page.setViewportSize(viewport);
      const runtimeFailures = watchRuntime(page);

      const response = await page.goto(surface.route);
      expect(response?.status()).toBe(200);
      await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
      await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

      const main = page.locator('main');
      await expect(main).toBeVisible();
      const mainBox = await main.boundingBox();
      expect(mainBox, 'Main content must expose a rendered box').not.toBeNull();
      expect(mainBox.width, 'Main content must not collapse at the governed viewport').toBeGreaterThan(200);
      expect(mainBox.x, 'Main content must start inside the horizontal viewport').toBeGreaterThanOrEqual(-1);
      expect(mainBox.x + mainBox.width, 'Main content must stay inside the horizontal viewport').toBeLessThanOrEqual(viewport.width + 1);

      await expectNoHorizontalOverflow(page);
      expect(runtimeFailures).toEqual([]);

      await page.screenshot({
        path: path.join(outputDir, `${surface.slug}-${viewport.width}.png`),
        fullPage: true,
      });
    });
  }
}
