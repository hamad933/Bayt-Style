import { expect, test } from '@playwright/test';

const publicSurfaces = [
  ['home', '/'],
  ['catalog', '/catalog'],
  ['product', '/products/olive-velvet-lounge-chair'],
  ['wishlist', '/wishlist'],
  ['comparison', '/comparison'],
  ['cart', '/cart'],
  ['admin-login', '/admin/login'],
];

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

async function assertDocumentQuality(page, surface) {
  const defects = await page.evaluate(() => {
    const defects = [];
    const visible = (element) => {
      const style = window.getComputedStyle(element);
      return style.display !== 'none' && style.visibility !== 'hidden' && element.getClientRects().length > 0;
    };
    const labelledByText = (element) => (element.getAttribute('aria-labelledby') || '')
      .split(/\s+/)
      .filter(Boolean)
      .map((id) => document.getElementById(id)?.textContent?.trim() || '')
      .join(' ')
      .trim();
    const labelText = (element) => Array.from(element.labels || [])
      .map((label) => label.textContent?.trim() || '')
      .join(' ')
      .trim();
    const accessibleName = (element) => (
      element.getAttribute('aria-label')?.trim()
      || labelledByText(element)
      || labelText(element)
      || element.getAttribute('alt')?.trim()
      || element.getAttribute('title')?.trim()
      || element.textContent?.trim()
      || ''
    );

    if (document.documentElement.lang !== 'ar') defects.push(`html lang=${JSON.stringify(document.documentElement.lang)}`);
    if (document.documentElement.dir !== 'rtl') defects.push(`html dir=${JSON.stringify(document.documentElement.dir)}`);
    if (!document.title.trim()) defects.push('document title is empty');

    const ids = Array.from(document.querySelectorAll('[id]')).map((element) => element.id).filter(Boolean);
    const duplicateIds = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))];
    if (duplicateIds.length) defects.push(`duplicate ids: ${duplicateIds.join(', ')}`);

    for (const image of document.querySelectorAll('img')) {
      if (!visible(image)) continue;
      if (!image.hasAttribute('alt')) defects.push(`visible image missing alt: ${image.getAttribute('src') || '<dynamic>'}`);
    }

    for (const control of document.querySelectorAll('input, select, textarea')) {
      if (!visible(control) || control.type === 'hidden') continue;
      if (!accessibleName(control)) defects.push(`unnamed form control: ${control.tagName.toLowerCase()}#${control.id || '<no-id>'}`);
    }

    for (const interactive of document.querySelectorAll('button, a[href]')) {
      if (!visible(interactive)) continue;
      if (!accessibleName(interactive)) defects.push(`unnamed interactive element: ${interactive.tagName.toLowerCase()}#${interactive.id || '<no-id>'}`);
    }

    return defects;
  });

  expect(defects, `${surface} accessibility/semantic defects`).toEqual([]);
}

for (const [surface, route] of publicSurfaces) {
  test(`[QUALITY] ${surface} has accessible semantics and no runtime failures`, async ({ page }) => {
    const runtimeFailures = watchRuntime(page);
    const response = await page.goto(route);
    expect(response, `${surface} navigation response`).not.toBeNull();
    expect(response.status(), `${surface} HTTP status`).toBeLessThan(400);
    await expect(page.locator('body')).toBeVisible();
    await assertDocumentQuality(page, surface);
    expect(runtimeFailures, `${surface} runtime failures`).toEqual([]);
  });
}

test('[QUALITY] customer shell preserves keyboard entry and modal focus', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  const runtimeFailures = watchRuntime(page);
  await page.goto('/');

  const skipLink = page.locator('.skip-link');
  await page.keyboard.press('Tab');
  await expect(skipLink).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#main-content')).toBeFocused();

  const loginTrigger = page.getByRole('button', { name: 'تسجيل الدخول' }).first();
  await loginTrigger.focus();
  await loginTrigger.click();
  const loginDialog = page.getByRole('dialog', { name: 'تسجيل الدخول' });
  await expect(loginDialog).toBeVisible();
  await expect(loginDialog.getByRole('button', { name: 'إغلاق' })).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(loginDialog).toBeHidden();
  await expect(loginTrigger).toBeFocused();

  const cartTrigger = page.getByRole('button', { name: 'فتح السلة' });
  await cartTrigger.focus();
  await cartTrigger.click();
  const cartDialog = page.getByRole('dialog', { name: 'مختاراتك الحالية' });
  await expect(cartDialog).toBeVisible();
  await expect(cartDialog.getByRole('button', { name: 'إغلاق السلة' })).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(cartDialog).toBeHidden();
  await expect(cartTrigger).toBeFocused();

  const brand = page.locator('.brand');
  await brand.focus();
  await page.keyboard.press('Escape');
  await expect(brand).toBeFocused();

  expect(runtimeFailures, 'customer shell runtime failures').toEqual([]);
});

test('[QUALITY] mobile menu restores trigger focus after Escape', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  await page.goto('/');

  const menuTrigger = page.getByRole('button', { name: 'فتح القائمة' });
  await menuTrigger.focus();
  await menuTrigger.click();
  const mobileNav = page.locator('#mobile-nav');
  await expect(mobileNav).toBeVisible();

  const firstLink = mobileNav.getByRole('link', { name: 'المنتجات' });
  await firstLink.focus();
  await expect(firstLink).toBeFocused();
  await page.keyboard.press('Escape');

  await expect(mobileNav).toBeHidden();
  await expect(menuTrigger).toBeFocused();
  expect(runtimeFailures, 'mobile menu runtime failures').toEqual([]);
});
