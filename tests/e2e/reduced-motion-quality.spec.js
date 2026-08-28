import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/reduced-motion-quality');
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

function maxDurationMs(value) {
    return String(value)
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean)
        .map((part) => part.endsWith('ms') ? Number.parseFloat(part) : Number.parseFloat(part) * 1000)
        .reduce((maximum, duration) => Math.max(maximum, Number.isFinite(duration) ? duration : 0), 0);
}

async function readMotionStyles(page) {
    return page.evaluate(() => {
        const button = document.querySelector('.button');
        const discoveryImage = document.querySelector('.discovery-tile img');
        if (!button || !discoveryImage) {
            throw new Error('Expected representative motion-bearing Home controls were not rendered.');
        }

        return {
            scrollBehavior: getComputedStyle(document.documentElement).scrollBehavior,
            buttonTransitionDuration: getComputedStyle(button).transitionDuration,
            imageTransitionDuration: getComputedStyle(discoveryImage).transitionDuration,
        };
    });
}

test('[QUALITY][A11Y][MOTION] reduced-motion preference suppresses smooth scrolling and decorative transitions', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setViewportSize({ width: 390, height: 844 });
    const runtimeFailures = watchRuntime(page);

    await page.goto('/');
    const styles = await readMotionStyles(page);

    expect(styles.scrollBehavior).toBe('auto');
    expect(maxDurationMs(styles.buttonTransitionDuration)).toBeLessThanOrEqual(0.02);
    expect(maxDurationMs(styles.imageTransitionDuration)).toBeLessThanOrEqual(0.02);
    expect(runtimeFailures).toEqual([]);

    await page.screenshot({ path: path.join(outputDir, 'home-reduced-motion-390.png'), fullPage: true });
});

test('[QUALITY][A11Y][MOTION] no-preference preserves the accepted interaction motion', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.setViewportSize({ width: 390, height: 844 });
    const runtimeFailures = watchRuntime(page);

    await page.goto('/');
    const styles = await readMotionStyles(page);

    expect(styles.scrollBehavior).toBe('smooth');
    expect(maxDurationMs(styles.buttonTransitionDuration)).toBeGreaterThan(100);
    expect(maxDurationMs(styles.imageTransitionDuration)).toBeGreaterThan(100);
    expect(runtimeFailures).toEqual([]);
});
