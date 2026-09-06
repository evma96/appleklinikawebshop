/* LOCAL-only interaction gate. Fixtures replace rendered gallery HTML in the response;
 * they never save products/media. See docs/qa/product-gallery-ux.md. */
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.BASE_URL || 'http://localhost:8080';
assert.equal(new URL(base).hostname, 'localhost', 'This gate is local only.');
const evidence = process.env.EVIDENCE_DIR || fs.mkdtempSync('/tmp/product-gallery-');
fs.mkdirSync(evidence, {recursive: true});
const fixture = process.env.GALLERY_FIXTURE_HTML;
assert(fixture, 'Provide the unsaved multi-image PHP renderer fixture.');
const scenarios = [{name: 'single-square', id: 476}, {name: 'single-portrait', id: 288}, {name: 'multi', id: 288, fixture: true}];
let checks = 0;
const check = (condition, message) => {checks++; assert(condition, message);};
(async () => {
 const browser = await chromium.launch({headless: true, ...(process.env.CHROME_EXECUTABLE ? {executablePath: process.env.CHROME_EXECUTABLE} : {})});
 try {for (const scenario of scenarios) for (const width of [1440, 390]) {
  const context = await browser.newContext({viewport: {width, height: 1000}, hasTouch: width === 390});
  const page = await context.newPage(), errors = [], requests = [], unrelatedNoise = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', msg => {
   if (msg.type() !== 'error') return;
   const detail = msg.text() + ' ' + JSON.stringify(msg.location());
   // Observed before and after: this LOCAL site has no favicon.ico. Do not change
   // unrelated site-icon configuration, hide other errors or mislabel this as gallery failure.
   if (msg.location().url === base + '/favicon.ico' && msg.text().includes('404')) unrelatedNoise.push(detail);
   else errors.push(detail);
  });
  page.on('request', r => {if (r.resourceType() === 'image') requests.push(r.url());});
  if (scenario.fixture) await page.route(base + '/?post_type=product&p=288', async route => {
   const response = await route.fetch(), html = await response.text();
   const boundary = /<div class="appleklinika-product-gallery[\s\S]*?(?=<aside class="appleklinika-buy-panel)/;
   assert(boundary.test(html), 'Known rendered gallery boundary required.');
   await route.fulfill({response, body: html.replace(boundary, fs.readFileSync(fixture, 'utf8'))});
  });
  const shot = async state => page.screenshot({path: path.join(evidence, `${scenario.name}-${width}-${state}.png`)});
  await page.goto(base + '/?post_type=product&p=' + scenario.id);
  const gallery = page.locator('[data-gallery-images]'), opener = gallery.locator('[data-gallery-open]');
  await gallery.waitFor(); await gallery.scrollIntoViewIfNeeded();
  await page.locator('[data-appleklinika-stage-image]').evaluate(img => img.decode());
  const images = await gallery.evaluate(g => JSON.parse(g.dataset.galleryImages));
  check(images.length === (scenario.fixture ? 3 : 1), 'Expected real image count.');
  check(await gallery.locator('.appleklinika-product-gallery__thumbs').isVisible() === !!scenario.fixture, 'No single-image thumbnail/navigation noise.');
  const initialBox = await gallery.locator('.appleklinika-product-gallery__stage').boundingBox();
  check(Math.abs(initialBox.width - initialBox.height) < 1, 'Stable square stage.');
  check(await page.locator('[data-appleklinika-stage-image]').getAttribute('srcset'), 'WP responsive sources retained.');
  check(!images.some(image => requests.includes(image.full)), 'No full-resolution gallery originals fetched before opening.');
  await shot('normal');
  if (scenario.fixture) {
   await gallery.locator('[data-gallery-index="1"]').click();
   check(await gallery.locator('[data-gallery-index="1"]').getAttribute('aria-pressed') === 'true', 'Selected thumbnail exposed.');
   check(Math.abs((await gallery.locator('.appleklinika-product-gallery__stage').boundingBox()).height - initialBox.height) < 1, 'Thumbnail switch has no gallery layout shift.');
  }
  const scrollBefore = await page.evaluate(() => scrollY);
  await opener.click();
  const modal = page.locator('dialog.ak-image-viewer'); await modal.waitFor({state: 'visible'});
  await modal.locator('img').evaluate(img => img.decode());
  await page.waitForFunction(() => !document.querySelector('.ak-image-viewer img').hidden);
  check(await modal.evaluate(d => d.matches(':modal')), 'Native modal is in the top layer.');
  const bounds = await modal.boundingBox();
  check(bounds.x === 0 && bounds.y === 0 && bounds.width === width && bounds.height === 1000, 'Overlay covers full viewport.');
  check(await modal.locator('[data-direction="1"]').isVisible() === !!scenario.fixture, 'Viewer navigation only when useful.');
  check(await modal.locator('img').getAttribute('src') === images[scenario.fixture ? 1 : 0].full, 'Selected original loads only in opened viewer.');
  check(await modal.locator('img').evaluate(img => Math.abs(parseFloat(img.style.width) / parseFloat(img.style.height) - img.naturalWidth / img.naturalHeight) < .001), 'Decoded aspect ratio wins over stale attachment metadata.');
  check(await modal.locator('[data-close]').evaluate(el => el.getBoundingClientRect().width >= 44 && el.getBoundingClientRect().height >= 44), 'Mobile-sized close target.');
  check(await modal.locator('[data-close]').evaluate(el => el === document.activeElement), 'Close receives focus.');
  await shot('open');
  if (scenario.fixture) {
   await modal.locator('[data-direction="1"]').click();
   check(await modal.locator('.ak-image-viewer__count').innerText() === '3 / 3', 'Next navigation.');
   await page.keyboard.press('ArrowLeft');
   check(await modal.locator('.ak-image-viewer__count').innerText() === '2 / 3', 'Keyboard previous.');
   await modal.locator('[data-direction="-1"]').click();
   check(await modal.locator('.ak-image-viewer__count').innerText() === '1 / 3', 'Previous button.');
   const surface = await modal.locator('.ak-image-viewer__canvas').boundingBox();
   await page.mouse.move(surface.x + surface.width * .8, surface.y + surface.height / 2);
   await page.mouse.down(); await page.mouse.move(surface.x + surface.width * .2, surface.y + surface.height / 2, {steps: 8}); await page.mouse.up();
   check(await modal.locator('.ak-image-viewer__count').innerText() === '2 / 3', 'Pointer swipe advances once.');
   await modal.locator('img').evaluate(img => img.decode());
   await page.waitForFunction(() => !document.querySelector('.ak-image-viewer img').hidden);
   await shot('navigation');
  }
  await modal.locator('[data-zoom]').click();
  check(await modal.locator('.ak-image-viewer__canvas').getAttribute('data-zoomed') === 'true', 'Zoom active.');
  await modal.locator('[data-zoom]').click();
  const img = modal.locator('img'), beforePan = await img.getAttribute('style');
  const surface = await modal.locator('.ak-image-viewer__canvas').boundingBox();
  await page.mouse.move(width / 2, surface.y + surface.height / 2);await page.mouse.down();
  await page.mouse.move(width / 2 + 80, surface.y + surface.height / 2 + 60, {steps: 8});await page.mouse.up();
  check(await img.getAttribute('style') !== beforePan, 'Zoomed image can be dragged within bounds.');
  await shot('zoom');
  await page.setViewportSize({width: width === 390 ? 844 : 900, height: 600});
  check(await modal.isVisible(), 'Viewer survives orientation/viewport change.');
  await page.setViewportSize({width, height: 1000});
  await modal.locator('[data-fit]').click();
  check(await modal.locator('.ak-image-viewer__canvas').getAttribute('data-zoomed') === 'false', 'Explicit return to fit.');
  check(await modal.locator('[data-zoom]').evaluate(el => el === document.activeElement), 'Fit reset keeps focus on a visible control.');
  if (width === 390) {
   const target = await img.boundingBox();
   await page.touchscreen.tap(target.x + target.width / 2, target.y + target.height / 2);
   check(await modal.locator('.ak-image-viewer__canvas').getAttribute('data-zoomed') === 'true', 'Native mobile tap zooms the real image.');
   await modal.locator('[data-fit]').tap();
  }
  await modal.locator('[data-close]').focus();await page.keyboard.press('Tab');
  check(await modal.evaluate(d => d.contains(document.activeElement)), 'Focus stays inside modal.');
  for (let i = 0; i < 7; i++) await page.keyboard.press('Tab');
  check(await modal.evaluate(d => d.contains(document.activeElement)), 'Repeated Tab cannot reach the background.');
  await modal.locator('[data-zoom]').focus();await page.keyboard.press('Shift+Tab');
  check(await modal.evaluate(d => d.contains(document.activeElement)), 'Reverse Tab wraps inside modal.');
  await page.keyboard.press('Escape');
  check(!await modal.isVisible(), 'ESC closes.');
  check(await opener.evaluate(el => el === document.activeElement), 'Opener focus restored.');
  check(Math.abs((await page.evaluate(() => scrollY)) - scrollBefore) < 2, 'Page scroll position restored without jump.');
  check(await page.locator('body').evaluate(el => getComputedStyle(el).position) !== 'fixed', 'Background scroll unlocked.');
  for (let i = 0; i < 3; i++) {await opener.click(); await modal.locator('[data-close]').click();}
  check(await page.locator('dialog.ak-image-viewer').count() === 1, 'Repeated open/close reuses one dialog.');
  await opener.click();
  check(await img.getAttribute('src') === images[scenario.fixture ? 1 : 0].full, 'Reopen preserves selected image.');
  await modal.locator('.ak-image-viewer__canvas').click({position: {x: 1, y: 1}});
  check(!await modal.isVisible(), 'Click/tap outside image closes fit view.');
  check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No page overflow.');
  check(errors.length === 0, 'No console/page errors: ' + errors.join('; '));
  await page.goto(base + '/');await page.goBack();
  check(await page.locator('[data-gallery-images]').count() === 1, 'Browser back returns to normal product page.');
  if (scenario.name === 'single-portrait') {
   const oldId = await page.locator('.single_add_to_cart_button').getAttribute('value');
   await page.locator('[data-selector-group="storage"] [data-selector-option]:not(.is-selected):not([disabled])').first().click();
   const newId = await page.locator('.single_add_to_cart_button').getAttribute('value');
   check(newId !== oldId, 'Native storage selector chose a different existing product; no cart action.');
   const expected = await page.locator('#appleklinika-product-selector-data').evaluate((el, id) => JSON.parse(el.textContent).find(product => String(product.id) === id).images[0], newId);
   check(await opener.getAttribute('href') === expected.full, 'Existing product selector updates the new gallery owner: ' + JSON.stringify({actual: await opener.getAttribute('href'), expected: expected.full, errors}));
   check(await gallery.getAttribute('data-current-index') === '0', 'Product change resets selected image.');
   await opener.click();
   check(await modal.locator('img').getAttribute('src') === expected.full, 'Viewer opens the newly selected product image, never stale media.');
   await modal.locator('[data-close]').click();
   check(await page.locator('dialog.ak-image-viewer').count() === 1, 'Product selection has one viewer instance.');
   check(errors.length === 0, 'Product-gallery integration emits no JS errors.');
  }
  console.log(`${scenario.name} ${width}: PASS; unrelated favicon 404: ${unrelatedNoise.length}`);await context.close();
 }} finally {await browser.close();}
 console.log(`Product gallery browser regression: ${checks} checks passed. Evidence: ${evidence}`);
})().catch(error => {console.error(error); process.exitCode = 1;});
