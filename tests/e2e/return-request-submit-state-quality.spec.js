import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/return-request-submit-state-quality');
fs.mkdirSync(outputDir, { recursive: true });

function watchRuntime(page) {
  const failures = [];
  page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`));
  page.on('console', (message) => {
    if (message.type() === 'error') failures.push(`console.error: ${message.text()}`));
  });
  page.on('response', (response) => {
    if (response.status() >= 500) failures.push(`HTTP ${response.status()}: ${response.url()}`));
  });
  return failures;
}

async function createOrder(page) {
  await page.goto('/products/olive-velvet-lounge-chair');
  await page.locator('[data-option-key="color"][data-option-value="رملي"]').click();
  await page.getByTestId('add-to-cart').click();
  await page.goto('/checkout');

  await page.getByLabel('الاسم الكامل').fill('عميل مرتجع تجريبي');
  await page.getByLabel('البريد الإلكتروني').fill('return-submit@example.test');
  await page.getByLabel('رقم الجوال').fill('0500000008');
  await page.getByLabel('المنطقة / المحافظة').fill('الرياض');
  await page.getByLabel('المدينة').fill('الرياض');
  await page.getByLabel('الشارع / سطر العنوان').fill('شارع اختبار المرتجع 8');
  await page.getByRole('checkbox', { name: /أوافق على إرسال بيانات هذا الطلب/ }).check();
  await page.getByTestId('confirm-checkout').click();

  await expect(page).toHaveURL(/\/checkout\/confirmation\//);
  await page.getByRole('link', { name: 'عرض حالة الطلب' }).click();
  await expect(page).toHaveURL(/\/orders\/BAS-/);

  return page.url().match(/\/orders\/([^/?#]+)/)?.[1];
}

function grantEligibility(orderNumber) {
  const bootstrap = String.raw`
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    $order = App\\Models\\Order::query()->where('order_number', $argv[1])->firstOrFail();
    $line = $order->lines()->firstOrFail();
    App\\Models\\ReturnEligibility::query()->create([
      'order_id' => $order->id,
      'order_line_id' => $line->id,
      'eligible_quantity' => $line->quantity,
      'state' => 'active',
      'source_type' => 'authoritative_return_state',
      'source_reference' => 'E2E-SINGLE-SUBMIT-'.$order->order_number,
      'correlation_id' => (string) Illuminate\\Support\\Str::uuid(),
      'recorded_at' => now(),
    ]);
  `;

  execFileSync('php', ['-r', bootstrap, orderNumber], {
    cwd: process.cwd(),
    env: process.env,
    stdio: 'pipe',
  });
}

test('[QUALITY][S08 RETURN SUBMIT] eligible return request exposes a visible single-flight submitting state', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  const orderNumber = await createOrder(page);
  expect(orderNumber).toBeTruthy();

  grantEligibility(orderNumber);
  await page.goto(`/orders/${orderNumber}/returns`);
  await expect(page.getByTestId('returns-page')).toBeVisible();

  const form = page.getByTestId('return-request-form');
  const submit = page.getByTestId('start-return');
  const status = page.getByTestId('return-submitting-status');
  const quantity = page.getByLabel('كمية المرتجع');
  const reason = page.getByLabel('سبب المرتجع');
  await expect(form).toBeVisible();
  await submit.scrollIntoViewIfNeeded();

  const initialQuantity = await quantity.inputValue();
  const initialReason = await reason.inputValue();

  const firstSubmissionAccepted = await form.evaluate((element) => {
    const submitter = element.querySelector('[data-testid="start-return"]');
    return element.dispatchEvent(new SubmitEvent('submit', {
      bubbles: true,
      cancelable: true,
      submitter,
    }));
  });
  expect(firstSubmissionAccepted).toBe(true);

  await expect(form).toHaveAttribute('aria-busy', 'true');
  await expect(submit).toBeDisabled();
  await expect(submit).toHaveAttribute('aria-busy', 'true');
  await expect(submit).toHaveText('جارٍ تسجيل طلب المرتجع…');
  await expect(status).toBeVisible();
  await expect(status).toHaveText('جارٍ تسجيل طلب المرتجع. يرجى عدم إعادة الإرسال.');
  await expect(quantity).toBeEnabled();
  await expect(reason).toBeEnabled();

  const submittedPayload = await form.evaluate((element) => Object.fromEntries(new FormData(element).entries()));
  expect(submittedPayload.quantity).toBe(initialQuantity);
  expect(submittedPayload.reason).toBe(initialReason);
  expect(submittedPayload.line_ref).toBeTruthy();

  const secondSubmissionAccepted = await form.evaluate((element) => {
    const submitter = element.querySelector('[data-testid="start-return"]');
    return element.dispatchEvent(new SubmitEvent('submit', {
      bubbles: true,
      cancelable: true,
      submitter,
    }));
  });
  expect(secondSubmissionAccepted).toBe(false);

  const submitBox = await submit.boundingBox();
  expect(submitBox).not.toBeNull();
  expect(submitBox.y).toBeGreaterThanOrEqual(0);
  expect(submitBox.y + submitBox.height).toBeLessThanOrEqual(844);

  const statusBox = await status.boundingBox();
  expect(statusBox).not.toBeNull();
  expect(statusBox.y).toBeGreaterThanOrEqual(0);
  expect(statusBox.y + statusBox.height).toBeLessThanOrEqual(844);

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
  expect(runtimeFailures).toEqual([]);

  await page.screenshot({
    path: path.join(outputDir, 'return-request-submitting-state-390.png'),
    fullPage: false,
  });
});
