'use strict';

// Local, native-input visual/state acceptance. Never submits an order.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

const base = process.env.AK_CHECKOUT_BASE_URL || 'http://localhost:8080';
assert.equal(new URL(base).hostname, 'localhost', 'This runner may only change a local QA cart.');
const container = process.env.AK_WP_CONTAINER || 'appleklinikawebshop-wordpress-1';
const productId = Number(process.env.AK_CHECKOUT_PRODUCT_ID || 334);
assert(Number.isSafeInteger(productId) && productId > 0);
const output = process.env.AK_UX_OUTPUT || fs.mkdtempSync('/tmp/checkout-final-ux-');
fs.mkdirSync(output, { recursive: true });
const reports = [];
let assertions = 0;
function check(ok, message) { assertions++; assert(ok, message); }
function save() { fs.writeFileSync(path.join(output, 'result.json'), JSON.stringify({ assertions, reports }, null, 2)); }
function localWoo(code) {
  const guard = `require '/var/www/html/wp-load.php';if(home_url()!==${JSON.stringify(base)})throw new Exception('Local only');`;
  return JSON.parse(execFileSync('docker', ['exec', container, 'php', '-r', guard + code], { encoding: 'utf8' }));
}
function state(page) {
  return page.evaluate(() => {
    const c = wp.data.select('wc/store/cart').getCustomerData();
    const a = wp.data.select('wc/store/checkout').getAdditionalFields();
    return {
      email: c.billingAddress.email, company: c.billingAddress.company,
      billingPhone: c.billingAddress.phone, shippingPhone: c.shippingAddress.phone,
      postcode: c.shippingAddress.postcode, shippingName: c.shippingAddress.first_name,
      billingStreet: c.billingAddress.address_1, shippingStreet: c.shippingAddress.address_1,
      mode: a['appleklinika/company_purchase'] === true, tax: a['appleklinika/tax_number'] || '',
    };
  });
}
async function fill(page, id, value) {
  await page.locator('#' + id).fill(value);
  await page.locator('#' + id).press('Tab');
}
async function shippingSnapshot(page) {
  return page.evaluate(() => {
    const cart = wp.data.select('wc/store/cart');
    return { rates: (cart.getCartData().shippingRates || []).map(p => ({
      postcode: p.destination.postcode, selected: p.shipping_rates.filter(r => r.selected).map(r => r.rate_id),
    })),
      updating: cart.isCustomerDataUpdating(), selecting: cart.isShippingRateBeingSelected(),
      checkedInput: document.querySelector('#shipping-option input:checked')?.value,
      review: document.querySelector('.ak-checkout-final-review__delivery-method')?.textContent };
  });
}
async function shippingSettled(page, postcode, rateId = null) {
  await page.waitForFunction(({ postcode, rateId }) => {
    const cart = wp.data.select('wc/store/cart');
    const packages = cart.getCartData().shippingRates || [];
    return !cart.isCustomerDataUpdating() && !cart.isAddressFieldsForShippingRatesUpdating()
      && !cart.isShippingRateBeingSelected() && packages.length > 0
      && packages.every(p => p.destination.postcode === postcode
        && (!rateId || p.shipping_rates.some(r => r.rate_id === rateId && r.selected)));
  }, { postcode, rateId }, { timeout: 45000 });
}
async function screenshot(page, name) {
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.getByRole('link', { name: 'Vissza a kosárhoz', exact: true }).first().focus();
  await page.screenshot({ path: path.join(output, name + '.png'), fullPage: true });
  if (name.endsWith('step2')) await page.locator('#order-fields').screenshot({ path: path.join(output, name + '-company-detail.png') });
  check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), name + ': no horizontal overflow');
  const controls=await page.evaluate(()=>[...document.querySelectorAll('.wc-block-components-checkbox input[type="checkbox"]')].filter(x=>x.getClientRects().length).map(x=>{
    const label=x.closest('label'),mark=label.querySelector('svg'),a=x.getBoundingClientRect(),b=mark?.getClientRects().length?mark.getBoundingClientRect():null;
    return {id:x.id,height:label.getBoundingClientRect().height,html:label.outerHTML,display:getComputedStyle(label).display,input:{x:a.x,y:a.y,w:a.width,h:a.height},mark:b?{x:b.x,y:b.y,w:b.width,h:b.height}:null,centered:!b||(Math.abs(a.x+a.width/2-b.x-b.width/2)<1.5&&Math.abs(a.y+a.height/2-b.y-b.height/2)<1.5)};
  }));
  fs.writeFileSync(path.join(output,name+'-controls.json'),JSON.stringify(controls,null,2));
  for(const control of controls){check(control.height>=44,name+': '+control.id+' touch target');check(control.centered,name+': '+control.id+' mark centered');}
}
async function verifyState(page, expected, label) {
  const current = await state(page);
  for (const [key, value] of Object.entries(expected)) check(current[key] === value, label + ': ' + key);
  check(await page.locator('#order-appleklinika-company_purchase').count() === 1, label + ': one company decision');
  check(await page.locator('#contact-appleklinika-marketing_consent').count() === 1, label + ': one native marketing control');
  if (label.includes('rerender')) check(await page.evaluate(() => ['billing', 'shipping'].every(id => getComputedStyle(document.getElementById(id)).display === 'grid')), label + ': stable form layout');
  check(await page.evaluate(() => ['email', 'shipping-phone', 'billing-phone', 'order-appleklinika-company_purchase'].every(id => {
    const input = document.getElementById(id);
    return input && input.isConnected && input.closest('.wc-block-components-checkout-step') && document.querySelectorAll('[id="' + id + '"]').length === 1;
  })), label + ': live original Woo-owned fields, no duplicate IDs');
  check(await page.evaluate(()=>['shipping-phone','billing-phone'].every(id=>document.getElementById(id)?.getAttribute('placeholder')==='+36 30 123 4567')),label+': phone hints survive live remounts without changing values');
}

(async () => {
  const baseline = localWoo(`echo json_encode(['drafts'=>wc_get_orders(['status'=>'checkout-draft','limit'=>-1,'return'=>'ids']),'stock'=>wc_get_product(${productId})->get_stock_quantity()]);`);
  fs.writeFileSync(path.join(output, 'baseline.json'), JSON.stringify(baseline, null, 2));
  const browser = await chromium.launch({ headless: true, executablePath: process.env.AK_CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  try {
    for (const width of [1440, 390]) {
      const report = { width, email: `qa-final-ux-${Date.now()}-${width}@example.test`, drafts: [], errors: [], submissions: 0 };
      reports.push(report); save();
      const context = await browser.newContext({ viewport: { width, height: 1000 } });
      const page = await context.newPage(); page.setDefaultTimeout(20000);
      page.on('pageerror', e => report.errors.push(e.message));
      page.on('console', m => { if (m.type() === 'error') report.errors.push(m.text()); });
      await context.route('**/*', route => {
        const r = route.request();
        // Pricing/radio UX only: never exercise the external pickup locator.
        if (new URL(r.url()).hostname === 'map.gls-croatia.com') {
          return route.fulfill({ contentType: 'application/javascript', body: "customElements.define('gls-dpm-dialog',class extends HTMLElement{showModal(){throw new Error('Live GLS locator is outside this presentation test');}});" });
        }
        if (r.method() === 'POST' && decodeURIComponent(r.url()).includes('/wc/store/v1/checkout') && !r.url().includes('__experimental_calc_totals=true')) {
          report.submissions++; return route.abort();
        }
        return route.continue();
      });
      page.on('response', async r => {
        if (r.url().includes('/wc/store/')) try {
          const data = await r.json();
          if (data.order_id && !report.drafts.includes(data.order_id)) { report.drafts.push(data.order_id); save(); }
        } catch { /* Not every Store API response is JSON. */ }
      });
      try {
        await page.goto(base + '/?post_type=product&p=' + productId, { waitUntil: 'networkidle' });
        check((await page.locator('.ak-cart-count').innerText()).trim() === '0', 'Isolated guest cart');
        await page.locator('.single_add_to_cart_button').click();
        await page.waitForFunction(() => document.querySelector('.ak-cart-count')?.textContent.trim() === '1');
        await page.goto(base + '/?page_id=9', { waitUntil: 'networkidle' });
        await fill(page, 'email', 'previous-' + report.email);
        await fill(page, 'email', report.email);
        const address = { last_name: 'Teszt', first_name: 'Elek', postcode: '6726', city: 'Szeged', address_1: 'Fő fasor', 'appleklinika-house_number': '12', phone: '+36301234567' };
        for (const [key, value] of Object.entries(address)) await fill(page, 'shipping-' + key, value);
        await page.locator('#shipping-fields input[type=checkbox]').first().uncheck();
        for (const [key, value] of Object.entries({ ...address, phone: '+36307654321' })) await fill(page, 'billing-' + key, value);
        const company = page.locator('#order-appleklinika-company_purchase');
        const marketing = page.locator('#contact-appleklinika-marketing_consent');
        for (const mode of ['personal', 'company']) {
          if (mode === 'company') {
            await page.locator('[data-checkout-step-trigger="2"]').click();
            await company.check();
            check(await page.locator('#order-appleklinika-company_name').evaluate(x => x.required && !x.checkValidity()), 'Missing company is invalid');
            check(await page.locator('#order-appleklinika-tax_number').evaluate(x => x.required && !x.checkValidity()), 'Missing tax is invalid');
            await fill(page, 'order-appleklinika-company_name', 'QA Checkout UX Kft.');
            await fill(page, 'order-appleklinika-tax_number', '123');
            check(!await page.locator('#order-appleklinika-tax_number').evaluate(x => x.checkValidity()), 'Malformed tax stays invalid');
            await fill(page, 'order-appleklinika-tax_number', '12345678-1-23');
            await fill(page, 'order-appleklinika-company_name', 'QA Végleges UX Kft.');
            await fill(page, 'billing-phone', '+36305550444');
            await fill(page, 'shipping-phone', '+36305550666');
          } else {
            await company.uncheck();
            for (const id of ['order-appleklinika-company_name', 'order-appleklinika-tax_number']) {
              check(!await page.locator('#' + id).isVisible() && !await page.locator('#' + id).evaluate(x => x.required), 'Personal mode has no hidden company requirements');
            }
            await fill(page, 'billing-first_name', '');
            check(!await page.locator('#billing-first_name').evaluate(x => x.checkValidity()), 'Personal names are still required');
            await fill(page, 'billing-first_name', 'Elek');
          }
          await page.waitForLoadState('networkidle');
          check(!await marketing.isVisible() && !await marketing.isChecked(), 'Optional marketing deferred and unchecked');
          check(await page.evaluate(() => Boolean(document.getElementById('order-fields').compareDocumentPosition(document.getElementById('billing-fields')) & Node.DOCUMENT_POSITION_FOLLOWING)), 'Company decision precedes billing in actual DOM order');
          if (mode === 'company') check(!await page.locator('#billing-first_name').isVisible(), 'Technical personal billing fields hidden for company');
          const expected = await state(page);
          check(expected.email === report.email && expected.mode === (mode === 'company'), 'Explicit latest email and billing mode');
          if (mode === 'company') check(expected.company === 'QA Végleges UX Kft.' && expected.tax === '12345678-1-23' && expected.billingPhone === '+36305550444' && expected.shippingPhone === '+36305550666', 'Latest edited company/tax/phones are authoritative');
          for (const postcode of ['6724', '6725', '6726']) {
            await fill(page, 'shipping-postcode', postcode);
            await shippingSettled(page, postcode);
            await verifyState(page, { ...expected, postcode }, mode + ' native Woo rerender');
          }
          report[mode] = { state: await state(page) };
          check(await page.locator('.ak-checkout-summary__method-pending:visible').count() === 2, 'Step 2 has neutral shipping/payment placeholders');
          check(await page.locator('.ak-checkout-summary__method-chosen:visible').count() === 0, 'No default GLS/Barion presented as a completed Step 2 choice');
          check(await page.locator('[id^="ak-checkout-address-selector-"]').count() === 0, 'Guest without saved addresses has no empty selector');
          await screenshot(page, width + '-' + mode + '-step2');
          await page.getByRole('button', { name: 'Tovább a szállítás és fizetéshez', exact: true }).click();
          await page.waitForFunction(() => document.body.dataset.akCheckoutStep === '3');
          const gls = page.getByRole('radio', { name: /Kézbesítés címre/ });
          const pickupRate = await page.locator('#shipping-option input[value^="local_pickup:"]').getAttribute('value');
          for (const [rateId, cost] of [['gls_shipping_method_parcel_locker',1490],['gls_shipping_method_parcel_shop',1490],[pickupRate,0],['gls_shipping_method',1990]]) {
            await page.locator('#shipping-option input[value="' + rateId + '"]').check();
            await shippingSettled(page, '6726', rateId);
            const rateTotals = await page.evaluate(() => wp.data.select('wc/store/cart').getCartData().totals);
            const factor = 10 ** rateTotals.currency_minor_unit;
            check(Number(rateTotals.total_shipping) / factor === cost, rateId + ': requested LOCAL cost');
            check(Number(rateTotals.total_price) === Number(rateTotals.total_items) + Number(rateTotals.total_shipping) + Number(rateTotals.total_tax) - Number(rateTotals.total_discount), rateId + ': grand total updates');
            report[mode][rateId] = rateTotals;
          }
          await gls.check();
          await shippingSettled(page, '6726', 'gls_shipping_method');
          report[mode].shippingStep3 = await shippingSnapshot(page);
          await page.locator('input[type=radio][value="barion"]').check();
          check(!await company.isVisible() && !await marketing.isVisible(), 'Step 3 contains no identity/consent controls');
          check(await gls.isChecked() && await page.locator('input[value="barion"]').isChecked(), 'Native GLS and Barion selectable');
          await screenshot(page, width + '-' + mode + '-step3');
          const totals = await page.evaluate(() => wp.data.select('wc/store/cart').getCartData().totals);
          await page.getByRole('button', { name: 'Tovább az összegzéshez', exact: true }).click();
          await page.waitForFunction(() => document.body.dataset.akCheckoutStep === '4');
          await shippingSettled(page, '6726', 'gls_shipping_method');
          report[mode].shippingStep4 = await shippingSnapshot(page);
          check(await page.locator('.ak-checkout-summary__method-pending:visible').count() === 0, 'Step 4 has no pending choices');
          const reviewTotals = await page.evaluate(() => wp.data.select('wc/store/cart').getCartData().totals);
          check(Number(reviewTotals.total_shipping) / (10 ** reviewTotals.currency_minor_unit) === 1990, 'Step 4 retains selected home-delivery cost');
          await verifyState(page, { ...expected, postcode: '6726' }, mode + ' final state');
          const finalText = await page.locator('.ak-checkout-final-review').innerText();
          for (const value of [report.email, expected.billingPhone, expected.shippingPhone, 'Fő fasor 12', 'Barion', 'Kézbesítés címre']) check(finalText.includes(value), 'Final review includes ' + value);
          if (mode === 'company') for (const value of [expected.company, expected.tax]) check(finalText.includes(value), 'Company/tax final review');
          check(!await company.isVisible() && await marketing.isVisible() && !await marketing.isChecked(), 'Only final optional marketing decision visible');
          const terms = page.locator('.wc-block-checkout__terms input[type=checkbox]');
          check(!await terms.isChecked(), 'Terms not prechecked');
          check(await page.evaluate(() => Object.entries(wp.data.select('wc/store/validation').getValidationErrors()).some(([key]) => /terms/i.test(key))), 'Unchecked native terms remain a validation gate');
          await terms.check();
          await page.waitForFunction(() => !document.querySelector('.wc-block-components-checkout-place-order-button')?.disabled);
          check(await page.locator('.wc-block-components-checkout-place-order-button').isEnabled(), 'Required terms accepted; declined marketing does not block');
          check(!await page.evaluate(() => Object.keys(wp.data.select('wc/store/validation').getValidationErrors()).some(key => /company|tax|billing_first_name|billing_last_name/i.test(key))), 'No stale company/tax/personal validation');
          await marketing.check();
          await screenshot(page, width + '-' + mode + '-consents-checked');
          await marketing.uncheck();
          check(JSON.stringify(totals) === JSON.stringify(await page.evaluate(() => wp.data.select('wc/store/cart').getCartData().totals)), 'Review/consent does not alter totals');
          // Cart-store updates precede the theme's scheduled presentation frame.
          // Require the visible amounts too, not only the authoritative totals.
          await page.waitForFunction(() => {
            const totals=wp.data.select('wc/store/cart').getCartData().totals;
            const shown=document.querySelector('.ak-checkout-summary__row--total strong');
            const rows=[...document.querySelectorAll('.ak-checkout-summary__row')];
            const shipping=rows.find(x=>x.querySelector('span')?.textContent==='Szállítás')?.querySelector('strong');
            const amount=x=>Number((x?.textContent||'').replace(/[^0-9]/g,''));
            return shown&&shipping&&amount(shown)===Number(totals.total_price)&&amount(shipping)===Number(totals.total_shipping);
          });
          check(true,'Visible Step 4 shipping and grand total match the authoritative Woo totals');
          await screenshot(page, width + '-' + mode + '-step4');
          await terms.uncheck();
        }
        check(report.errors.length === 0, 'No browser errors');
        check(report.submissions === 0, 'No order submission attempted');
        report.pass = true;
      } catch (error) {
        report.failure = error.message;
        report.shippingFailure = await shippingSnapshot(page).catch(() => null);
        report.validation = await page.evaluate(() => ({ errors: wp.data.select('wc/store/validation').getValidationErrors(), checkout: wp.data.select('wc/store/checkout').getCheckoutStatus(), payment: wp.data.select('wc/store/payment').getActivePaymentMethod(), invalid: [...document.querySelectorAll('input,select,textarea')].filter(x => x.getClientRects().length && x.willValidate && !x.validity.valid).map(x => ({id:x.id,type:x.type,validation:x.validationMessage})), step: document.body.dataset.akCheckoutStep, buttons: [...document.querySelectorAll('[data-checkout-step-controls]')].map(x=>({step:x.dataset.checkoutStepControls,connected:x.isConnected,text:x.textContent})) })).catch(() => null);
        await page.screenshot({ path: path.join(output, width + '-failure.png'), fullPage: true }).catch(() => {});
        throw error;
      } finally {
        report.sessions = (await context.cookies()).filter(c => c.name.startsWith('wp_woocommerce_session_')).map(c => decodeURIComponent(c.value).split('|')[0]);
        for (const key of report.sessions) {
          assert(/^[a-zA-Z0-9_-]+$/.test(key));
          const draft = localWoo(`$s=(new WC_Session_Handler())->get_session('${key}',false);echo json_encode($s['store_api_draft_order']??null);`);
          if (draft && !report.drafts.includes(Number(draft))) report.drafts.push(Number(draft));
        }
        await page.goto(base + '/?page_id=8', { waitUntil: 'networkidle' });
        const remove = page.locator('a.ak-cart-item__remove');
        assert(await remove.count() <= 1, 'Only own QA cart');
        if (await remove.count()) await remove.click();
        await page.waitForFunction(() => document.querySelector('.ak-cart-count')?.textContent.trim() === '0');
        for (const id of report.drafts) {
          assert(!baseline.drafts.includes(id), 'Never delete a preexisting draft');
          localWoo(`$o=wc_get_order(${Number(id)});if($o){if($o->get_status()!=='checkout-draft'||($o->get_billing_email()!==''&&$o->get_billing_email()!=='${report.email}'))throw new Exception('Unsafe draft');$o->delete(true);}echo json_encode(true);`);
        }
        for (const key of report.sessions) localWoo(`(new WC_Session_Handler())->delete_session('${key}');echo json_encode(true);`);
        report.stock = localWoo(`echo json_encode(wc_get_product(${productId})->get_stock_quantity());`);
        check(report.stock === baseline.stock, 'Stock unchanged'); report.cleaned = true; save();
        await context.close();
      }
    }
  } finally { await browser.close(); save(); }
  console.log(`Checkout final UX: ${assertions} native assertions passed. Evidence: ${output}`);
})().catch(error => { console.error(error); process.exitCode = 1; });
