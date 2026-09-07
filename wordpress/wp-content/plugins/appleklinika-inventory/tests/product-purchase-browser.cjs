/* LOCAL native-click regression. Existing products only; no product/stock writes.
 * Direct page loads are the oracle for selector-produced purchase UI. */
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.BASE_URL || 'http://localhost:8080';
assert.equal(new URL(base).hostname, 'localhost', 'LOCAL only.');
const out = process.env.EVIDENCE_DIR || fs.mkdtempSync('/tmp/product-purchase-');
fs.mkdirSync(out, {recursive: true});
const cases = [
    {id: 366, color: 'alpine_green', stocked: false},
    {id: 318, color: 'silver', stocked: true},
    {id: 334, color: 'gold', stocked: true},
];
const failures = [], report = [];
let checks = 0;
const check = (ok, message) => {checks++; if (!ok) failures.push(message);};
const norm = s => (s || '').replace(/\s+/g, ' ').trim();
const state = page => page.locator('.appleklinika-cart-area').evaluate(area => {
    const form = area.querySelector('form.cart');
    const button = area.querySelector('.single_add_to_cart_button');
    const qty = form?.querySelector('input.qty');
    return {
        areas: document.querySelectorAll('.appleklinika-cart-area').length,
        forms: area.querySelectorAll('form.cart').length,
        buttons: area.querySelectorAll('.single_add_to_cart_button').length,
        productId: button?.value || null,
        disabled: button ? button.disabled : null,
        action: form?.action || null,
        stock: area.querySelector('.stock')?.textContent.trim() || '',
        quantity: qty ? {value: qty.value, min: qty.min, max: qty.max, type: qty.type} : null,
        battery: form?.querySelector('[name=appleklinika_battery_extra]')?.value || null,
    };
});
(async () => {
    const browser = await chromium.launch({headless: true, ...(process.env.CHROME_EXECUTABLE ? {executablePath: process.env.CHROME_EXECUTABLE} : {})});
    try {
        for (const width of [1440, 390]) {
            const context = await browser.newContext({viewport: {width, height: 1000}});
            const page = await context.newPage(), errors = [], warnings = [], submitted = [];
            let cartAttempted = false;
            page.on('pageerror', e => errors.push(e.message));
            page.on('console', m => {
                if (m.type() !== 'error') return;
                // Pre-existing local site-icon 404, never suppress application errors.
                if (m.location().url === base + '/favicon.ico' && m.text().includes('404')) warnings.push(m.text());
                else errors.push(m.text());
            });
            page.on('request', r => {
                if (new URL(r.url()).searchParams.get('wc-ajax') === 'add_to_cart' && r.method() === 'POST') submitted.push(r.postData());
            });
            const expected = new Map(), snapshots = [];
            try {
                for (const item of cases) {
                    await page.goto(base + '/?post_type=product&p=' + item.id, {waitUntil: 'networkidle'});
                    const current = await state(page);
                    assert.equal(current.forms, Number(item.stocked), 'Fixture stock precondition: ' + item.id);
                    if (item.stocked) assert.equal(current.productId, String(item.id));
                    expected.set(item.id, current);
                }
                await page.goto(base + '/?post_type=product&p=366', {waitUntil: 'networkidle'});
                for (const id of [318, 366, 318, 334, 366, 318]) {
                    const item = cases.find(c => c.id === id);
                    await page.locator('[data-selector-group=color] [data-option-value="' + item.color + '"]').click();
                    const current = await state(page), reference = expected.get(id);
                    check(JSON.stringify(current) === JSON.stringify(reference), width + ' native transition to ' + id + ': direct-load parity');
                    check(current.areas === 1 && current.forms <= 1 && current.buttons <= 1, width + ': no duplicate purchase UI');
                    const selected = await page.locator('#appleklinika-product-selector-data').evaluate((node, productId) => JSON.parse(node.textContent).find(p => p.id === productId), id);
                    check(norm(await page.locator('[data-appleklinika-product-title]').innerText()) === norm(selected.title), width + ': title remains coherent');
                    check(norm(await page.locator('[data-appleklinika-stock-badge]').innerText()) === norm(selected.stockLabel), width + ': stock remains coherent');
                    check(Number((await page.locator('.appleklinika-price-stack__current').innerText()).replace(/\D/g, '')) === selected.salePrice, width + ': price remains coherent');
                    check(await page.locator('[data-gallery-open]').getAttribute('href') === selected.images[0].full, width + ': gallery follows selected product');
                    snapshots.push({id, expected: reference, actual: current});
                    await page.locator('.appleklinika-buy-panel').screenshot({path: path.join(out, width + '-transition-' + snapshots.length + '-' + id + '.png')});
                }
                // IN -> different IN, including the starting stocked form rather than only a created form.
                await page.goto(base + '/?post_type=product&p=318', {waitUntil: 'networkidle'});
                await page.locator('[data-selector-group=color] [data-option-value=gold]').click();
                check(JSON.stringify(await state(page)) === JSON.stringify(expected.get(334)), width + ': stocked-to-stocked ID/quantity parity');
                await page.locator('[data-selector-group=color] [data-option-value=alpine_green]').click();
                check(JSON.stringify(await state(page)) === JSON.stringify(expected.get(366)), width + ': initially stocked form is removed');
                await page.locator('[data-selector-group=color] [data-option-value=silver]').click();
                check(JSON.stringify(await state(page)) === JSON.stringify(expected.get(318)), width + ': removed form is recreated once');
                await page.locator('[data-selector-group=battery] [data-option-value=aftermarket_new]').click();
                await page.locator('[data-selector-group=color] [data-option-value=gold]').click();
                check((await state(page)).battery === 'aftermarket_new', width + ': selected battery option survives form replacement');
                await page.locator('[data-selector-group=battery] [data-option-value=standard]').click();
                check(JSON.stringify(await state(page)) === JSON.stringify(expected.get(334)), width + ': standard purchase state restored');

                // No cart mutation at all on a failing baseline. After green transitions,
                // one real add of the newly selected product, never an order/checkout.
                if (width === 390 && failures.length === 0) {
                    assert.equal((await page.locator('.ak-cart-count').innerText()).trim(), '0', 'Isolated empty cart');
                    const qty = page.locator('.appleklinika-cart-area input.qty');
                    if (await qty.isVisible()) await qty.fill('1');
                    cartAttempted = true;
                    const response = page.waitForResponse(r => new URL(r.url()).searchParams.get('wc-ajax') === 'add_to_cart' && r.request().method() === 'POST');
                    await page.locator('.single_add_to_cart_button').click();
                    const added = await (await response).json();
                    check(!added.error, 'Normal Woo AJAX add succeeds after repeated form replacements');
                    await page.waitForFunction(() => document.querySelector('.ak-cart-count')?.textContent.trim() === '1');
                    check(submitted.length === 1, 'Exactly one add request: no duplicated/dead listeners');
                    check(submitted[0].includes('334'), 'Newly selected product ID was submitted');
                    const cart = await (await context.request.get(base + '/?rest_route=/wc/store/v1/cart')).json();
                    check(cart.items?.length === 1 && cart.items[0].id === 334 && cart.items[0].quantity === 1, 'Woo cart contains selected product and quantity, not original product');
                    check(cart.items?.[0].quantity_limits.maximum === Number(expected.get(334).quantity.max), 'Displayed max agrees with Woo cart stock limit');
                    await context.storageState({path: path.join(out, 'qa-cart-storage-state.json')});
                }
                check(errors.length === 0, width + ': no console/runtime errors: ' + errors.join('; '));
            } finally {
                if (cartAttempted) {
                    await page.goto(base + '/?page_id=8', {waitUntil: 'networkidle'});
                    const remove = page.locator('a.ak-cart-item__remove');
                    assert(await remove.count() <= 1, 'Only the exact isolated QA item may be removed');
                    if (await remove.count()) await remove.click();
                    await page.waitForFunction(() => document.querySelector('.ak-cart-count')?.textContent.trim() === '0');
                    const cart = await (await context.request.get(base + '/?rest_route=/wc/store/v1/cart')).json();
                    check(cart.items?.length === 0, 'Exact QA cart item removed; no order created');
                    fs.rmSync(path.join(out, 'qa-cart-storage-state.json'), {force: true});
                }
                report.push({width, snapshots, errors, warnings, submitted, cartAttempted});
                await context.close();
            }
            console.log('Purchase lifecycle ' + width + ': inspected all native transitions.');
        }
    } finally {
        await browser.close();
        fs.writeFileSync(path.join(out, 'result.json'), JSON.stringify({checks, failures, report}, null, 2));
    }
    console.log(JSON.stringify({checks, failures, evidence: out}, null, 2));
    if (failures.length) process.exitCode = 1;
})().catch(e => {console.error(e); process.exitCode = 1;});
