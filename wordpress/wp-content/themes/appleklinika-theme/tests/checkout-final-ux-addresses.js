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
async function fill(page, id, value) {
  const purpose = id.startsWith('shipping-') ? 'shipping' : (id.startsWith('billing-') ? 'billing' : null);
  if (purpose && !await page.locator('#' + id).isVisible()) await editable(page, purpose);
  await page.locator('#' + id).fill(value);
  await page.locator('#' + id).press('Tab');
}
async function editable(page, purpose) {
  const disclosure = page.locator('[data-ak-address-purpose="' + purpose + '"] .ak-checkout-address-selector__editor');
  if (await disclosure.isVisible()) {
    if (!await disclosure.evaluate(x=>x.open)) await disclosure.locator('summary').click();
    return;
  }
  const edit = page.locator('#' + purpose + '-fields .wc-block-components-address-card__edit');
  if (await edit.isVisible()) await edit.click();
}
async function address(page, purpose, phone) {
  await editable(page, purpose);
  await page.locator('#' + purpose + '-country').selectOption('HU');
  const data = { postcode: '6726', city: 'Szeged', address_1: 'Fő fasor', 'appleklinika-house_number': '12', phone };
  for (const [key,value] of Object.entries(data)) await fill(page, purpose + '-' + key, value);
  if (await page.locator('#' + purpose + '-first_name').isVisible()) {
    await fill(page,purpose+'-first_name','Elek'); await fill(page,purpose+'-last_name','Teszt');
  }
}
async function settled(page, postcode, rate = null) {
  await page.waitForFunction(({postcode,rate}) => {
    const c=wp.data.select('wc/store/cart'), p=c.getCartData().shippingRates || [];
    return !c.isCustomerDataUpdating() && !c.isAddressFieldsForShippingRatesUpdating() && !c.isShippingRateBeingSelected()
      && p.length && p.every(x=>x.destination.postcode===postcode && (!rate || x.shipping_rates.some(r=>r.rate_id===rate&&r.selected)));
  },{postcode,rate});
}
async function shot(page, name) {
  await page.evaluate(()=>window.scrollTo(0,0));
  await page.getByRole('link',{name:'Vissza a kosárhoz',exact:true}).first().focus();
  await page.screenshot({path:output+'/'+name+'.png',fullPage:true});
  if (process.env.AK_UX_CAPTURE_DOM === '1') fs.writeFileSync(output+'/'+name+'.html', await page.locator('.wc-block-checkout__form').evaluate(x=>x.outerHTML));
  if(name.endsWith('step2')||name.endsWith('same-address')) {
    await page.locator('#shipping-fields').screenshot({path:output+'/'+name+'-shipping-detail.png'});
    await page.locator('#order-fields').screenshot({path:output+'/'+name+'-identity-detail.png'});
  }
}
(async () => {
  const marker = 'qa-final-ux-address-' + Date.now();
  const fixture = woo(`$id=wp_insert_user(['user_login'=>'${marker}','user_email'=>'${marker}@example.test','user_pass'=>wp_generate_password(32),'role'=>'customer']);if(is_wp_error($id))throw new Exception($id->get_error_message());try{$data=['label'=>'QA shipping','capabilities'=>2,'first_name'=>'Elek','last_name'=>'Teszt','country'=>'HU','postcode'=>'6726','city'=>'Szeged','address_1'=>'Fő fasor','house_number'=>'12','phone'=>'+36301234567','email'=>'${marker}@example.test','status'=>'active','source'=>'account'];$shipping=$service->create($id,$data,false,true);$data=array_merge($data,['label'=>'QA company billing','capabilities'=>1,'company_name'=>'QA Mentett Cím Kft.','tax_number'=>'12345678-1-23','address_1'=>'Számlázási utca','house_number'=>'24']);$billing=$service->create($id,$data,true,false);echo json_encode(['id'=>$id,'billing'=>$billing->key().'|'.$billing->version(),'cookieName'=>LOGGED_IN_COOKIE,'cookie'=>wp_generate_auth_cookie($id,time()+3600,'logged_in'),'stock'=>wc_get_product(334)->get_stock_quantity(),'drafts'=>wc_get_orders(['status'=>'checkout-draft','limit'=>-1,'return'=>'ids'])]);}catch(Throwable $e){$service->eraseForCustomer($id);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($id);throw $e;}`);
  report.customerId = fixture.id;
  if (process.env.AK_UX_NO_SAVED === '1') {
    woo(`$service->eraseForCustomer(${fixture.id});echo json_encode(true);`);
    fixture.billing = null;
  }
  let browser, context, page;
  try {
    browser = await chromium.launch({ headless: true, executablePath: process.env.AK_CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
    context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    await context.addCookies([{ name: fixture.cookieName, value: fixture.cookie, url: base, httpOnly: true, sameSite: 'Lax' }]);
    await context.route('**/*', route => {
      // Prices/method controls are real; the external pickup map is out of scope.
      if (new URL(route.request().url()).hostname === 'map.gls-croatia.com') {
        return route.fulfill({contentType:'application/javascript',body:"customElements.define('gls-dpm-dialog',class extends HTMLElement{showModal(){throw new Error('Live GLS locator is outside this presentation test');}});"});
      }
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
    await fill(page,'email',marker+'@example.test');
    const same = page.locator('#shipping-fields .wc-block-checkout__use-address-for-billing input');
    const selector = page.locator('#ak-checkout-address-selector-billing');
    await shot(page, '1440-initial-saved-step2');
    await editable(page,'shipping');
    if (fixture.billing) {
      const shippingSelector=page.locator('#ak-checkout-address-selector-shipping');
      const savedValue=await shippingSelector.locator('option').first().getAttribute('value');
      await shippingSelector.selectOption('__one_off__');
      check(await page.locator('[data-ak-address-purpose="shipping"] .ak-checkout-address-selector__save summary').isVisible(),'Manual shipping exposes the optional save disclosure');
      check(!await page.locator('[data-ak-address-purpose="shipping"] [data-ak-address-save]').isVisible(),'Optional save checkbox is collapsed by default');
      await page.locator('[data-ak-address-purpose="shipping"] .ak-checkout-address-selector__save summary').click();
      check(await page.locator('[data-ak-address-purpose="shipping"] [data-ak-address-save]').isVisible(),'Native disclosure reveals existing save preference');
      await page.locator('[data-ak-address-purpose="shipping"] .ak-checkout-address-selector__save summary').click();
      await shippingSelector.selectOption(savedValue);
      await page.waitForFunction(()=>document.querySelector('#shipping-postcode')?.value==='6726');
      check(!await page.locator('[data-ak-address-purpose="shipping"] [data-ak-address-save]').isVisible(),'Saved shipping hides save preference');
      check(await shippingSelector.locator('option').last().innerText()==='Másik cím használata','Manual option is last');
      check(await page.locator('[data-ak-address-purpose="shipping"] .ak-checkout-address-selector__editor summary').isVisible(),'Saved shipping has one compact edit disclosure');
      check(!await page.locator('#shipping-country').isVisible(),'Saved choice is compact without redundant form');
    } else {
      check(await page.locator('[id^="ak-checkout-address-selector-"]').count()===0,'No saved addresses: no pointless selector');
      check(await page.locator('#shipping-country').isVisible(),'No saved addresses: normal editable form directly, even with legacy profile values');
      await address(page,'shipping','+36301234567');
    }
    for (const on of [false, true, false, true, false]) {
      await same.setChecked(on);
      await page.waitForFunction(on => Boolean(document.getElementById('billing-fields')) === !on, on);
      await page.waitForFunction(on => document.querySelector('.ak-checkout-same-address-help')?.hidden === !on,on);
      const count = await selector.count(); report.lifecycle.push(count);
      check(count === (on || !fixture.billing ? 0 : 1), 'Exactly the selector required for the live billing state');
      check(await page.locator('.ak-checkout-same-address-help').isVisible()===on,'Same-address explanation follows native control');
      if (!on && fixture.billing) {
        await editable(page,'billing');
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
      for (const mode of ['personal','company']) {
        await page.locator('[data-checkout-step-trigger="2"]').click();
        await address(page,'shipping','+36301234567');
        await page.locator('#order-appleklinika-company_purchase').setChecked(mode==='company');
        if(mode==='company'){
          await fill(page,'order-appleklinika-company_name','QA Egyező Cím Kft.');
          await fill(page,'order-appleklinika-tax_number','12345678-1-23');
        }
        await same.check();
        await page.waitForFunction(()=>!document.getElementById('billing-fields')&&!document.querySelector('.ak-checkout-same-address-help')?.hidden);
        check(await selector.count()===0,'Same-address ON has no redundant billing selector');
        check(await page.locator('#order-appleklinika-company_purchase').count()===1,'Same-address ON keeps one billing identity decision');
        if(mode==='company')check(await page.locator('#order-appleklinika-company_name').inputValue()==='QA Egyező Cím Kft.'&&await page.locator('#order-appleklinika-tax_number').inputValue()==='12345678-1-23','Same-address ON preserves company identity');
        await settled(page,'6726');
        await shot(page,width+'-'+mode+'-same-address');
        await page.getByRole('button',{name:'Tovább a szállítás és fizetéshez',exact:true}).click();
        await page.waitForFunction(()=>document.body.dataset.akCheckoutStep==='3');
        await page.locator('#shipping-option input[value="gls_shipping_method"]').check();await settled(page,'6726','gls_shipping_method');
        await page.locator('input[value="barion"]').check();
        await page.getByRole('button',{name:'Tovább az összegzéshez',exact:true}).click();
        await page.waitForFunction(()=>document.body.dataset.akCheckoutStep==='4');
        const sharedReview=await page.locator('.ak-checkout-final-review').innerText();
        check(sharedReview.includes('Fő fasor 12')&&sharedReview.includes('Barion')&&sharedReview.includes('+36301234567'),'Same-address ON completes Step 2/3/4 with shipping/contact/payment');
        if(mode==='company')check(sharedReview.includes('QA Egyező Cím Kft.')&&sharedReview.includes('12345678-1-23'),'Same-address ON final review retains company/tax');
        const sharedBilling=await page.locator('.ak-checkout-final-review__timeline-item').filter({has:page.getByRole('heading',{name:'Számlázási adatok',exact:true})}).innerText();
        check(sharedBilling.includes('Fő fasor 12')&&sharedBilling.includes('6726 Szeged'),'Same-address ON billing review explicitly contains the effective shipping address, including COMPANY');
        check(await page.evaluate(()=>Object.keys(wp.data.select('wc/store/validation').getValidationErrors()).every(k=>/terms/i.test(k))),'Same-address ON has no stale validation');
        await shot(page,width+'-'+mode+'-same-address-step4');
        await page.locator('[data-checkout-step-trigger="2"]').click();
        await same.uncheck();
        await editable(page,'billing');
        if (fixture.billing) {
          const shippingSelector=page.locator('#ak-checkout-address-selector-shipping');
          await shippingSelector.selectOption('__one_off__');
          if(mode==='company')await shippingSelector.selectOption(await shippingSelector.locator('option').first().getAttribute('value'));
          check(await page.locator('[data-ak-address-purpose="shipping"] .ak-checkout-address-selector__save summary').isVisible()===(mode==='personal'),'Shipping manual/saved states expose only the correct save disclosure');
          await selector.selectOption('__one_off__');
          if(mode==='company')await selector.selectOption(fixture.billing);
        }
        await page.locator('#order-appleklinika-company_purchase').setChecked(mode==='company');
        await address(page,'shipping','+36301234567');
        if(mode==='personal'||!fixture.billing)await address(page,'billing','+36307654321');
        else await fill(page,'billing-phone','+36307654321');
        if(mode==='company') {
          await fill(page,'order-appleklinika-company_name',fixture.billing?'QA Mentett Cím Kft.':'QA Kézi Cím Kft.');
          await fill(page,'order-appleklinika-tax_number','12345678-1-23');
        }
        for(const postcode of ['6724','6725','6726']) {
          await fill(page,'shipping-postcode',postcode); await settled(page,postcode);
          check(await page.locator('#email').inputValue()===marker+'@example.test','Latest email survives rerender');
          check(await page.locator('#billing-phone').inputValue()==='+36307654321'&&await page.locator('#shipping-phone').inputValue()==='+36301234567','Both phones survive rerender');
          check(await page.locator('#order-appleklinika-company_purchase').isChecked()===(mode==='company'),'Billing mode survives rerender');
          if(mode==='company')check(await page.locator('#order-appleklinika-tax_number').inputValue()==='12345678-1-23','Tax survives rerender');
          check(await page.evaluate(()=>['billing','shipping'].every(p=>{
            const el=document.getElementById(p+'-fields');
            return el&&el.isConnected&&el.querySelectorAll('[data-ak-address-purpose]').length===1&&el.closest('.wc-block-checkout__form');
          })),'Three rerenders retain single connected hosts and their original React-owned inputs');
        }
        check(await page.locator('[data-ak-address-purpose="billing"] .ak-checkout-address-selector__save summary').isVisible()===(mode==='personal'||!fixture.billing),'Save disclosure only in manual billing state');
        check(await page.locator('.ak-checkout-billing-title:visible').count()===1,'One visible billing heading joins same-address and company identity');
        check(await page.locator('.ak-checkout-decision input').count()===2,'Exactly two original billing decision inputs');
        check(await page.locator('.ak-checkout-decision input').evaluateAll(xs=>xs.every(x=>x.getBoundingClientRect().width===42&&x.getBoundingClientRect().height===26)),'Billing switches have consistent dimensions');
        check(await page.locator('.ak-checkout-profile-save__helper').isVisible()===false,'Unchecked profile preference has no long helper paragraph');
        await shot(page,width+'-'+mode+'-step2');
        if(mode==='company'&&fixture.billing) {
          for(const purpose of ['shipping','billing']) await page.locator('[data-ak-address-purpose="'+purpose+'"] .ak-checkout-address-selector__editor[open] > summary').click();
          check(!await page.locator('#shipping-country').isVisible()&&!await page.locator('#billing-country').isVisible(),'Both saved addresses collapse without removing native fields');
          await shot(page,width+'-'+mode+'-saved-compact-step2');
        }
        check(await page.evaluate(()=>['shipping','billing'].every(p=>{
          const host=document.getElementById(p+'-fields'), heading=host.querySelector('.wc-block-components-checkout-step__heading'), section=host.querySelector('[data-ak-address-purpose]');
          return heading && (heading.compareDocumentPosition(section)&Node.DOCUMENT_POSITION_FOLLOWING) && section.parentElement===host;
        })),'Address choices follow their own headings, with no reparented native input');
        check(await page.locator('.ak-checkout-address-selector__manual-help:visible').count()===(mode==='personal'||!fixture.billing?2:0),'Only manual entry displays the manual-address explanation');
        check(await page.locator('.ak-checkout-summary__method-chosen:visible').count()===0,'Step 2 has no premature selected methods');
        await page.getByRole('button',{name:'Tovább a szállítás és fizetéshez',exact:true}).click();
        await page.waitForFunction(()=>document.body.dataset.akCheckoutStep==='3');
        await page.locator('#shipping-option input[value="gls_shipping_method"]').check(); await settled(page,'6726','gls_shipping_method');
        await page.locator('input[value="barion"]').check();
        await shot(page,width+'-'+mode+'-step3');
        await page.getByRole('button',{name:'Tovább az összegzéshez',exact:true}).click();
        await page.waitForFunction(()=>document.body.dataset.akCheckoutStep==='4');
        const review=await page.locator('.ak-checkout-final-review').innerText();
        for(const value of [marker+'@example.test','+36301234567','+36307654321','Fő fasor','Barion','Kézbesítés címre'])check(review.includes(value),'Final review contains '+value);
        if(mode==='company')check(review.includes('12345678-1-23'),'Final company tax');
        check(await page.evaluate(()=>Object.keys(wp.data.select('wc/store/validation').getValidationErrors()).every(k=>/terms/i.test(k))),'Only unaccepted legal gate remains');
        await shot(page,width+'-'+mode+'-step4');
        check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No horizontal overflow');
        check(await page.locator('#order-appleklinika-company_purchase').count()===1,'One company control');
      }
    }
    if (fixture.billing) {
      await page.locator('[data-checkout-step-trigger="2"]').click();
      await editable(page,'billing');
      await fill(page,'billing-postcode','');
      const editor=page.locator('[data-ak-address-purpose="billing"] .ak-checkout-address-selector__editor');
      if (await editor.evaluate(x=>x.open)) await editor.locator('summary').click();
      await page.getByRole('button',{name:'Tovább a szállítás és fizetéshez',exact:true}).click();
      await page.waitForFunction(()=>document.querySelector('[data-ak-address-purpose="billing"] .ak-checkout-address-selector__editor')?.open);
      check(await page.locator('body').getAttribute('data-ak-checkout-step')==='2','Invalid saved editor still blocks progression');
      check(await page.locator('#billing-postcode').isVisible(),'Validation reveals the original invalid field, not a duplicate');
      await fill(page,'billing-postcode','6726');
      await page.getByRole('button',{name:'Tovább a szállítás és fizetéshez',exact:true}).click();
      await page.waitForFunction(()=>document.body.dataset.akCheckoutStep==='3');
      check(true,'Corrected saved address progresses normally');
    }
    check(report.errors.length === 0, 'No console/runtime errors');
    check(report.submissions === 0, 'No submission');
    report.pass = true;
  } catch (error) {
    report.failure = error.message;
    if(page){await page.screenshot({path:output+'/failure.png',fullPage:true});fs.writeFileSync(output+'/failure-dom.html',await page.locator('.wc-block-checkout__form').evaluate(x=>x.outerHTML));report.validation=await page.evaluate(()=>({step:document.body.dataset.akCheckoutStep,errors:wp.data.select('wc/store/validation').getValidationErrors(),cart:wp.data.select('wc/store/cart').getCustomerData()}));}
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
