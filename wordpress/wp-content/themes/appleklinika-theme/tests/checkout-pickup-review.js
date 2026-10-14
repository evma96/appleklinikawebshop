'use strict';
// Exercise the actual review renderer with isolated DOM/store fixtures; no browser or network.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(__dirname + '/../assets/js/frontend.js', 'utf8');
function extract(name) {
  const start = source.indexOf('    function ' + name + '(');
  assert(start >= 0, name);
  const end = source.indexOf('\n    function ', start + 1);
  return source.slice(start, end);
}
const escape = value => String(value || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let method = 'local_pickup:27', title = 'Üzletben átvehető', price = '0 Ft';
const context = {
  window: { appleklinikaCheckout: { pickupAddressLines: ['Apple Klinika', '6722 Szeged', 'Jósika utca 4.'] } },
  document: { querySelector: selector => selector.startsWith('#pickup-options') ? {
    value: method,
    closest: () => ({ querySelector: selector => ({ textContent: selector === '.wc-block-components-radio-control__label' ? title : price }) })
  } : null },
  checkoutFieldValue: id => ({email:'fixture@example.invalid','billing-phone':'06000000000'}[id] || ''),
  addressReview: prefix => prefix === 'billing' ? ['Fixture Buyer', '1111 Fixture city', 'Billing street 1'] : [],
  currentPaymentReview: () => 'Készpénz személyes átvételkor',
  finalReviewEscapeHtml: escape,
};
vm.createContext(context);
for (const name of ['currentShippingReview','finalReviewAction','finalReviewValues','finalReviewIcon','finalReviewActions','finalReviewTimelineItem','finalReviewNote','shippingReviewLines','finalReviewHtml']) vm.runInContext(extract(name), context);
let checks = 0;
function check(value, label) { checks++; assert(value, label); }
let html = context.finalReviewHtml();
check(html.includes('6722 Szeged') && html.includes('Jósika utca 4.'), 'Pickup shows the configured store despite absent shipping fields.');
check(html.includes('<h3>Személyes átvétel</h3>'), 'Pickup has its own truthful heading.');
check(!html.includes('Még nincs megadva'), 'Pickup does not claim a missing address.');
check(html.includes('Billing street 1'), 'Billing remains the customer billing address.');
check(!html.includes('Cím módosítása') && html.includes('Átvételi mód módosítása'), 'Pickup offers method editing, not a nonexistent delivery address.');
context.window.appleklinikaCheckout.pickupAddressLines = ['Edited store <script>', '9999 Edited city', 'Edited street'];
html = context.finalReviewHtml();
check(html.includes('Edited street') && html.includes('&lt;script&gt;') && !html.includes('<script>'), 'Editable store content is escaped, never hardcoded or executable.');
method = 'gls_shipping_method:1'; title = 'GLS házhozszállítás'; price = '1 990 Ft';
context.addressReview = prefix => prefix === 'shipping' ? ['Recipient', 'Shipping street 2'] : ['Buyer', 'Billing street 1'];
html = context.finalReviewHtml();
check(html.includes('<h3>Kézbesítés</h3>') && html.includes('Shipping street 2') && !html.includes('Edited street'), 'Courier retains the selected customer delivery address.');
check(html.includes('1 990 Ft') && html.includes('Cím módosítása'), 'Courier fee and address editing remain.');
method = 'vp_pont:4'; title = 'Személyes átvétel';
check(context.currentShippingReview().methodId === 'vp_pont' && context.finalReviewHtml().includes('<h3>Kézbesítés</h3>'), 'Detection uses canonical method ID, not a translated or misleading title.');
// Sidebar lives in a separate closure: execute it without review-only helpers.
const sidebar = {
  window: context.window, document: context.document, escapeHtml: escape, decodeHtmlEntities: x => x,
  blocksStore: () => 'cart', blocksSelector: (s,m,f) => m === 'getCartData' ? {totals:{total_items:'1000',total_price:'1000'},items:[]} : f,
  checkoutFormAddress: () => ({}), addressSummary: a => a.address_1 || 'Még nincs megadva',
  selectedShippingMethod: () => title, selectedPaymentMethod: () => 'Banki átutalás', formatStoreMoney: x => x || '0',
};
vm.createContext(sidebar); vm.runInContext(extract('currentCheckoutSummaryHtml'), sidebar);
method = 'local_pickup:27';
html = sidebar.currentCheckoutSummaryHtml();
check(html.includes('Átvétel helye') && html.includes('Edited street'), 'Independent sidebar reads configured pickup address without relying on another closure.');
method = 'gls_shipping_method:1';
check(!sidebar.currentCheckoutSummaryHtml().includes('Edited street'), 'Sidebar leaves courier presentation unchanged.');
console.log('Pickup review: ' + checks + ' assertions passed.');
