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

async function contrastEvidence(locator, label) {
  await expect(locator, `${label} must be visible`).toBeVisible();
  return locator.evaluate((element) => {
    const parse = (value) => {
      const match = value.match(/rgba?\(([^)]+)\)/i);
      if (!match) throw new Error(`Unsupported color: ${value}`);
      const parts = match[1].split(',').map((part) => Number.parseFloat(part.trim()));
      return { r: parts[0], g: parts[1], b: parts[2], a: parts.length > 3 ? parts[3] : 1 };
    };
    const channel = (value) => {
      const normalized = value / 255;
      return normalized <= 0.04045
        ? normalized / 12.92
        : ((normalized + 0.055) / 1.055) ** 2.4;
    };
    const luminance = ({ r, g, b }) => 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
    const ratio = (foreground, background) => {
      const light = Math.max(luminance(foreground), luminance(background));
      const dark = Math.min(luminance(foreground), luminance(background));
      return (light + 0.05) / (dark + 0.05);
    };

    const style = getComputedStyle(element);
    const foreground = parse(style.color);
    let node = element;
    let background = null;
    while (node) {
      const nodeStyle = getComputedStyle(node);
      const candidate = parse(nodeStyle.backgroundColor);
      if (candidate.a >= 0.99) {
        background = candidate;
        break;
      }
      node = node.parentElement;
    }
    background ??= { r: 255, g: 255, b: 255, a: 1 };

    return {
      text: (element.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 120),
      color: style.color,
      background: `rgb(${background.r}, ${background.g}, ${background.b})`,
      fontSize: Number.parseFloat(style.fontSize),
      fontWeight: Number.parseInt(style.fontWeight, 10) || 400,
      contrast: ratio(foreground, background),
    };
  });
}

async function requireContrast(locator, label, minimum = 4.5) {
  const evidence = await contrastEvidence(locator, label);
  expect(evidence.contrast, `${label}: ${JSON.stringify(evidence)}`).toBeGreaterThanOrEqual(minimum);
  return evidence;
}

test('[QUALITY][CONTRAST] representative customer text and actions meet rendered contrast', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  const runtimeFailures = watchRuntime(page);
  const response = await page.goto('/');
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(400);

  await requireContrast(page.locator('.desktop-nav a').first(), 'desktop navigation');
  await requireContrast(page.locator('.hero-copy > p:not(.eyebrow)').first(), 'hero supporting copy');
  await requireContrast(page.locator('.hero-actions-row .button-primary'), 'hero primary action');

  await page.getByRole('button', { name: 'تسجيل الدخول' }).click();
  const dialog = page.getByRole('dialog', { name: 'تسجيل الدخول' });
  await expect(dialog).toBeVisible();
  await requireContrast(dialog.locator('p:not(.eyebrow)').first(), 'login dialog body');
  await requireContrast(dialog.getByRole('button', { name: 'متابعة كزائر' }), 'login dialog action');

  expect(runtimeFailures).toEqual([]);
});

test('[QUALITY][CONTRAST] admin login representative text and action meet rendered contrast', async ({ page }) => {
  await page.setViewportSize({ width: 430, height: 932 });
  const runtimeFailures = watchRuntime(page);
  const response = await page.goto('/admin/login');
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(400);

  await requireContrast(page.locator('.admin-login-copy'), 'admin login explanatory copy');
  await requireContrast(page.locator('.admin-field span').first(), 'admin login field label');
  await requireContrast(page.getByRole('button', { name: 'دخول آمن' }), 'admin login primary action');

  const metrics = await page.evaluate(() => ({
    documentWidth: document.documentElement.scrollWidth,
    viewportWidth: document.documentElement.clientWidth,
  }));
  expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
  expect(runtimeFailures).toEqual([]);
});
