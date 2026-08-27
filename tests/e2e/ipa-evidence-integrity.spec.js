import { expect, test } from '@playwright/test';

const exactSha = /^[0-9a-f]{40}$/;

test('[IPA] canonical evidence provenance is exact-bound', async () => {
  const acceptedBaseline = process.env.RP01_ACCEPTED_PRODUCT_BASELINE;
  const evidenceHead = process.env.RP01_EVIDENCE_HEAD;
  const evidenceRef = process.env.GITHUB_HEAD_REF || process.env.GITHUB_REF_NAME;

  expect(acceptedBaseline, 'RP01_ACCEPTED_PRODUCT_BASELINE must be explicitly bound').toMatch(exactSha);
  expect(evidenceHead, 'RP01_EVIDENCE_HEAD must be explicitly bound').toMatch(exactSha);
  expect(evidenceRef, 'GitHub evidence ref must be explicitly available').toBeTruthy();

  // Canonical evidence must never silently fall back to the historical S10 baseline.
  expect(acceptedBaseline).not.toBe('905556f073b7fa853148aa57d0d6b6524192a3ef');
});

test('[IPA] product Variant control is hydrated before exact selection', async ({ page }) => {
  const response = await page.goto('/products/olive-velvet-lounge-chair');
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(400);

  const sand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
  await expect(sand).toHaveAttribute('aria-pressed', 'false');
  await sand.click();
  await expect(sand).toHaveAttribute('aria-pressed', 'true');
  await expect(page.getByTestId('variant-sku')).toHaveText('BAS-CHAIR-SAND-01');
  await expect(page.getByTestId('variant-price')).toContainText('2,050');
  await expect(page.locator('[data-option-key="finish"][data-option-value="بلوط طبيعي"]')).toBeDisabled();
});
