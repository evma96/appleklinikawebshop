'use strict';

// LOCAL native login/logout and presentation gate. No order or external API.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const base = 'http://localhost:8080';
const output = process.env.AK_UX_OUTPUT || fs.mkdtempSync('/tmp/checkout-controls-');
fs.mkdirSync(output, { recursive: true });
const report = { assertions: 0, cases: [], errors: [], submissions: 0 };
const auditOnly = process.env.AK_UX_AUDIT_ONLY === '1';
const service = String.raw`$service=new \AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService(new \AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressAddressRepository($wpdb),new \AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressTransactionManager($wpdb),new \AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooUserMetaProjection(),new \AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooAllowedCountries());`;
function woo(code) {
  return JSON.parse(execFileSync('docker', ['exec','appleklinikawebshop-wordpress-1','php','-r',`require '/var/www/html/wp-load.php';if(home_url()!=='${base}')throw new Exception('LOCAL only');${service}${code}`], {encoding:'utf8'}));
}
function check(value, message) { report.assertions++; assert(value, message); }
function save() { fs.writeFileSync(output+'/result.json', JSON.stringify(report,null,2)); }
async function fill(page,id,value) { await page.locator('#'+id).fill(value); await page.locator('#'+id).press('Tab'); }
async function snapshot(page,context,item,stage) {
  const data=await page.evaluate(()=>{
    const store=wp.data.select('wc/store/cart');
    const fields={};
    for(const id of ['email','shipping-first_name','shipping-last_name','shipping-address_1','shipping-postcode','shipping-phone','billing-first_name','billing-last_name','billing-address_1','billing-postcode','billing-phone','order-appleklinika-company_name','order-appleklinika-tax_number']) {
      const x=document.getElementById(id);fields[id]=x?{value:x.value,placeholder:x.getAttribute('placeholder'),required:x.required,missing:x.validity.valueMissing}:null;
    }
    return {fields,customer:store.getCustomerData(),cart:store.getCartData(),additional:wp.data.select('wc/store/checkout').getAdditionalFields(),autofill:[...document.querySelectorAll('input:-webkit-autofill')].map(x=>x.id)};
  });
  const cookies=(await context.cookies()).filter(c=>c.name.startsWith('wp_woocommerce_session_'));
  const keys=cookies.map(c=>decodeURIComponent(c.value).split('|')[0]);
  keys.forEach(k=>{assert(/^[a-zA-Z0-9_-]+$/.test(k));if(!item.sessions.includes(k))item.sessions.push(k);});
  data.serverSessions=keys.map(k=>woo(`$s=(new WC_Session_Handler())->get_session('${k}',false);echo json_encode(['customer'=>maybe_unserialize($s['customer']??[])]);`));
  item.traces.push({stage,...data});save();return data;
}
async function shot(page,name,item) {
  await page.evaluate(()=>window.scrollTo(0,0));
  await page.screenshot({path:output+'/'+name+'.png',fullPage:true});
  if(await page.locator('#order-notes').isVisible()) await page.locator('#order-notes').screenshot({path:output+'/'+name+'-note.png'});
  const metrics=await page.evaluate(()=>({
    boxes:[...document.querySelectorAll('input[type=checkbox]')].filter(x=>x.getClientRects().length).map(x=>{
      const r=x.getBoundingClientRect(), label=x.closest('label'), mark=label?.querySelector('svg'), m=mark?.getClientRects().length?mark.getBoundingClientRect():null;
      return {id:x.id,checked:x.checked,box:{x:r.x,y:r.y,w:r.width,h:r.height},labelHeight:label?.getBoundingClientRect().height,mark:m?{x:m.x,y:m.y,w:m.width,h:m.height}:null};
    }),price:[...document.querySelectorAll('.ak-cart-item__price strong,.ak-cart-item__price strong .amount,.ak-cart-item__price > span,.ak-cart-item__price del')].map(x=>({text:x.textContent,font:getComputedStyle(x).fontSize})),overflow:document.documentElement.scrollWidth>innerWidth
  }));
  item.visual.push({name,...metrics});
  check(!metrics.overflow,name+': no overflow');
  if(!auditOnly)for(const b of metrics.boxes){
    check(b.labelHeight>=44,b.id+': accessible label target');
    if(b.mark)check(Math.abs(b.box.x+b.box.w/2-b.mark.x-b.mark.w/2)<1.5&&Math.abs(b.box.y+b.box.h/2-b.mark.y-b.mark.h/2)<1.5,b.id+': centered checkmark');
  }
  if(!auditOnly&&metrics.price.length){
    const current=metrics.price.filter(x=>x.text.includes('314'));
    check(current.length===2&&current.every(x=>x.font==='23px'),name+': current price retains 23px through native Woo amount spans');
    check(metrics.price.filter(x=>!x.text.includes('314')).every(x=>x.font==='12px'),name+': old price and saving remain secondary');
  }
}
async function checkout(page) {
  await page.goto(base+'/?post_type=product&p=334',{waitUntil:'networkidle'});
  const count=Number((await page.locator('.ak-cart-count').innerText()).trim());
  check(count===0||count===1,'Only own one-item QA cart');
  if(!count){await page.locator('.single_add_to_cart_button').click();await page.waitForFunction(()=>document.querySelector('.ak-cart-count')?.textContent.trim()==='1');}
  await page.goto(base+'/?page_id=9',{waitUntil:'networkidle'});
  await page.locator('.wc-block-checkout__use-address-for-billing input').uncheck();
  await page.waitForSelector('#billing-fields');
  await page.waitForLoadState('networkidle');
}
async function login(page,user,password) {
  await page.goto(base+'/wp-login.php',{waitUntil:'networkidle'});
  await page.locator('#user_login').fill(user.login);await page.locator('#user_pass').fill(password);
  await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/wp-login.php')),page.locator('#wp-submit').click()]);
}
async function logout(page,accountUrl) {
  await page.goto(accountUrl,{waitUntil:'networkidle'});
  const link=page.locator('a[href*="customer-logout"],a[href*="action=logout"]').first();
  await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),link.click()]);
  check(await page.locator('#username').isVisible(),'Logout returns to guest login form');
}
function absence(data, needles, label) {
  const text=JSON.stringify({fields:data.fields,customer:data.customer,additional:data.additional,session:data.serverSessions});
  for(const value of needles)check(!text.includes(value),label+': no '+value);
}
(async()=>{
  report.baseline=woo("echo json_encode(['stock'=>wc_get_product(334)->get_stock_quantity(),'drafts'=>wc_get_orders(['status'=>'checkout-draft','limit'=>-1,'return'=>'ids']),'accountUrl'=>wc_get_page_permalink('myaccount')]);");
  const browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
  try { for(const width of [1440,390]) {
    const marker='qa-control-session-'+Date.now()+'-'+width,password=randomBytes(24).toString('hex');
    const item={width,marker,traces:[],visual:[],sessions:[],users:[]};report.cases.push(item);save();
    for(const letter of ['A','B']){
      const login=marker+'-'+letter,email=login+'@example.test',first=letter==='A'?'Aladár':'Borbála',phone=letter==='A'?'+36201110001':'+36202220002';
      const id=woo(`$id=wp_insert_user(['user_login'=>'${login}','user_email'=>'${email}','user_pass'=>'${password}','role'=>'customer']);if(is_wp_error($id))throw new Exception('QA create failed');$c=new WC_Customer($id);$c->set_billing_first_name('${first}');$c->set_billing_last_name('Teszt${letter}');$c->set_billing_email('${email}');$c->set_billing_phone('${phone}');$c->set_billing_country('HU');$c->set_billing_city('Szeged');$c->set_billing_postcode('6726');$c->set_billing_address_1('Fiók ${letter} utca');$c->set_shipping_first_name('${first}');$c->set_shipping_last_name('Teszt${letter}');$c->set_shipping_phone('${phone}');$c->set_shipping_country('HU');$c->set_shipping_city('Szeged');$c->set_shipping_postcode('6726');$c->set_shipping_address_1('Fiók ${letter} utca');$c->save();update_user_meta($id,'billing_appleklinika/house_number','12');update_user_meta($id,'shipping_appleklinika/house_number','12');echo json_encode($id);`);
      item.users.push({id,login,email,first,phone});save();
    }
    const context=await browser.newContext({viewport:{width,height:1000}}),page=await context.newPage();page.setDefaultTimeout(30000);
    page.on('pageerror',e=>report.errors.push(e.message));page.on('console',m=>{if(m.type()==='error')report.errors.push(m.text());});
    await context.route('**/*',route=>{
      const r=route.request();
      if(new URL(r.url()).hostname==='map.gls-croatia.com')return route.fulfill({contentType:'application/javascript',body:"customElements.define('gls-dpm-dialog',class extends HTMLElement{showModal(){throw new Error('External locator excluded');}});"});
      if(r.method()==='POST'&&decodeURIComponent(r.url()).includes('/wc/store/v1/checkout')&&!r.url().includes('__experimental_calc_totals=true')){report.submissions++;return route.abort();}
      return route.continue();
    });
    try {
      await checkout(page);
      let data=await snapshot(page,context,item,'fresh-guest');
      check(Object.values(data.fields).every(x=>!x||x.value===''),'Fresh guest identity/address fields all empty');
      for(const prefix of ['shipping','billing']){
        check(data.fields[prefix+'-phone'].value===''&&data.customer[prefix+'Address'].phone==='','Fresh '+prefix+' phone is empty');
        check(data.fields[prefix+'-phone'].missing,'Empty phone remains natively required');
        if(!auditOnly)check(data.fields[prefix+'-phone'].placeholder==='+36 30 123 4567','Phone example is only a placeholder');
        if(!auditOnly)check(await page.locator('#'+prefix+'-phone').evaluate(x=>getComputedStyle(x,'::placeholder').opacity==='0'),'Unfocused hint never overlaps Woo floating label');
      }
      check(data.autofill.length===0,'No browser autofill in fresh context');
      await shot(page,width+'-guest-step2',item);
      await page.locator('#shipping-phone').focus();
      if(!auditOnly)check(await page.locator('#shipping-phone').evaluate(x=>getComputedStyle(x,'::placeholder').opacity==='1'),'Focused phone hint visible without setting a value');
      // Keep the field below the fixed header when capturing the focused hint.
      await page.evaluate(()=>window.scrollBy(0,document.getElementById('shipping-phone').getBoundingClientRect().top-innerHeight/2));
      await page.locator('#shipping-phone').locator('..').screenshot({path:output+'/'+width+'-phone-placeholder.png'});
      const email=marker+'-guest@example.test';item.guestEmail=email;
      await fill(page,'email',email);
      for(const prefix of ['shipping','billing']){
        for(const [key,value] of Object.entries({first_name:'Vendég',last_name:'Saját',postcode:'6726',city:'Szeged',address_1:'Vendég utca',phone:'+36303330003','appleklinika-house_number':'12'}))await fill(page,prefix+'-'+key,value);
      }
      await page.waitForFunction(()=>{const c=wp.data.select('wc/store/cart');return !c.isCustomerDataUpdating()&&!c.isAddressFieldsForShippingRatesUpdating()&&c.getCartData().shippingRates.every(p=>p.destination.postcode==='6726');});
      await snapshot(page,context,item,'guest-entered');
      await page.reload({waitUntil:'networkidle'});
      data=await snapshot(page,context,item,'returning-guest');
      check(data.customer.billingAddress.email===email&&data.customer.shippingAddress.phone==='+36303330003','Own guest session legitimately restores contact');
      const note=page.locator('#order-notes input[type=checkbox]');await note.check();
      await page.locator('#order-notes textarea').fill('QA: kézbesítési megjegyzés.');
      await shot(page,width+'-guest-note-open',item);await note.uncheck();
      await page.goto(base+'/?page_id=8',{waitUntil:'networkidle'});await shot(page,width+'-cart',item);
      await login(page,item.users[0],password);await checkout(page);
      data=await snapshot(page,context,item,'guest-login-A');
      absence(data,[item.users[1].email,item.users[1].first,item.users[1].phone],'Account A isolation');
      check(data.customer.billingAddress.email===item.users[0].email,'Account A uses own email');
      const profile=page.locator('#contact-appleklinika-save_to_profile');await profile.check();
      await shot(page,width+'-account-profile-checked',item);await profile.uncheck();
      for(const purpose of ['shipping','billing']){
        const summary=page.locator('[data-ak-address-purpose="'+purpose+'"] .ak-checkout-address-selector__save summary');
        if(await summary.isVisible()){await summary.click();await page.locator('[data-ak-address-purpose="'+purpose+'"] [data-ak-address-save]').check();}
      }
      await shot(page,width+'-address-save-checked',item);
      for(const purpose of ['shipping','billing'])await page.locator('[data-ak-address-purpose="'+purpose+'"] [data-ak-address-save]').uncheck();
      await page.locator('#order-appleklinika-company_purchase').check();
      await fill(page,'order-appleklinika-company_name','QA Fiók A Kft.');
      await fill(page,'order-appleklinika-tax_number','12345678-2-42');
      await snapshot(page,context,item,'A-company-entered');
      await logout(page,report.baseline.accountUrl);await checkout(page);
      data=await snapshot(page,context,item,'A-logout-guest');
      absence(data,[item.users[0].email,item.users[0].first,item.users[0].phone,'Fiók A utca'],'Logout guest isolation');
      absence(data,['QA Fiók A Kft.','12345678-2-42'],'Logout company isolation');
      check(data.additional['appleklinika/company_purchase']!==true,'Guest does not inherit A company mode');
      await login(page,item.users[1],password);await checkout(page);
      data=await snapshot(page,context,item,'login-B');
      absence(data,[item.users[0].email,item.users[0].first,item.users[0].phone,'Fiók A utca'],'Account B isolation');
      absence(data,['QA Fiók A Kft.','12345678-2-42'],'Account B company isolation');
      check(data.customer.billingAddress.email===item.users[1].email,'Account B uses own email');
      check(data.customer.shippingAddress.phone===item.users[1].phone,'Account B uses own phone');
      await logout(page,report.baseline.accountUrl);await checkout(page);
      data=await snapshot(page,context,item,'B-logout-guest');absence(data,[item.users[1].email,item.users[1].first,item.users[1].phone,'Fiók B utca'],'Second logout isolation');
      item.pass=true;
    } catch(e){item.failure=e.message;await page.screenshot({path:output+'/'+width+'-failure.png',fullPage:true}).catch(()=>{});throw e;}
    finally {
      for(const c of await context.cookies())if(c.name.startsWith('wp_woocommerce_session_')){const key=decodeURIComponent(c.value).split('|')[0];if(!item.sessions.includes(key))item.sessions.push(key);}
      await context.close();
      const emails=[item.guestEmail,...item.users.map(u=>u.email)].filter(Boolean),ids=item.users.map(u=>u.id);
      item.drafts=[];
      for(const key of item.sessions){
        assert(/^[a-zA-Z0-9_-]+$/.test(key));
        const draft=woo(`$s=(new WC_Session_Handler())->get_session('${key}',false);echo json_encode($s['store_api_draft_order']??null);`);
        if(draft&&!item.drafts.includes(Number(draft)))item.drafts.push(Number(draft));
      }
      for(const id of item.drafts){
        assert(!report.baseline.drafts.includes(id),'Never clean a preexisting draft');
        woo(`$o=wc_get_order(${id});if($o){if($o->get_status()!=='checkout-draft'||($o->get_billing_email()!==''&&!in_array($o->get_billing_email(),${JSON.stringify(emails)},true)))throw new Exception('Exact session draft only');$o->delete(true);}echo json_encode(true);`);
      }
      item.cleanup=woo(`$emails=json_decode('${JSON.stringify(emails)}',true);$ids=json_decode('${JSON.stringify(ids)}',true);foreach($emails as $email){foreach(wc_get_orders(['billing_email'=>$email,'limit'=>-1]) as $o){if($o->get_status()!=='checkout-draft'||in_array($o->get_id(),${JSON.stringify(report.baseline.drafts)},true))throw new Exception('Unexpected QA order');$o->delete(true);}}foreach(${JSON.stringify([...new Set([...item.sessions,...ids.map(String)])])} as $key){(new WC_Session_Handler())->delete_session($key);}foreach($ids as $id){$u=get_user_by('id',$id);if(!$u||strpos($u->user_login,'${marker}-')!==0)throw new Exception('Exact QA user only');$service->eraseForCustomer($id);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($id);}echo json_encode(['stock'=>wc_get_product(334)->get_stock_quantity(),'usersRemoved'=>count($ids)]);`);
      check(item.cleanup.stock===report.baseline.stock,'No stock changed');save();
    }
  }} finally{await browser.close();save();}
  check(report.submissions===0,'No order submission');check(report.errors.length===0,'No console errors');save();
  console.log(`Session/control polish: ${report.assertions} assertions passed. Evidence: ${output}`);
})().catch(e=>{save();console.error(e);process.exitCode=1;});
