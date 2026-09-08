'use strict';

// Isolated local saved-address presentation gate. No checkout submission.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const base = 'http://localhost:8080';
const output = process.env.AK_UX_OUTPUT || fs.mkdtempSync('/tmp/checkout-final-addresses-');
fs.mkdirSync(output, { recursive: true });
const service = String.raw`$service=new \AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService(new \AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressAddressRepository($wpdb),new \AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressTransactionManager($wpdb),new \AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooUserMetaProjection(),new \AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooAllowedCountries());`;
function woo(code) {
  return JSON.parse(execFileSync('docker', ['exec', process.env.AK_WP_CONTAINER || 'appleklinikawebshop-wordpress-1', 'php', '-r', `require '/var/www/html/wp-load.php';if(home_url()!=='${base}')throw new Exception('Local only');${service}${code}`], { encoding: 'utf8' }));
}
const report = { assertions: 0, errors: [], lifecycle: [], submissions: 0 };
function check(value, message) { report.assertions++; assert(value, message); }
(async () => {
  const marker = 'qa-final-ux-address-' + Date.now();
  const fixture = woo(`$id=wp_insert_user(['user_login'=>'${marker}','user_email'=>'${marker}@example.test','user_pass'=>wp_generate_password(32),'role'=>'customer']);if(is_wp_error($id))throw new Exception($id->get_error_message());try{$data=['label'=>'QA shipping','capabilities'=>2,'first_name'=>'Elek','last_name'=>'Teszt','country'=>'HU','postcode'=>'6726','city'=>'Szeged','address_1'=>'Fő fasor','house_number'=>'12','phone'=>'+36301234567','email'=>'${marker}@example.test','status'=>'active','source'=>'account'];$shipping=$service->create($id,$data,false,true);$data=array_merge($data,['label'=>'QA company billing','capabilities'=>1,'company_name'=>'QA Mentett Cím Kft.','tax_number'=>'12345678-1-23','address_1'=>'Számlázási utca','house_number'=>'24']);$billing=$service->create($id,$data,true,false);echo json_encode(['id'=>$id,'billing'=>$billing->key().'|'.$billing->version(),'cookieName'=>LOGGED_IN_COOKIE,'cookie'=>wp_generate_auth_cookie($id,time()+3600,'logged_in'),'stock'=>wc_get_product(334)->get_stock_quantity(),'drafts'=>wc_get_orders(['status'=>'checkout-draft','limit'=>-1,'return'=>'ids'])]);}catch(Throwable $e){$service->eraseForCustomer($id);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($id);throw $e;}`);
  report.customerId = fixture.id;
  let browser, context, page;
  try {
    browser = await chromium.launch({ headless: true, executablePath: process.env.AK_CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
    context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    await context.addCookies([{ name: fixture.cookieName, value: fixture.cookie, url: base, httpOnly: true, sameSite: 'Lax' }]);
    await context.route('**/*', route => {
      if (route.request().method() === 'POST' && decodeURIComponent(route.request().url()).includes('/wc/store/v1/checkout') && !route.request().url().includes('__experimental_calc_totals=true')) {
        report.submissions++; return route.abort();
      }
      return route.continue();
    });
    page = await context.newPage(); page.setDefaultTimeout(30000);
    page.on('pageerror', e => report.errors.push(e.message));
    page.on('console', m => { if (m.type() === 'error') report.errors.push(m.text()); });
    await page.goto(base + '/?post_type=product&p=334', { waitUntil: 'networkidle' });
    await page.locator('.single_add_to_cart_button').click();
    await page.waitForFunction(() => document.querySelector('.ak-cart-count')?.textContent.trim() === '1');
    await page.goto(base + '/?page_id=9', { waitUntil: 'networkidle' });
    fs.writeFileSync(output + '/checkout-header.html', await page.locator('.wp-site-blocks > header').evaluate(x => x.outerHTML));
    const same = page.locator('#shipping-fields .wc-block-checkout__use-address-for-billing input');
    const selector = page.locator('#ak-checkout-address-selector-billing');
    for (const on of [false, true, false, true, false]) {
      await same.setChecked(on);
      await page.waitForFunction(on => document.querySelectorAll('#ak-checkout-address-selector-billing').length === (on ? 0 : 1), on);
      const count = await selector.count(); report.lifecycle.push(count);
      check(count === (on ? 0 : 1), 'One selector in every separate-billing state');
      if (!on) {
        await selector.selectOption('__one_off__');
        await selector.selectOption(fixture.billing);
        await page.waitForFunction(() => wp.data.select('wc/store/cart').getCustomerData().billingAddress.company === 'QA Mentett Cím Kft.');
        check(await page.locator('#order-appleklinika-company_purchase').isChecked(), 'Saved company identity restores COMPANY');
        check(await page.locator('#order-appleklinika-tax_number').inputValue() === '12345678-1-23', 'Saved tax preserved');
        check(await page.locator('#billing-address_1').inputValue() === 'Számlázási utca', 'Saved street preserved');
        check(await page.locator('#billing-appleklinika-house_number').inputValue() === '24', 'Saved house preserved');
        check(await selector.evaluate(x => x.isConnected && x.closest('#billing-fields') === document.getElementById('billing-fields')), 'Only live billing host is authoritative');
      }
      check(await page.locator('#order-appleklinika-company_purchase').count() === 1, 'One company decision after remount');
    }
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.locator('#order-fields').scrollIntoViewIfNeeded();
      await page.screenshot({ path: output + '/' + width + '-saved-company.png', fullPage: true });
      await page.locator('#order-fields').screenshot({ path: output + '/' + width + '-company-detail.png' });
      check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No saved-address overflow');
      check(!await page.locator('#billing-first_name').isVisible(), 'Hidden technical personal names for saved company');
    }
    check(report.errors.length === 0, 'No console/runtime errors');
    check(report.submissions === 0, 'No submission');
    report.pass = true;
  } catch (error) {
    report.failure = error.message;
    throw error;
  } finally {
    if (page) await page.goto(base + '/?page_id=8', { waitUntil: 'networkidle' }).then(async () => {
      const remove = page.locator('a.ak-cart-item__remove');
      assert(await remove.count() <= 1);
      if (await remove.count()) await remove.click();
      await page.waitForFunction(() => document.querySelector('.ak-cart-count')?.textContent.trim() === '0');
    });
    if (context) await context.close();
    if (browser) await browser.close();
    report.cleanup = woo(`$u=get_user_by('id',${fixture.id});if(!$u||$u->user_login!=='${marker}')throw new Exception('Exact fixture required');$orders=wc_get_orders(['customer_id'=>${fixture.id},'limit'=>-1]);foreach($orders as $o){if($o->get_status()!=='checkout-draft'||in_array($o->get_id(),${JSON.stringify(fixture.drafts)},true))throw new Exception('Unexpected order');$o->delete(true);}$service->eraseForCustomer(${fixture.id});(new WC_Session_Handler())->delete_session('${fixture.id}');require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user(${fixture.id});echo json_encode(['userRemoved'=>get_user_by('id',${fixture.id})===false,'addresses'=>count($service->list(${fixture.id})),'stock'=>wc_get_product(334)->get_stock_quantity()]);`);
    check(report.cleanup.userRemoved && report.cleanup.addresses === 0 && report.cleanup.stock === fixture.stock, 'Exact fixture removed; stock unchanged');
    fs.writeFileSync(output + '/result.json', JSON.stringify(report, null, 2));
  }
  console.log(`Saved-address UX: ${report.assertions} assertions passed. Evidence: ${output}`);
})().catch(error => { console.error(error); process.exitCode = 1; });
