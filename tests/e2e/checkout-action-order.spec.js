import { expect, test } from '@playwright/test';

async function addConfiguredChair(page) {
  await page.goto('/products/olive-velvet-lounge-chair');
  const sand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
  await expect(sand).toHaveAttribute('aria-pressed', 'false');
  await sand.click();
  await expect(sand).toHaveAttribute('aria-pressed', 'true');
  await page.getByTestId('add-to-cart').click();
  await expect(page.locator('.cart-badge')).toHaveText('1');
  await page.goto('/cart');
  await page.getByTestId('proceed-checkout').click();
  await expect(page).toHaveURL(/\/checkout$/);
}

for (const width of [820, 390]) {
  test(`[QUALITY] checkout final action follows required inputs visually at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: width === 390 ? 844 : 1100 });
    await addConfiguredChair(page);

    const firstRequired = page.getByLabel('الاسم الكامل');
    const consent = page.locator('input[name="terms"]');
    const submit = page.getByTestId('confirm-checkout');

    await expect(firstRequired).toBeVisible();
    await expect(consent).toBeVisible();
    await expect(submit).toBeVisible();

    const firstBox = await firstRequired.boundingBox();
    const consentBox = await consent.boundingBox();
    const submitBox = await submit.boundingBox();

    expect(firstBox).not.toBeNull();
    expect(consentBox).not.toBeNull();
    expect(submitBox).not.toBeNull();
    expect(firstBox.y, 'first required contact field must appear before final submit action').toBeLessThan(submitBox.y);
    expect(consentBox.y, 'final consent must appear before final submit action').toBeLessThan(submitBox.y);
  });
}
