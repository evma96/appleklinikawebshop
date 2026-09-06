// Read-only LOCAL browser regression. Requires Playwright on NODE_PATH.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const output = process.env.HEADER_QA_OUTPUT;
if (!output) throw new Error('Set HEADER_QA_OUTPUT to an ignored local artifact directory.');
fs.mkdirSync(output, { recursive: true });
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; }

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH });
  const results = [];
  try {
    for (const width of [320, 390, 768, 1024, 1440]) {
      const context = await browser.newContext({ viewport: { width, height: 1000 } });
      const page = await context.newPage();
      const errors = [];
      const knownResourceWarnings = [];
      page.on('pageerror', error => errors.push(error.message));
      page.on('console', message => {
        if (message.type() !== 'error') return;
        const detail = message.text() + ' ' + message.location().url;
        // Pre-existing LOCAL favicon 404 is recorded in deficiencies.md, not a header regression.
        if (message.location().url === 'http://localhost:8080/favicon.ico' && message.text().includes('404')) knownResourceWarnings.push(detail);
        else errors.push(detail);
      });
      await page.goto('http://localhost:8080/?post_type=product&p=314');
      const header = page.locator('.ak-header-shell');
      await header.locator('.ak-header-logo img').evaluate(img => img.decode());
      check(await header.locator('.ak-account-link').count() === 1, 'One account link');
      check(await header.locator('.ak-cart-link').count() === 1, 'One cart link');
      check(await header.locator('.ak-header-sell-link').count() === 1, 'One sell link');
      check(await header.locator('.ak-category-nav a').count() === 4, 'Four category links');
      const controls = header.locator('.ak-header-pill, .ak-category-nav a, .ak-product-search');
      const rectangles = await controls.evaluateAll(nodes => nodes.map(node => {
        const r = node.getBoundingClientRect();
        return { label: node.textContent.trim(), x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom };
      }));
      for (const r of rectangles) {
        check(r.height >= 44 && r.width >= 44, 'Accessible target: ' + r.label);
        check(r.x >= 0 && r.right <= width + 1, 'Control inside viewport: ' + r.label);
      }
      for (let i = 0; i < rectangles.length; i++) for (let j = i + 1; j < rectangles.length; j++) {
        const a = rectangles[i], b = rectangles[j];
        check(a.right <= b.x + 1 || b.right <= a.x + 1 || a.bottom <= b.y + 1 || b.bottom <= a.y + 1, 'Controls do not overlap');
      }
      const actions = await header.locator('.ak-header-pill').evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().y));
      check(width <= 639 || Math.max(...actions) - Math.min(...actions) < 1, 'Desktop/tablet actions share a row');
      const badge = header.locator('.ak-cart-count');
      check(await badge.isVisible() && /^\d+$/.test(await badge.innerText()), 'Visible numeric Woo cart badge');
      check(!await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), 'No horizontal overflow');
      await page.screenshot({ path: path.join(output, 'after-' + width + '.png') });
      await page.mouse.wheel(0, 500);
      await page.waitForFunction(() => document.body.classList.contains('ak-header-hidden'));
      check(await page.locator('.ak-site-header').evaluate(node => getComputedStyle(node).position === 'sticky'), 'Existing sticky header retained');
      await page.mouse.wheel(0, -500);
      await page.waitForFunction(() => !document.body.classList.contains('ak-header-hidden'));
      check(!await page.locator('body').evaluate(node => node.classList.contains('ak-header-hidden')), 'Existing scroll-up reveal retained');
      // Native search submit; no input-value or application state scripting.
      await header.locator('input[name="s"]').fill('iPhone');
      await Promise.all([page.waitForURL(url => url.searchParams.get('s') === 'iPhone'), header.locator('button[type="submit"]').click()]);
      check(new URL(page.url()).searchParams.get('post_type') === 'product', 'Search remains product-scoped');
      if (width === 1440 || width === 390) {
        // Read-only navigation through the actual controls, without login/cart writes.
        for (const selector of ['.ak-account-link', '.ak-cart-link', '.ak-header-sell-link', ...[1, 2, 3, 4].map(n => '.ak-category-nav a:nth-of-type(' + n + ')')]) {
          await page.goto('http://localhost:8080/');
          const link = page.locator('.ak-header-shell ' + selector);
          const destination = await link.getAttribute('href');
          check(new URL(destination).origin === 'http://localhost:8080', 'Local original destination');
          await Promise.all([page.waitForURL(destination), link.click()]);
          check(await page.locator('body').isVisible(), 'Destination renders: ' + selector);
        }
      }
      check(errors.length === 0, 'No new console/runtime errors: ' + errors.join('; '));
      results.push({ width, result: 'PASS', rectangles, errors, knownResourceWarnings });
      await context.close();
      console.log('Header browser PASS: ' + width + 'px');
    }
    fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({ checks, results }, null, 2));
    console.log('Header browser passed: ' + checks + ' assertions.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
