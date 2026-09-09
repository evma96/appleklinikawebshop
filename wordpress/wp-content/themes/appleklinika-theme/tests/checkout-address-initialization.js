'use strict';

// LOCAL native-input source trace. No shared profile, real order or external service.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const base = 'http://localhost:8080';
const output = process.env.AK_UX_OUTPUT || fs.mkdtempSync('/tmp/checkout-address-initialization-');
fs.mkdirSync(output, { recursive: true });
const report = { assertions: 0, cases: [], presentationFailures: [] };
const service = String.raw`$service=new \AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService(new \AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressAddressRepository($wpdb),new \AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressTransactionManager($wpdb),new \AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooUserMetaProjection(),new \AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooAllowedCountries());`;
function woo(code) {
  return JSON.parse(execFileSync('docker', ['exec', process.env.AK_WP_CONTAINER || 'appleklinikawebshop-wordpress-1', 'php', '-r', `require '/var/www/html/wp-load.php';if(home_url()!=='${base}')throw new Exception('LOCAL only');${service}${code}`], { encoding: 'utf8' }));
}
function check(ok, message) { report.assertions++; assert(ok, message); }
function save() { fs.writeFileSync(output + '/result.json', JSON.stringify(report, null, 2)); }
const keys = ['first_name', 'last_name', 'address_1', 'city', 'postcode'];
const shipping = { first_name: 'Elek', last_name: 'Szállítás', address_1: 'Fő fasor', city: 'Szeged', postcode: '6726', country: 'HU', phone: '+36301234567' };
const billing = { first_name: 'Anna', last_name: 'Számlázás', address_1: 'Számlázási utca', city: 'Budapest', postcode: '1117', country: 'HU', phone: '+36307654321' };
async function fill(page, id, value) { await page.locator('#' + id).fill(value); await page.locator('#' + id).press('Tab'); }
async function editable(page, purpose) {
  const edit = page.locator('#' + purpose + '-fields .wc-block-components-address-card__edit');
  if (await edit.isVisible()) await edit.click();
}
async function address(page, purpose, values) {
  await editable(page, purpose);
  await page.locator('#' + purpose + '-country').selectOption('HU');
  for (const [key, value] of Object.entries(values)) if (key !== 'country') await fill(page, purpose + '-' + key, value);
  await fill(page, purpose + '-appleklinika-house_number', purpose === 'shipping' ? '12' : '24');
}
async function settled(page, expected) {
  await page.waitForFunction(expected => {
    const store = wp.data.select('wc/store/cart'), data = store.getCartData();
    const packages = data.shippingRates || [];
    return !store.isCustomerDataUpdating() && !store.isAddressFieldsForShippingRatesUpdating()
      && packages.length > 0 && packages.every(p => p.destination.postcode === expected.shipping.postcode)
      && Object.entries(expected).every(([purpose, address]) => Object.entries(address).every(([key, value]) => data[purpose + 'Address'][key] === value));
  }, expected, { timeout: 45000 });
}
async function snapshot(page) {
  return page.evaluate(() => {
    const store = wp.data.select('wc/store/cart'), fields = {};
    for (const purpose of ['shipping', 'billing']) {
      fields[purpose] = {};
      for (const key of ['first_name', 'last_name', 'address_1', 'city', 'postcode', 'country', 'phone']) fields[purpose][key] = document.getElementById(purpose + '-' + key)?.value ?? null;
    }
    return { fields, customer: store.getCustomerData(), cart: { billing: store.getCartData().billingAddress, shipping: store.getCartData().shippingAddress },
      addressBook: store.getCartData().extensions?.['appleklinika/address-book'], additional: wp.data.select('wc/store/checkout').getAdditionalFields(),
      same: document.querySelector('.wc-block-checkout__use-address-for-billing input')?.checked,
      localStorageKeys: Object.keys(localStorage), sessionStorageKeys: Object.keys(sessionStorage),
      autofilledInputs: [...document.querySelectorAll('input:-webkit-autofill')].map(x => x.id) };
  });
}
function compare(actual, expected, label) {
  for (const [purpose, values] of Object.entries(expected)) for (const [key, value] of Object.entries(values)) {
    check(actual.fields[purpose][key] === value, `${label}: visible ${purpose}.${key}`);
    check(actual.customer[purpose + 'Address'][key] === value, `${label}: customer ${purpose}.${key}`);
    check(actual.cart[purpose][key] === value, `${label}: Store API hydrated cart ${purpose}.${key}`);
  }
}
async function shot(page, name) {
  await page.getByRole('link', { name: 'Vissza a kosárhoz', exact: true }).first().focus();
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: output + '/' + name + '.png', fullPage: true });
  check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), name + ': no overflow');
}
function addressesFromPayload(data) {
  if (!data || typeof data !== 'object') return [];
  const found = [];
  if (data.billing_address || data.shipping_address) found.push({ billing: data.billing_address, shipping: data.shipping_address });
  for (const item of data.responses || data.requests || []) found.push(...addressesFromPayload(item.body));
  return found;
}

async function exercise(page, context, item, fixture, pending) {
  const { width, kind } = item;
  const same = page.locator('.wc-block-checkout__use-address-for-billing input');
  await page.goto(base + '/?post_type=product&p=334', { waitUntil: 'networkidle' });
  check((await page.locator('.ak-cart-count').innerText()).trim() === '0', 'New independent cart');
  item.initialBrowserStorage = await page.evaluate(() => window.__qaStorageOnLoad);
  check(item.initialBrowserStorage.local.length === 0 && item.initialBrowserStorage.session.length === 0, 'No inherited local/session storage before application startup');
  await page.locator('.single_add_to_cart_button').click();
  await page.waitForFunction(() => document.querySelector('.ak-cart-count')?.textContent.trim() === '1');
  await page.goto(base + '/?page_id=9', { waitUntil: 'networkidle' });
  item.traces.push({ stage: 'initial', ...await snapshot(page) });
  await same.uncheck();
  await page.waitForSelector('#billing-fields');
  const initial = await snapshot(page); item.traces.push({ stage: 'initial-independent', ...initial });
  check(initial.autofilledInputs.length === 0, 'No browser autofill');
  check(await page.locator('[id^="ak-checkout-address-selector-"]').count() === 0, 'No pointless selector');
  check(await page.locator('[data-ak-address-save]').count() === (fixture ? 2 : 0), 'Save controls only available to authenticated customer');
  const expected = {};
  for (const purpose of ['billing', 'shipping']) expected[purpose] = Object.fromEntries(keys.map(key => [key, kind === 'woo-profile' ? ({billing,shipping})[purpose][key] : '']));
  compare(initial, expected, 'Initial authoritative source');
  check(await page.locator('#shipping-country').isVisible(), 'Normal editable form directly');
  await shot(page, width + '-' + kind + '-initial-step2');
  await fill(page, 'email', item.email);
  if (kind !== 'woo-profile') {
    await address(page, 'shipping', shipping);
    await settled(page, { billing: expected.billing, shipping });
    const independent = await snapshot(page);
    compare(independent, { billing: expected.billing, shipping }, 'Shipping filled / billing still blank');
    item.traces.push({ stage: 'shipping-only', ...independent });
    const billingSummary = await page.locator('.ak-checkout-summary__detail').filter({ has: page.locator('span', { hasText: /^Számlázási cím$/ }) }).innerText();
    if (billingSummary.includes(shipping.address_1)) report.presentationFailures.push(`${width}/${kind}: empty independent billing is falsely shown as shipping in sidebar`);
    await shot(page, width + '-' + kind + '-shipping-only-step2');
  }
  await address(page, 'billing', billing);
  await address(page, 'shipping', shipping);
  await settled(page, { billing, shipping });
  for (const postcode of ['6724', '6725', '6726']) {
    await fill(page, 'shipping-postcode', postcode);
    await settled(page, { billing, shipping: { ...shipping, postcode } });
    compare(await snapshot(page), { billing, shipping: { ...shipping, postcode } }, 'Independent rerender ' + postcode);
  }
  item.traces.push({ stage: 'three-rerenders', ...await snapshot(page) });
  await shot(page, width + '-' + kind + '-filled-step2');
  check(await page.locator('.ak-checkout-summary__method-chosen:visible').count() === 0, 'Step 2 does not finalize Woo defaults');
  check(await page.locator('.ak-checkout-summary__method-pending:visible').count() === 2, 'No orphan method labels');
  const sessionKeys = (await context.cookies()).filter(c => c.name.startsWith('wp_woocommerce_session_')).map(c => decodeURIComponent(c.value).split('|')[0]);
  item.sessionBeforeReload = sessionKeys.map(key => {
    assert(/^[a-zA-Z0-9_-]+$/.test(key));
    return woo(`$s=(new WC_Session_Handler())->get_session('${key}',false);echo json_encode(['customer'=>maybe_unserialize($s['customer']??[]),'draftId'=>$s['store_api_draft_order']??null]);`);
  });
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForSelector('#billing-fields');
  await settled(page, { billing, shipping });
  await editable(page, 'shipping'); await editable(page, 'billing');
  const restored = await snapshot(page); item.traces.push({ stage: 'same-session-reload', ...restored });
  compare(restored, { billing, shipping }, 'Legitimate restoration to recreated React fields');
  check(!restored.same, 'Different addresses keep same-address OFF on reload');
  check(restored.customer.billingAddress.email === item.email, 'Email restored from own session');
  await shot(page, width + '-' + kind + '-returning-step2');
  await same.check();
  await page.waitForFunction(() => !document.getElementById('billing-fields'));
  await settled(page, { shipping, billing: Object.fromEntries(keys.map(key => [key, shipping[key]])) });
  check(await page.locator('[data-ak-address-purpose="billing"]').count() === 0, 'Same-address hides redundant billing UI');
  await page.getByRole('button', { name: 'Tovább a szállítás és fizetéshez', exact: true }).click();
  await page.waitForFunction(() => document.body.dataset.akCheckoutStep === '3');
  await page.locator('#shipping-option input[value="gls_shipping_method"]').check();
  await page.locator('input[value="barion"]').check();
  await page.waitForFunction(() => { const c=wp.data.select('wc/store/cart'); return !c.isShippingRateBeingSelected() && c.getCartData().shippingRates.every(p=>p.shipping_rates.some(r=>r.rate_id==='gls_shipping_method'&&r.selected)); });
  await page.getByRole('button', { name: 'Tovább az összegzéshez', exact: true }).click();
  await page.waitForFunction(() => document.body.dataset.akCheckoutStep === '4');
  check((await page.locator('.ak-checkout-final-review').innerText()).includes('Fő fasor 12'), 'Shared address retained through Step 3/4');
  item.traces.push({ stage: 'shared-step4', ...await snapshot(page) });
  await Promise.all(pending);
  for (const purpose of ['billing', 'shipping']) check(item.requests.some(x => x[purpose]?.address_1 === ({billing,shipping})[purpose].address_1), 'Actual Store API partial updates contain native ' + purpose + ' entry');
  check(item.responses.some(x => x.billing?.address_1 === billing.address_1 && x.shipping?.address_1 === shipping.address_1), 'Actual Store API response preserves distinct addresses');
  check(item.errors.length === 0 && item.submissions === 0, 'No console errors or order submission');
}

(async () => {
  report.baseline = woo("echo json_encode(['stock'=>wc_get_product(334)->get_stock_quantity(),'drafts'=>wc_get_orders(['status'=>'checkout-draft','limit'=>-1,'return'=>'ids'])]);");
  const browser = await chromium.launch({ headless: true, executablePath: process.env.AK_CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', args: ['--disable-features=AutofillServerCommunication,AutofillEnableAccountWalletStorage'] });
  try {
    for (const width of (process.env.AK_UX_WIDTHS || '1440,390').split(',').map(Number)) for (const kind of (process.env.AK_UX_CASES || 'guest,empty-profile,woo-profile').split(',')) {
      const marker = `qa-address-origin-${Date.now()}-${width}`;
      const item = { kind, width, marker, email: marker + '@example.test', traces: [], requests: [], responses: [], errors: [], submissions: 0 };
      report.cases.push(item); save();
      let fixture;
      if (kind !== 'guest') {
        const profile = kind === 'woo-profile' ? { billing, shipping } : {};
        fixture = woo(`$id=wp_insert_user(['user_login'=>'${marker}','user_email'=>'${item.email}','user_pass'=>wp_generate_password(32),'role'=>'customer']);if(is_wp_error($id))throw new Exception($id->get_error_message());$c=new WC_Customer($id);$data=json_decode('${JSON.stringify(profile)}',true);foreach($data as $purpose=>$values)foreach($values as $key=>$value)$c->{'set_'.$purpose.'_'.$key}($value);$c->save();echo json_encode(['id'=>$id,'profile'=>['billing'=>$c->get_billing(),'shipping'=>$c->get_shipping()],'customCount'=>count($service->list($id)),'cookieName'=>LOGGED_IN_COOKIE,'cookie'=>wp_generate_auth_cookie($id,time()+3600,'logged_in')]);`);
        item.userId = fixture.id; item.initialProfile = fixture.profile;
        check(fixture.customCount === 0, 'Fixture has zero custom saved addresses');
      }
      const context = await browser.newContext({ viewport: { width, height: 1000 } });
      check((await context.cookies()).length === 0, 'Fresh context: no cookies');
      await context.addInitScript(() => { window.__qaStorageOnLoad = { local: Object.keys(localStorage), session: Object.keys(sessionStorage) }; });
      if (fixture) await context.addCookies([{ name: fixture.cookieName, value: fixture.cookie, url: base, httpOnly: true, sameSite: 'Lax' }]);
      const page = await context.newPage(); page.setDefaultTimeout(30000);
      const pending = [];
      page.on('pageerror', e => item.errors.push(e.message));
      page.on('console', m => { if (m.type() === 'error') item.errors.push(m.text()); });
      await context.route('**/*', route => {
        const r = route.request();
        if (new URL(r.url()).hostname === 'map.gls-croatia.com') return route.fulfill({ contentType: 'application/javascript', body: "customElements.define('gls-dpm-dialog',class extends HTMLElement{showModal(){throw new Error('External locator excluded');}});" });
        if (r.method() === 'POST' && decodeURIComponent(r.url()).includes('/wc/store/v1/checkout') && !r.url().includes('__experimental_calc_totals=true')) { item.submissions++; return route.abort(); }
        return route.continue();
      });
      page.on('request', r => {
        if (decodeURIComponent(r.url()).includes('/wc/store/')) try { item.requests.push(...addressesFromPayload(r.postDataJSON())); } catch { /* No body. */ }
      });
      page.on('response', r => {
        if (decodeURIComponent(r.url()).includes('/wc/store/')) pending.push(r.json().then(data => item.responses.push(...addressesFromPayload(data))).catch(() => {}));
      });
      try {
        await exercise(page, context, item, fixture, pending);
        item.pass = true;
      } catch (error) {
        item.failure = error.message; item.traces.push({ stage: 'failure', ...await snapshot(page).catch(() => ({})) });
        await shot(page, width + '-' + kind + '-failure').catch(() => {});
        throw error;
      } finally {
        item.sessions = (await context.cookies()).filter(c => c.name.startsWith('wp_woocommerce_session_')).map(c => decodeURIComponent(c.value).split('|')[0]);
        for (const key of item.sessions) {
          assert(/^[a-zA-Z0-9_-]+$/.test(key));
          woo(`$s=(new WC_Session_Handler())->get_session('${key}',false);$id=(int)($s['store_api_draft_order']??0);if($id){$o=wc_get_order($id);if($o){if(in_array($id,${JSON.stringify(report.baseline.drafts)},true)||$o->get_status()!=='checkout-draft'||($o->get_billing_email()!==''&&$o->get_billing_email()!=='${item.email}'))throw new Exception('Unsafe draft');$o->delete(true);}}(new WC_Session_Handler())->delete_session('${key}');echo json_encode(true);`);
        }
        await context.close();
        if (fixture) woo(`$u=get_user_by('id',${fixture.id});if(!$u||$u->user_login!=='${item.marker}')throw new Exception('Exact QA user only');$service->eraseForCustomer(${fixture.id});require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user(${fixture.id});echo json_encode(true);`);
        check(woo('echo json_encode(wc_get_product(334)->get_stock_quantity());') === report.baseline.stock, 'Stock unchanged');
        item.cleaned = true; save();
      }
    }
  } finally { await browser.close(); save(); }
  check(report.presentationFailures.length === 0, 'Sidebar must respect independent billing, including empty billing'); save();
  console.log(`Address initialization: ${report.assertions} native assertions passed. Evidence: ${output}`);
})().catch(error => { console.error(error); process.exitCode = 1; });
