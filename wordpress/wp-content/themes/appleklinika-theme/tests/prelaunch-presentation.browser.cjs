// LOCAL fixture acceptance: no product writes, no checkout submission.
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.BASE_URL || 'http://localhost:8080';
assert.equal(new URL(base).hostname, 'localhost');
const out = process.env.EVIDENCE_DIR || fs.mkdtempSync('/tmp/prelaunch-presentation-');
fs.mkdirSync(out, {recursive: true});
let checks = 0;
const failures = [], errors = [], knownLocalNoise = [], results = [];
const check = (ok, label) => { checks++; if (!ok) failures.push(label); };
(async () => {
 const browser = await chromium.launch({headless: true, ...(process.env.CHROME_EXECUTABLE ? {executablePath: process.env.CHROME_EXECUTABLE} : {})});
 try {
  for (const width of [1440, 390]) {
   const context = await browser.newContext({viewport: {width, height: 1000}, hasTouch: width === 390});
   const page = await context.newPage();
   page.on('pageerror', error => errors.push(error.message));
   page.on('console', message => {
    if (message.type() !== 'error') return;
    const detail = message.text() + ' ' + message.location().url;
    // Also present before this feature: the LOCAL environment has no site icon.
    if (message.location().url === base + '/favicon.ico' && message.text().includes('404')) knownLocalNoise.push(detail);
    else errors.push(detail);
   });
   const shot = name => page.screenshot({path: path.join(out, `${width}-${name}.png`)});
   for (const [type, label, id] of [['iphone','iPhone',288],['macbook','MacBook',432],['ipad','iPad',470],['apple_watch','Apple Watch',476]]) {
    for (const kind of ['listing', 'pdp']) {
     await page.goto(base + (kind === 'listing' ? '/?post_type=product&ak_type=' + type : '/?post_type=product&p=' + id), {waitUntil: 'networkidle'});
     check(JSON.stringify(await page.locator('.ak-category-nav [aria-current=page]').allTextContents()) === JSON.stringify([label]), `${width} ${type} ${kind}: one correct active category`);
     check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${width} ${type} ${kind}: no overflow`);
     check(!/Selector teszt|Demo - |helyi fejlesztési termék|selector teszttermék|local registration test|\bGrade\b/i.test(await page.locator('body').innerText()), `${width} ${type} ${kind}: no dev copy or English grade label`);
     if (kind === 'listing') {
      const form = page.locator('.ak-shop-filters:visible').first(), toggle = form.locator('.ak-filter-toggle'), model = form.locator('.ak-filter-group').first();
      check(await toggle.isVisible() === (width === 390), `${width} ${type}: mobile toggle only`);
      check(await model.isVisible() === (width !== 390), `${width} ${type}: initial filter visibility`);
      if (width === 390) {
       check((await form.boundingBox()).height < 90, `${type}: collapsed filters stay compact`);
       await toggle.click();
       check(await model.isVisible() && await toggle.getAttribute('aria-expanded') === 'true', `${type}: native expand`);
       await shot(`${type}-filter-open`);
       await toggle.click();
       check(!await model.isVisible() && await toggle.getAttribute('aria-expanded') === 'false', `${type}: native collapse`);
       await page.setViewportSize({width:1440,height:1000});
       check(await model.isVisible(), `${type}: desktop always exposes filters after resize`);
       await page.setViewportSize({width,height:1000});
      }
     }
     await shot(`${type}-${kind}`);
     if (kind === 'listing' && width === 390) {
      const form = page.locator('.ak-shop-filters:visible').first();
      await form.locator('.ak-filter-toggle').click();
      const option = form.locator('.ak-filter-group input[type=checkbox]').first();
      const value = await option.inputValue();
      // Click the visible label: the existing custom checkbox paints a span over its input.
      await option.locator('..').click();
      check(await option.isChecked(), `${type}: visible checkbox label selects the model`);
      await form.getByRole('button', {name:'Szűrés alkalmazása',exact:true}).click();
      await page.waitForLoadState('networkidle');
      check([...new URL(page.url()).searchParams.values()].includes(value), `${type}: native filter submission retains chosen model`);
      check(await page.locator('.ak-product-card__title').count() > 0, `${type}: filtered products remain visible`);
      check(JSON.stringify(await page.locator('.ak-category-nav [aria-current=page]').allTextContents()) === JSON.stringify([label]), `${type}: filtered page retains active category`);
     }
    }
   }
   await page.goto(base + '/?post_type=product&p=288', {waitUntil:'networkidle'});
   const gallery = page.locator('[data-gallery-images]'), opener = gallery.locator('[data-gallery-open]');
   const images = await gallery.evaluate(g => JSON.parse(g.dataset.galleryImages));
   check(images.length === 3, `${width}: three genuine assigned photos, no HTML fixture substitution`);
   await gallery.scrollIntoViewIfNeeded();
   for (let index=0; index<3; index++) {
    await gallery.locator(`[data-gallery-index="${index}"]`).click();
    await page.locator('[data-appleklinika-stage-image]').evaluate(img => img.decode());
    check(await opener.getAttribute('href') === images[index].full, `${width}: thumbnail ${index+1} selects matching original`);
    await shot('gallery-photo-' + (index+1));
   }
   await opener.click();
   const modal = page.locator('dialog.ak-image-viewer');
   await modal.waitFor({state:'visible'});
   await modal.locator('img').evaluate(img => img.decode());
   await shot('lightbox');
   await modal.locator('[data-direction="-1"]').click();
   check(await modal.locator('.ak-image-viewer__count').innerText() === '2 / 3', `${width}: previous photo`);
   await modal.locator('[data-direction="1"]').click();
   check(await modal.locator('.ak-image-viewer__count').innerText() === '3 / 3', `${width}: next photo`);
   await modal.locator('img').evaluate(img => img.decode());
   await modal.locator('[data-zoom]').click();
   check(await modal.locator('.ak-image-viewer__canvas').getAttribute('data-zoomed') === 'true', `${width}: zoom works`);
   await shot('zoom');
   await modal.locator('[data-close]').click();
   check(!await modal.isVisible(), `${width}: close works`);
   await opener.click();
   check(await modal.isVisible() && await page.locator('dialog.ak-image-viewer').count() === 1, `${width}: reopen without duplicate`);
   await modal.locator('[data-close]').click();
   results.push({width, images:images.map(i=>i.full)});
   await context.close();
  }
 } finally {
  await browser.close();
  check(errors.length === 0, 'No console/runtime errors: ' + errors.join('; '));
  fs.writeFileSync(path.join(out,'result.json'),JSON.stringify({checks,failures,errors,knownLocalNoise,results},null,2));
  console.log(JSON.stringify({checks,failures,errors}));
  if (failures.length) process.exitCode=1;
 }
})().catch(error=>{console.error(error);process.exitCode=1;});
