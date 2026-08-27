import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const sourcePath = path.resolve('tests/e2e/ipa-final-pages.spec.js');
const historicalS10 = '905556f073b7fa853148aa57d0d6b6524192a3ef';
const historicalEvidenceBranch = 'chore/rp01-ipa-evidence-all-pages';

test('[IPA] evidence source has no historical or unknown provenance fallback', () => {
  const source = fs.readFileSync(sourcePath, 'utf8');

  expect(source).not.toContain(historicalS10);
  expect(source).not.toContain(historicalEvidenceBranch);
  expect(source).not.toContain("|| 'UNKNOWN'");
  expect(source).toContain('const baseline = process.env.RP01_ACCEPTED_PRODUCT_BASELINE;');
  expect(source).toContain('const evidenceHead = process.env.RP01_EVIDENCE_HEAD || process.env.GITHUB_SHA;');
  expect(source).toContain('const evidenceBranch = process.env.GITHUB_HEAD_REF || process.env.GITHUB_REF_NAME;');
});

test('[IPA] provenance validation occurs before evidence directory mutation', () => {
  const source = fs.readFileSync(sourcePath, 'utf8');
  const firstValidation = source.indexOf("if (!exactSha.test(baseline || ''))");
  const headValidation = source.indexOf("if (!exactSha.test(evidenceHead || ''))");
  const refValidation = source.indexOf('if (!evidenceBranch?.trim())');
  const firstArtifactMutation = source.indexOf('fs.mkdirSync(outputDir');

  expect(firstValidation).toBeGreaterThan(-1);
  expect(headValidation).toBeGreaterThan(firstValidation);
  expect(refValidation).toBeGreaterThan(headValidation);
  expect(firstArtifactMutation).toBeGreaterThan(refValidation);
});
