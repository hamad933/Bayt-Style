import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const workstream = 'RP01-IPA-EVIDENCE-ALL-PAGES-001';
const repository = 'hamad933/Bayt-Style';
const exactSha = /^[0-9a-f]{40}$/;
const baseline = process.env.RP01_ACCEPTED_PRODUCT_BASELINE;
const evidenceHead = process.env.RP01_EVIDENCE_HEAD || process.env.GITHUB_SHA;
const evidenceBranch = process.env.GITHUB_HEAD_REF || process.env.GITHUB_REF_NAME;
const outputDir = path.resolve('storage/test-artifacts/ipa-final-pages');
const recordsPath = path.join(outputDir, 'evidence-records.jsonl');
const defectsPath = path.join(outputDir, 'observed-potential-page-defects.jsonl');

if (!exactSha.test(baseline || '')) {
  throw new Error('RP01_ACCEPTED_PRODUCT_BASELINE must explicitly bind a 40-hex SHA before IPA evidence generation.');
}
if (!exactSha.test(evidenceHead || '')) {
  throw new Error('RP01_EVIDENCE_HEAD or GITHUB_SHA must explicitly bind a 40-hex SHA before IPA evidence generation.');
}
if (!evidenceBranch?.trim()) {
  throw new Error('GITHUB_HEAD_REF or GITHUB_REF_NAME must explicitly bind the IPA evidence ref before evidence generation.');
}

const viewports = [
  { width: 1440, height: 1000 },
  { width: 820, height: 1180 },
  { width: 768, height: 1024 },
  { width: 430, height: 932 },
  { width: 390, height: 844 },
];

const surfaces = [
  ['s01-home', '/'],
  ['s02-catalog', '/catalog'],
  ['s03-s04-product', '/products/olive-velvet-lounge-chair'],
  ['s05-wishlist', '/wishlist'],
  ['s05-comparison', '/comparison'],
  ['s06-cart', '/cart'],
  ['s06-checkout', '/checkout'],
  ['s06-confirmation', '/checkout/confirmation/{order:order_number}'],
  ['s07-order-status', '/orders/{order:order_number}'],
  ['s08-returns-refunds-credit', '/orders/{order:order_number}/returns'],
  ['s09-admin-login', '/admin/login'],
  ['s09-admin-catalog', '/admin/catalog'],
  ['s09-admin-product-inventory', '/admin/catalog/{product}/edit'],
  ['s10-admin-orders-index', '/admin/orders'],
  ['s10-admin-order-detail', '/admin/orders/{order:order_number}'],
];

fs.mkdirSync(outputDir, { recursive: true });
fs.writeFileSync(recordsPath, '');
fs.writeFileSync(defectsPath, '');

function artifactRelative(filePath) {
  return path.relative(process.cwd(), filePath).replaceAll('\\', '/');
}

function recordDefect(defect) {
  fs.appendFileSync(defectsPath, `${JSON.stringify({
    classification: 'OBSERVED_POTENTIAL_PAGE_DEFECT',
    ...defect,
  })}\n`);
}

async function reviewAndCapture(page, surface, route, state, viewport, screenshotPath, includePrimaryRecord = true) {
  await expect(page.locator('body')).toBeVisible();
  const metrics = await page.evaluate(() => ({
    lang: document.documentElement.lang,
    dir: document.documentElement.dir,
    documentWidth: document.documentElement.scrollWidth,
    viewportWidth: document.documentElement.clientWidth,
    text: document.body?.innerText || '',
  }));

  await page.screenshot({ path: screenshotPath, fullPage: true });
  const screenshot = artifactRelative(screenshotPath);
  const reachedPath = new URL(page.url()).pathname;
  const debugPattern = /Whoops, looks like something went wrong|Stack trace|Illuminate\\|Symfony\\Component\\ErrorHandler|Server Error/i;

  if (metrics.lang !== 'ar') {
    recordDefect({ surface, viewport: viewport.width, evidencePath: screenshot, description: `html lang is ${JSON.stringify(metrics.lang)} instead of "ar".` });
  }
  if (metrics.dir !== 'rtl') {
    recordDefect({ surface, viewport: viewport.width, evidencePath: screenshot, description: `html dir is ${JSON.stringify(metrics.dir)} instead of "rtl".` });
  }
  if (metrics.documentWidth > metrics.viewportWidth + 1) {
    recordDefect({
      surface,
      viewport: viewport.width,
      evidencePath: screenshot,
      description: `Horizontal document overflow observed: documentWidth=${metrics.documentWidth}px, viewportWidth=${metrics.viewportWidth}px.`,
    });
  }
  if (debugPattern.test(metrics.text)) {
    recordDefect({ surface, viewport: viewport.width, evidencePath: screenshot, description: 'Rendered body matched a server exception/debug-page marker.' });
  }

  if (includePrimaryRecord) {
    const record = {
      project: 'RP01 — Bayt & Style',
      workstream,
      repository,
      acceptedProductBaseline: baseline,
      evidenceBranch,
      exactEvidenceHead: evidenceHead,
      pageSurface: surface,
      route,
      reachedPath,
      state,
      viewportWidth: viewport.width,
      viewportHeight: viewport.height,
      screenshotPath: screenshot,
      htmlLang: metrics.lang,
      htmlDir: metrics.dir,
      documentWidth: metrics.documentWidth,
      documentViewportWidth: metrics.viewportWidth,
      horizontalOverflowObserved: metrics.documentWidth > metrics.viewportWidth + 1,
      serverExceptionDebugMarkerObserved: debugPattern.test(metrics.text),
      productApplicationBytesDifferFromAcceptedBaseline: false,
      testRunProvenance: {
        githubRunId: process.env.GITHUB_RUN_ID || null,
        githubRunAttempt: process.env.GITHUB_RUN_ATTEMPT || null,
        githubWorkflow: process.env.GITHUB_WORKFLOW || null,
      },
    };
    fs.appendFileSync(recordsPath, `${JSON.stringify(record)}\n`);
  }
}

async function capture(page, surface, route, state, viewport) {
  const screenshotPath = path.join(outputDir, `${surface}-${viewport.width}.png`);
  await reviewAndCapture(page, surface, route, state, viewport, screenshotPath, true);
}

async function captureSecondary(page, surface, route, state, viewport, filename) {
  const screenshotPath = path.join(outputDir, filename);
  await reviewAndCapture(page, surface, route, state, viewport, screenshotPath, false);
}

async function gotoAndAssert(page, route) {
  const response = await page.goto(route);
  expect(response, `navigation response for ${route}`).not.toBeNull();
  expect(response.status(), `HTTP status for ${route}`).toBeLessThan(400);
}

async function chooseSandChair(page) {
  await gotoAndAssert(page, '/products/olive-velvet-lounge-chair');
  const sand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
  await sand.click();
  await expect(sand).toHaveAttribute('aria-pressed', 'true');
  await expect(page.getByTestId('variant-sku')).toHaveText('BAS-CHAIR-SAND-01');
  await expect(page.getByTestId('variant-price')).toContainText('2,050');
  await expect(page.locator('[data-option-key="finish"][data-option-value="بلوط طبيعي"]')).toBeDisabled();
}

async function fillCheckout(page) {
  await page.getByLabel('الاسم الكامل').fill('عميل أدلة IPA تجريبي');
  await page.getByLabel('البريد الإلكتروني').fill('ipa-evidence@example.test');
  await page.getByLabel('رقم الجوال').fill('+966500000099');
  await page.getByLabel('المنطقة / المحافظة').fill('الرياض');
  await page.getByLabel('المدينة').fill('الرياض');
  await page.getByLabel('الحي اختياري').fill('حي الأدلة');
  await page.getByLabel('الشارع / سطر العنوان').fill('شارع الأدلة 99');
  await page.getByLabel('المبنى / الوحدة اختياري').fill('وحدة 9');
  await page.getByLabel('الرمز البريدي عند انطباقه').fill('00000');
  await page.locator('input[name="terms"]').check();
}

async function loginAdmin(page) {
  const email = process.env.S09_ADMIN_EMAIL;
  const password = process.env.S09_ADMIN_PASSWORD;
  if (!email || !password) throw new Error('S09_ADMIN_EMAIL and S09_ADMIN_PASSWORD are required for IPA evidence.');
  await page.getByLabel('البريد الإلكتروني').fill(email);
  await page.getByLabel('كلمة المرور').fill(password);
  await page.getByRole('button', { name: 'دخول آمن' }).click();
  await expect(page).toHaveURL(/\/admin\/catalog(?:\?|$)/);
}

for (const viewport of viewports) {
  test(`[IPA] all final primary surfaces at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);

    await gotoAndAssert(page, '/');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await capture(page, 's01-home', '/', 'normal-populated-home', viewport);

    await gotoAndAssert(page, '/catalog');
    await expect(page.getByTestId('catalog-search')).toBeVisible();
    await capture(page, 's02-catalog', '/catalog', 'populated-default-catalog', viewport);

    await chooseSandChair(page);
    await capture(page, 's03-s04-product', '/products/{product:slug}', 'multi-variant-valid-selected-sand-variant', viewport);

    const wishlist = page.getByTestId('detail-wishlist');
    await wishlist.click();
    await expect(wishlist).toHaveAttribute('aria-pressed', 'true');
    await gotoAndAssert(page, '/wishlist');
    await expect(page.getByTestId('wishlist-list')).toBeVisible();
    await capture(page, 's05-wishlist', '/wishlist', 'populated-wishlist', viewport);

    await gotoAndAssert(page, '/catalog');
    const comparisonButtons = page.getByTestId('comparison-toggle');
    await expect(comparisonButtons).toHaveCount(6);
    for (let index = 0; index < 3; index += 1) {
      await comparisonButtons.nth(index).click();
      await expect(comparisonButtons.nth(index)).toHaveAttribute('aria-pressed', 'true');
    }
    await gotoAndAssert(page, '/comparison');
    await expect(page.getByTestId('comparison-grid').locator('.comparison-item')).toHaveCount(3);
    await capture(page, 's05-comparison', '/comparison', 'populated-three-product-comparison', viewport);

    await chooseSandChair(page);
    await page.getByTestId('add-to-cart').click();
    await gotoAndAssert(page, '/cart');
    await expect(page.getByTestId('cart-line')).toContainText('BAS-CHAIR-SAND-01');
    await capture(page, 's06-cart', '/cart', 'populated-exact-sand-variant-cart', viewport);

    await page.getByTestId('proceed-checkout').click();
    await expect(page).toHaveURL(/\/checkout$/);
    await expect(page.getByTestId('checkout-page')).toContainText('BAS-CHAIR-SAND-01');
    await capture(page, 's06-checkout', '/checkout', 'populated-checkout-manual-payment-boundaries', viewport);

    await fillCheckout(page);
    await page.getByTestId('confirm-checkout').click();
    await expect(page).toHaveURL(/\/checkout\/confirmation\//);
    await expect(page.getByTestId('payment-state')).toHaveText('الدفع لم يكتمل بعد');
    await expect(page.getByTestId('reservation-state')).toHaveText('المخزون غير محجوز حتى الآن');
    await capture(page, 's06-confirmation', '/checkout/confirmation/{order:order_number}', 'created-order-pending-payment-no-reservation', viewport);

    await page.getByRole('link', { name: 'عرض حالة الطلب' }).click();
    await expect(page).toHaveURL(/\/orders\/BAS-/);
    await expect(page.getByTestId('status-payment')).toHaveText('الدفع لم يكتمل بعد');
    await expect(page.getByTestId('status-reservation')).toHaveText('المخزون غير محجوز حتى الآن');
    await expect(page.getByTestId('status-fulfillment')).toHaveText('تجهيز الطلب لم يبدأ بعد');
    await capture(page, 's07-order-status', '/orders/{order:order_number}', 'created-order-truthful-status', viewport);

    await page.getByTestId('open-returns').click();
    await expect(page).toHaveURL(/\/orders\/BAS-.*\/returns/);
    await expect(page.getByTestId('return-eligibility-state')).toContainText('طلب المرتجع غير متاح حاليًا');
    await expect(page.getByTestId('store-credit-balance')).toContainText('0.00');
    await capture(page, 's08-returns-refunds-credit', '/orders/{order:order_number}/returns', 'created-order-current-return-refund-credit-truth', viewport);

    await gotoAndAssert(page, '/admin/login');
    await expect(page.getByRole('button', { name: 'دخول آمن' })).toBeVisible();
    await capture(page, 's09-admin-login', '/admin/login', 'before-authentication', viewport);

    await loginAdmin(page);
    await expect(page.getByRole('heading', { name: 'حقيقة الكتالوج الحالية' })).toBeVisible();
    await capture(page, 's09-admin-catalog', '/admin/catalog', 'authenticated-catalog-index-before-edit', viewport);

    await page.getByLabel('بحث').fill('BAS-CHAIR-SAND-01');
    await page.getByRole('button', { name: 'تطبيق' }).click();
    const productRow = page.getByRole('row').filter({ hasText: 'BAS-CHAIR-SAND-01' });
    await expect(productRow).toHaveCount(1);
    await productRow.getByRole('link', { name: 'إدارة' }).click();
    await expect(page).toHaveURL(/\/admin\/catalog\/\d+\/edit$/);
    await expect(page.getByRole('heading', { name: 'خيارات البيع والمخزون' })).toBeVisible();
    await capture(page, 's09-admin-product-inventory', '/admin/catalog/{product}/edit', 'deterministic-product-variant-inventory-workspace', viewport);

    await page.getByRole('link', { name: 'الطلبات والمدفوعات والمرتجعات' }).click();
    await expect(page).toHaveURL(/\/admin\/orders(?:\?|$)/);
    await expect(page.getByRole('heading', { name: 'الطلبات والمدفوعات والمرتجعات' })).toBeVisible();
    await capture(page, 's10-admin-orders-index', '/admin/orders', 'authenticated-orders-index-before-detail', viewport);

    const orderRow = page.getByRole('row').filter({ hasText: 'BAS-S10-EVIDENCE' });
    await expect(orderRow).toHaveCount(1);
    await orderRow.getByRole('link', { name: 'فتح' }).click();
    await expect(page).toHaveURL(/\/admin\/orders\/BAS-S10-EVIDENCE$/);
    await expect(page.getByRole('heading', { name: 'الدفع' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'حالات الإرجاع' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'سجلات الاسترداد' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'قيود رصيد المتجر' })).toBeVisible();
    await capture(page, 's10-admin-order-detail', '/admin/orders/{order:order_number}', 'seeded-payment-return-refund-store-credit-truth', viewport);
  });
}

test('[IPA] bounded secondary state evidence', async ({ page }) => {
  const viewport = { width: 1440, height: 1000 };
  await page.setViewportSize(viewport);

  await gotoAndAssert(page, '/catalog?q=NO_SUCH_RP01_PRODUCT_001');
  await expect(page.getByTestId('product-card')).toHaveCount(0);
  await captureSecondary(page, 's02-catalog', '/catalog', 'no-results-search', viewport, 'secondary-catalog-no-results-1440.png');

  await chooseSandChair(page);
  await page.getByTestId('add-to-cart').click();
  await gotoAndAssert(page, '/checkout');
  await page.getByTestId('confirm-checkout').click();
  await expect(page).toHaveURL(/\/checkout$/);
  await captureSecondary(page, 's06-checkout', '/checkout', 'validation-errors', viewport, 'secondary-checkout-validation-errors-1440.png');
});

test.afterAll(async () => {
  const records = fs.readFileSync(recordsPath, 'utf8').trim().split('\n').filter(Boolean).map((line) => JSON.parse(line));
  const defects = fs.readFileSync(defectsPath, 'utf8').trim().split('\n').filter(Boolean).map((line) => JSON.parse(line));
  const expectedCount = surfaces.length * viewports.length;

  const matrix = {};
  for (const [surface] of surfaces) {
    matrix[surface] = {};
    for (const viewport of viewports) {
      const present = records.some((record) => record.pageSurface === surface && record.viewportWidth === viewport.width);
      matrix[surface][String(viewport.width)] = present ? 'PRESENT' : 'MISSING';
    }
  }

  const manifest = {
    project: 'RP01 — Bayt & Style',
    workstream,
    repository,
    acceptedProductBaseline: baseline,
    evidenceBranch,
    exactEvidenceHead: evidenceHead,
    productApplicationBytes: 'UNCHANGED_FROM_ACCEPTED_MAIN',
    primaryEvidenceCount: records.length,
    expectedPrimaryEvidenceCount: expectedCount,
    viewports,
    coverageMatrix: matrix,
    secondaryStateEvidence: [
      { surface: 's02-catalog', state: 'no-results-search', screenshotPath: 'storage/test-artifacts/ipa-final-pages/secondary-catalog-no-results-1440.png' },
      { surface: 's06-checkout', state: 'validation-errors', screenshotPath: 'storage/test-artifacts/ipa-final-pages/secondary-checkout-validation-errors-1440.png' },
    ],
    missingRenderedStateEvidence: [
      'product-unavailable-disabled-variant dedicated secondary screenshot not captured; disabled option remains visible in the primary product evidence',
      'empty-cart dedicated secondary screenshot not captured',
      'admin empty/error dedicated secondary screenshot not captured',
    ],
    observedPotentialPageDefects: defects,
    records,
  };

  fs.writeFileSync(path.join(outputDir, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
  fs.writeFileSync(path.join(outputDir, 'evidence-index.json'), `${JSON.stringify(records, null, 2)}\n`);
  fs.writeFileSync(path.join(outputDir, 'observed-potential-page-defects.json'), `${JSON.stringify(defects, null, 2)}\n`);

  const header = '| Page / Surface | 1440 | 820 | 768 | 430 | 390 |\n|---|---:|---:|---:|---:|---:|\n';
  const rows = surfaces.map(([surface]) => `| ${surface} | ${matrix[surface]['1440']} | ${matrix[surface]['820']} | ${matrix[surface]['768']} | ${matrix[surface]['430']} | ${matrix[surface]['390']} |`).join('\n');
  fs.writeFileSync(path.join(outputDir, 'coverage-matrix.md'), `${header}${rows}\n`);

  if (records.length !== expectedCount) throw new Error(`Expected ${expectedCount} primary evidence records, found ${records.length}.`);
});
