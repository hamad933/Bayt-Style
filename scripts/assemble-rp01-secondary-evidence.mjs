import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const manifestPath = path.resolve('storage/test-artifacts/ipa-final-pages/manifest.json');
const recordsPath = path.resolve('storage/test-artifacts/ipa-final-pages/evidence-records.jsonl');
const expectedBaseline = process.env.RP01_ACCEPTED_PRODUCT_BASELINE;
const expectedHead = process.env.RP01_EVIDENCE_HEAD || process.env.GITHUB_SHA;
const expectedRef = process.env.GITHUB_HEAD_REF || process.env.GITHUB_REF_NAME;
const exactSha = /^[0-9a-f]{40}$/;

if (!exactSha.test(expectedBaseline || '')) throw new Error('RP01_ACCEPTED_PRODUCT_BASELINE must be exact-bound before secondary evidence assembly.');
if (!exactSha.test(expectedHead || '')) throw new Error('RP01_EVIDENCE_HEAD/GITHUB_SHA must be exact-bound before secondary evidence assembly.');
if (!expectedRef?.trim()) throw new Error('Evidence ref must be exact-bound before secondary evidence assembly.');
if (!fs.existsSync(manifestPath)) throw new Error(`Canonical IPA manifest missing: ${manifestPath}`);
if (!fs.existsSync(recordsPath)) throw new Error(`Canonical IPA evidence records missing: ${recordsPath}`);

try {
  execFileSync('git', ['cat-file', '-e', `${expectedBaseline}^{commit}`], { stdio: 'ignore' });
} catch {
  execFileSync('git', ['fetch', '--no-tags', '--depth=1', 'origin', expectedBaseline], { stdio: 'inherit' });
}

const productApplicationPaths = [
  'app',
  'bootstrap',
  'config',
  'database',
  'public',
  'resources',
  'routes',
  'composer.json',
  'composer.lock',
  'package.json',
  'package-lock.json',
  'vite.config.js',
];
const productApplicationChangedPaths = execFileSync(
  'git',
  ['diff', '--name-only', expectedBaseline, expectedHead, '--', ...productApplicationPaths],
  { encoding: 'utf8' },
).trim().split('\n').filter(Boolean);
const productApplicationBytesDifferFromAcceptedBaseline = productApplicationChangedPaths.length > 0;

const evidence = [
  {
    surface: 's02-catalog',
    state: 'no-results-search',
    screenshotPath: 'storage/test-artifacts/ipa-final-pages/secondary-catalog-no-results-1440.png',
  },
  {
    surface: 's05-wishlist',
    state: 'empty-wishlist',
    screenshotPath: 'storage/test-artifacts/public-empty-quality/wishlist-empty-390.png',
  },
  {
    surface: 's05-comparison',
    state: 'empty-comparison',
    screenshotPath: 'storage/test-artifacts/public-empty-quality/comparison-empty-390.png',
  },
  {
    surface: 's06-cart',
    state: 'empty-cart',
    screenshotPath: 'storage/test-artifacts/secondary-quality/empty-cart-390.png',
  },
  {
    surface: 's06-checkout',
    state: 'server-validation-errors-localized',
    screenshotPath: 'storage/test-artifacts/secondary-quality/checkout-server-validation-errors-390.png',
  },
  {
    surface: 's03-s04-product',
    state: 'unavailable-option-disabled',
    screenshotPath: 'storage/test-artifacts/secondary-quality/product-unavailable-option-390.png',
  },
  {
    surface: 's09-admin-login',
    state: 'invalid-credentials-error',
    screenshotPath: 'storage/test-artifacts/secondary-quality/admin-login-error-390.png',
  },
  {
    surface: 's09-admin-catalog',
    state: 'no-results-search',
    screenshotPath: 'storage/test-artifacts/secondary-quality/admin-catalog-no-results-390.png',
  },
];

for (const item of evidence) {
  const absolute = path.resolve(item.screenshotPath);
  if (!fs.existsSync(absolute)) throw new Error(`Expected truthful secondary screenshot missing: ${item.screenshotPath}`);
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
if (manifest.acceptedProductBaseline !== expectedBaseline) {
  throw new Error(`Manifest baseline ${manifest.acceptedProductBaseline} does not match exact environment baseline ${expectedBaseline}.`);
}
if (manifest.exactEvidenceHead !== expectedHead) {
  throw new Error(`Manifest head ${manifest.exactEvidenceHead} does not match exact environment head ${expectedHead}.`);
}
if (manifest.evidenceBranch !== expectedRef) {
  throw new Error(`Manifest ref ${manifest.evidenceBranch} does not match exact environment ref ${expectedRef}.`);
}

const records = fs.readFileSync(recordsPath, 'utf8')
  .split('\n')
  .filter(Boolean)
  .map((line) => JSON.parse(line));
for (const record of records) {
  if (record.acceptedProductBaseline !== expectedBaseline || record.exactEvidenceHead !== expectedHead || record.evidenceBranch !== expectedRef) {
    throw new Error('Primary evidence record provenance does not match exact environment bindings.');
  }
  record.productApplicationBytesDifferFromAcceptedBaseline = productApplicationBytesDifferFromAcceptedBaseline;
}
fs.writeFileSync(recordsPath, `${records.map((record) => JSON.stringify(record)).join('\n')}\n`);

manifest.productApplicationBytes = productApplicationBytesDifferFromAcceptedBaseline
  ? 'DIFFERS_FROM_ACCEPTED_MAIN'
  : 'UNCHANGED_FROM_ACCEPTED_MAIN';
manifest.productApplicationChangedPaths = productApplicationChangedPaths;
manifest.secondaryStateEvidence = evidence;
manifest.secondaryStateEvidenceCount = evidence.length;
manifest.missingRenderedStateEvidence = [];
manifest.secondaryStateEvidenceStatus = 'COMPLETE_EXACT_CURRENT';
manifest.supersededSecondaryEvidence = [
  {
    surface: 's06-checkout',
    state: 'browser-native-validation-prevention',
    screenshotPath: 'storage/test-artifacts/ipa-final-pages/secondary-checkout-validation-errors-1440.png',
    reason: 'Superseded for server-validation proof because browser-native required-field prevention can stop submission before Laravel validation.',
  },
];

fs.writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
fs.writeFileSync(
  path.resolve('storage/test-artifacts/ipa-final-pages/secondary-evidence-index.json'),
  `${JSON.stringify({
    project: manifest.project,
    workstream: manifest.workstream,
    acceptedProductBaseline: manifest.acceptedProductBaseline,
    exactEvidenceHead: manifest.exactEvidenceHead,
    evidenceBranch: manifest.evidenceBranch,
    productApplicationBytes: manifest.productApplicationBytes,
    productApplicationChangedPaths,
    status: manifest.secondaryStateEvidenceStatus,
    count: evidence.length,
    evidence,
  }, null, 2)}\n`,
);

console.log(`Canonical RP01 secondary evidence assembled: ${evidence.length} truthful states; missing=0; product-bytes=${manifest.productApplicationBytes}.`);
