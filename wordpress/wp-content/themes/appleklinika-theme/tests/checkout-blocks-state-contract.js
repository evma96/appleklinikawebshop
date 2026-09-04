/* eslint-disable no-console */
'use strict';

const { chromium } = require('playwright');

const chromePath = process.env.AK_CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const baseUrl = process.env.AK_CHECKOUT_BASE_URL || 'http://localhost:8080';
const productPath = process.env.AK_CHECKOUT_PRODUCT_PATH
  || '/?product=demo-ipad-air-11-inch-m4-256-gb-kek-blue-wi-fi-a';
const runToken = Date.now().toString(36);

const values = {
  email: `state-contract-${runToken}@example.test`,
  billingPhone: '+36305550111',
  shippingPhone: '+36305550222',
  company: 'State Contract QA Kft.',
  taxNumber: '12345678-1-23',
  shippingFirstName: 'Átvevő',
  shippingLastName: 'Minta',
  shippingPostcode: '1111',
};

const firstValues = {
  email: `state-contract-first-${runToken}@example.test`,
  billingPhone: '+36305550333',
  shippingPhone: '+36305550444',
  company: 'State Contract First QA Kft.',
  taxNumber: '87654321-2-41',
};

const finalShippingPostcode = '1114';

function customerState(page) {
  return page.evaluate(() => {
    const cart = window.wp.data.select('wc/store/cart');
    const checkout = window.wp.data.select('wc/store/checkout');
    const customer = cart.getCustomerData();
    const additional = checkout.getAdditionalFields();

    return {
      company: customer.billingAddress.company || '',
      email: customer.billingAddress.email || '',
      billingPhone: customer.billingAddress.phone || '',
      shippingPhone: customer.shippingAddress.phone || '',
      companyMode: additional['appleklinika/company_purchase'] === true,
      companyField: additional['appleklinika/company_name'] || '',
      taxNumber: additional['appleklinika/tax_number'] || '',
      shippingFirstName: customer.shippingAddress.first_name || '',
      shippingLastName: customer.shippingAddress.last_name || '',
      shippingPostcode: customer.shippingAddress.postcode || '',
    };
  });
}

async function openGuestCheckout(browser, traffic) {
  const context = await browser.newContext();
  const page = await context.newPage();

  page.on('request', (request) => {
    if (request.url().includes('/wc/store/')) {
      traffic.requests.push({
        method: request.method(),
        url: request.url(),
        body: request.postData() || '',
      });
    }
  });
  page.on('response', async (response) => {
    if (response.url().includes('/wc/store/')) {
      traffic.responses.push({
        method: response.request().method(),
        effectiveMethod: response.request().headers()['x-http-method-override'] || response.request().method(),
        url: response.url(),
        status: response.status(),
        requestBody: response.request().postData() || '',
        body: await response.text(),
      });
    }
  });

  await page.goto(baseUrl + productPath, { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: /kosárba teszem/i }).click();
  await page.waitForTimeout(1500);
  await page.goto(baseUrl + '/?page_id=9&_qa=checkout-blocks-state-contract', {
    waitUntil: 'domcontentloaded',
  });
  try {
    await page.locator('#contact-fields input[type="email"]').waitFor({ state: 'visible' });
  } catch (error) {
    throw new Error(`Checkout did not render at ${page.url()}: ${(await page.locator('body').innerText()).slice(0, 500)}`, { cause: error });
  }

  return { context, page };
}

function field(page, name) {
  const selectors = {
    email: '#contact-fields input[type="email"]',
    billingPhone: '#billing-fields input[type="tel"], #billing-fields input[id*="phone"], #billing-fields input[name*="phone"]',
    shippingPhone: '#shipping-fields input[type="tel"], #shipping-fields input[id*="phone"], #shipping-fields input[name*="phone"]',
    companyMode: '#order-appleklinika-company_purchase',
    company: '#order-appleklinika-company_name',
    taxNumber: '#order-appleklinika-tax_number',
  };

  return page.locator(selectors[name]).first();
}

async function ensureAddressForms(page) {
  if (await field(page, 'shippingPhone').count() === 0 || ! await field(page, 'shippingPhone').isVisible()) {
    const editShipping = page.locator('#shipping-fields').getByRole('button', {
      name: /edit shipping address|szállítási cím szerkesztése/i,
    }).first();
    if (await editShipping.count()) {
      await editShipping.click();
      await page.waitForTimeout(250);
    }
  }

  const sameAddress = page.locator('#shipping-fields input[type="checkbox"]').first();
  if (await sameAddress.count() && await sameAddress.isChecked()) {
    await sameAddress.uncheck();
  }

  await field(page, 'shippingPhone').waitFor({ state: 'visible' });
  await field(page, 'billingPhone').waitFor({ state: 'visible' });
}

async function fillAddress(page, section, address, write) {
  const root = page.locator(`#${section}-fields`);
  const pairs = {
    first_name: address.firstName,
    last_name: address.lastName,
    postcode: address.postcode,
    city: address.city,
    address_1: address.address1,
  };

  for (const [suffix, value] of Object.entries(pairs)) {
    const input = root.locator(`input[id$="-${suffix}"], input[name$="${suffix}"]`).first();
    if (await input.count() && await input.isVisible()) {
      await write(input, value);
    }
  }
}

async function shippingVisibleState(page) {
  return {
    shippingFirstName: await page.locator('#shipping-first_name').inputValue(),
    shippingLastName: await page.locator('#shipping-last_name').inputValue(),
    shippingPostcode: await page.locator('#shipping-postcode').inputValue(),
  };
}

async function inputWithKeyboard(locator, value) {
  await locator.click();
  await locator.press(process.platform === 'darwin' ? 'Meta+A' : 'Control+A');
  await locator.press('Backspace');
  await locator.pressSequentially(value, { delay: 25 });
  await locator.press('Tab');
}

async function inputWithFill(locator, value) {
  await locator.fill(value);
  await locator.press('Tab');
}

function extractContactSnapshots(entries) {
  const snapshots = [];

  function visit(value) {
    if (!value || typeof value !== 'object') {
      return;
    }

    if (value.billing_address || value.shipping_address) {
      const billing = value.billing_address || {};
      const shipping = value.shipping_address || {};
      snapshots.push({
        company: billing.company || '',
        email: billing.email || '',
        billingPhone: billing.phone || '',
        shippingPhone: shipping.phone || '',
        shippingFirstName: shipping.first_name || '',
        shippingLastName: shipping.last_name || '',
        shippingPostcode: shipping.postcode || '',
      });
    }

    Object.values(value).forEach(visit);
  }

  entries.forEach((entry) => {
    try {
      visit(JSON.parse(entry.body));
    } catch (error) {
      // Non-JSON Store API traffic is irrelevant to this state contract.
    }
  });

  return snapshots;
}

function extractCompanyModes(entries) {
  const modes = [];

  function visit(value) {
    if (typeof value === 'string') {
      try {
        visit(JSON.parse(value));
      } catch (error) {
        // Plain strings cannot contain a structured checkout extension value.
      }
      return;
    }

    if (!value || typeof value !== 'object') {
      return;
    }

    if (Object.prototype.hasOwnProperty.call(value, 'appleklinika/company_purchase')) {
      modes.push(value['appleklinika/company_purchase'] === true
        || value['appleklinika/company_purchase'] === 1
        || value['appleklinika/company_purchase'] === '1');
    }

    Object.values(value).forEach(visit);
  }

  entries.forEach((entry) => {
    try {
      visit(JSON.parse(entry.body));
    } catch (error) {
      // Non-JSON Store API traffic is irrelevant to this state contract.
    }
  });

  return modes;
}

function requestHasPostcode(response, postcode) {
  const body = response.request().postDataJSON();
  return [body, ...(body?.requests || []).map((request) => request.body || request.data)]
    .some((part) => part?.shipping_address?.postcode === postcode
      && part.shipping_address.country === 'HU');
}

async function pairedExchange(response) {
  const request = response.request().postDataJSON() || {};
  const body = await response.json();
  const pairs = body.responses
    ? body.responses.map((reply, index) => ({
      request: request.requests?.[index]?.body || request.requests?.[index]?.data || {},
      response: reply.body,
      status: reply.status,
    }))
    : [{ request, response: body, status: response.status() }];
  return {
    success: response.ok() && pairs.every((pair) => pair.status >= 200 && pair.status < 300 && !pair.response?.code),
    statuses: pairs.map((pair) => pair.status),
    requestContacts: extractContactSnapshots(pairs.map((pair) => ({ body: JSON.stringify(pair.request) }))),
    responseContacts: extractContactSnapshots(pairs.map((pair) => ({ body: JSON.stringify(pair.response) }))),
    additionalFields: body.additional_fields || {},
  };
}

async function runMethod(browser, method) {
  const traffic = { requests: [], responses: [] };
  const { context, page } = await openGuestCheckout(browser, traffic);
  const cleanup = {};
  try {

  const write = method === 'keyboard' ? inputWithKeyboard : inputWithFill;
  const email = field(page, 'email');
  await email.evaluate((input, marker) => input.setAttribute('data-ak-state-contract-original', marker), `${method}-email`);

  await write(email, firstValues.email);
  await ensureAddressForms(page);
  await fillAddress(page, 'shipping', {
    firstName: 'Átvevő',
    lastName: 'Minta',
    postcode: '1111',
    city: 'Budapest',
    address1: 'QA Szállítás utca 1.',
  }, write);
  await fillAddress(page, 'billing', {
    firstName: 'Számla',
    lastName: 'Minta',
    postcode: '1024',
    city: 'Budapest',
    address1: 'QA Számla utca 2.',
  }, write);
  await write(field(page, 'billingPhone'), firstValues.billingPhone);
  await write(field(page, 'shippingPhone'), firstValues.shippingPhone);
  if (! await field(page, 'companyMode').isChecked()) {
    await field(page, 'companyMode').click();
  }
  await field(page, 'company').waitFor({ state: 'visible' });
  await field(page, 'company').evaluate((input, marker) => input.setAttribute('data-ak-state-contract-original', marker), `${method}-company`);
  await write(field(page, 'company'), firstValues.company);
  await write(field(page, 'taxNumber'), firstValues.taxNumber);

  await page.waitForTimeout(1500);
  const personalModeResponse = page.waitForResponse(
    (response) => response.url().includes('/wc/store/v1/checkout')
      && response.request().postDataJSON()?.additional_fields?.['appleklinika/company_name'] === '',
    { timeout: 10000 }
  );
  await field(page, 'companyMode').click();
  const personalModeRoundTripResponse = await personalModeResponse;
  await page.waitForTimeout(300);
  const personalModeObserved = ! await field(page, 'companyMode').isChecked()
    && (await customerState(page)).companyMode === false;
  const companyModeResponse = page.waitForResponse(
    (response) => response.url().includes('/wc/store/v1/checkout')
      && response.request().postDataJSON()?.additional_fields?.['appleklinika/company_name'] === values.company,
    { timeout: 10000 }
  );
  await field(page, 'companyMode').click();
  await field(page, 'company').waitFor({ state: 'visible' });
  await page.waitForTimeout(300);
  const companyModeRestored = await field(page, 'companyMode').isChecked()
    && (await customerState(page)).companyMode === true;

  await write(field(page, 'email'), values.email);
  await write(field(page, 'billingPhone'), values.billingPhone);
  await write(field(page, 'shippingPhone'), values.shippingPhone);
  await write(field(page, 'company'), values.company);
  await write(field(page, 'taxNumber'), values.taxNumber);
  const companyModeRoundTripResponse = await companyModeResponse;

  await page.waitForTimeout(2500);

  const shippingCountry = page.locator('#shipping-country');
  let previousStateMarker = `${method}-state-baseline`;
  await shippingCountry.selectOption('US');
  await page.locator('#shipping-state').waitFor({ state: 'visible' });
  await page.locator('#shipping-state').evaluate((input, marker) => {
    input.setAttribute('data-ak-state-contract-node', marker);
  }, previousStateMarker);
  await shippingCountry.selectOption('HU');
  await page.locator('#shipping-state').waitFor({ state: 'hidden' });
  await write(page.locator('#shipping-postcode'), values.shippingPostcode);
  await page.waitForTimeout(500);

  const rerenders = [];
  for (let index = 1; index <= 3; index += 1) {
    const inputMarker = `${method}-render-${index}`;
    const latestShippingPostcode = String(1111 + index);
    const responsePromise = page.waitForResponse(
      (response) => response.url().includes('/wc/store/') && requestHasPostcode(response, latestShippingPostcode),
      { timeout: 10000 }
    );
    await write(page.locator('#shipping-postcode'), latestShippingPostcode);
    const editedExchange = await pairedExchange(await responsePromise);
    await page.waitForTimeout(300);
    const afterPostcodeEdit = {
      visible: await shippingVisibleState(page),
      store: await customerState(page),
    };
    await shippingCountry.selectOption('US');
    await page.locator('#shipping-state').waitFor({ state: 'visible' });
    const inputRecreated = await page.locator('#shipping-state').getAttribute('data-ak-state-contract-node') !== previousStateMarker;
    await page.locator('#shipping-state').evaluate((input, marker) => {
      input.setAttribute('data-ak-state-contract-node', marker);
    }, inputMarker);
    previousStateMarker = inputMarker;
    const afterUnitedStates = {
      visible: await shippingVisibleState(page),
      store: await customerState(page),
    };
    await shippingCountry.selectOption('HU');
    await page.locator('#shipping-state').waitFor({ state: 'hidden' });
    const afterFirstHungary = {
      visible: await shippingVisibleState(page),
      store: await customerState(page),
    };
    await page.waitForTimeout(500);
    const restoreResponsePromise = page.waitForResponse(
      (response) => response.url().includes('/wc/store/') && requestHasPostcode(response, latestShippingPostcode),
      { timeout: 10000 }
    );
    await write(page.locator('#shipping-postcode'), latestShippingPostcode);
    const restoredExchange = await pairedExchange(await restoreResponsePromise);
    await page.waitForTimeout(300);
    const afterPostcodeRestore = {
      visible: await shippingVisibleState(page),
      store: await customerState(page),
    };
    await field(page, 'shippingPhone').waitFor({ state: 'visible' });
    await page.waitForTimeout(500);

    rerenders.push({
      index,
      latestShippingPostcode,
      inputRecreated,
      editedExchange,
      restoredExchange,
      afterPostcodeEdit,
      afterUnitedStates,
      afterFirstHungary,
      afterPostcodeRestore,
      visible: {
        company: await field(page, 'company').inputValue(),
        email: await field(page, 'email').inputValue(),
        billingPhone: await field(page, 'billingPhone').inputValue(),
        shippingPhone: await field(page, 'shippingPhone').inputValue(),
        companyMode: await field(page, 'companyMode').isChecked(),
        ...await shippingVisibleState(page),
      },
      store: await customerState(page),
    });
  }

  const visible = {
    company: await field(page, 'company').inputValue(),
    email: await field(page, 'email').inputValue(),
    billingPhone: await field(page, 'billingPhone').inputValue(),
    shippingPhone: await field(page, 'shippingPhone').inputValue(),
    companyMode: await field(page, 'companyMode').isChecked(),
    ...await shippingVisibleState(page),
  };
  const store = await customerState(page);
  const emailRecreated = await field(page, 'email').getAttribute('data-ak-state-contract-original') !== `${method}-email`;
  const companyRecreated = await field(page, 'company').getAttribute('data-ak-state-contract-original') !== `${method}-company`;
  const mutatingResponses = traffic.responses.filter((entry) => entry.method !== 'GET');

  await page.locator('[data-checkout-step-controls="2"] .ak-checkout-step-controls__button').click();
  await page.waitForTimeout(1000);
  const activeStepAfterDetails = await page.locator('body').getAttribute('data-ak-checkout-step');
  var activeStepAfterPayment = activeStepAfterDetails;
  if (activeStepAfterDetails === '3') {
    await page.locator('[data-checkout-step-controls="3"] .ak-checkout-step-controls__button').click();
    await page.waitForTimeout(1000);
    activeStepAfterPayment = await page.locator('body').getAttribute('data-ak-checkout-step');
  }
  const validationText = await page.locator('body').innerText();

  return {
    method,
    visible,
    store,
    requests: traffic.requests.map((entry) => `${entry.method} ${new URL(entry.url).pathname}`),
    responses: traffic.responses.map((entry) => `${entry.status} ${entry.method} ${new URL(entry.url).pathname}`),
    mutatingResponses: mutatingResponses.length,
    emailRecreated,
    companyRecreated,
    personalModeObserved,
    companyModeRestored,
    personalModeRoundTrip: personalModeRoundTripResponse.ok(),
    companyModeRoundTrip: companyModeRoundTripResponse.ok(),
    apiErrors: traffic.responses.flatMap((entry) => {
      const body = JSON.parse(entry.body);
      const request = JSON.parse(entry.requestBody || '{}');
      const replies = body.responses || [{ status: entry.status, body }];
      return replies.flatMap((reply, index) => reply.status >= 400 ? [{
        route: request.requests?.[index]?.path || new URL(entry.url).searchParams.get('rest_route') || new URL(entry.url).pathname,
        method: entry.effectiveMethod,
        status: reply.status,
        response: reply.body,
      }] : []);
    }),
    personalModeResponseStatus: personalModeRoundTripResponse.status(),
    companyModeResponseStatus: companyModeRoundTripResponse.status(),
    personalModeExchange: await pairedExchange(personalModeRoundTripResponse),
    companyModeExchange: await pairedExchange(companyModeRoundTripResponse),
    requestContacts: extractContactSnapshots(traffic.requests),
    responseContacts: extractContactSnapshots(traffic.responses),
    requestCompanyModes: extractCompanyModes(traffic.requests),
    responseCompanyModes: extractCompanyModes(traffic.responses),
    rerenders,
    activeStepAfterDetails,
    activeStepAfterPayment,
    validationText,
    cleanup,
  };
  } finally {
    cleanup.draftIds = [...new Set(traffic.responses.flatMap((entry) => {
      try { const body = JSON.parse(entry.body); return body.order_id ? [body.order_id] : []; }
      catch (error) { return []; }
    }))];
    try {
      const cartResponse = await context.request.get(baseUrl + '/index.php?rest_route=/wc/store/v1/cart');
      const cart = await cartResponse.json();
      if (!cartResponse.ok() || cart.items?.length !== 1) throw new Error('Unexpected isolated cart contents; refusing cleanup.');
      const removed = await context.request.post(baseUrl + '/index.php?rest_route=/wc/store/v1/cart/remove-item', {
        headers: { Nonce: cartResponse.headers().nonce },
        data: { key: cart.items[0].key },
      });
      cleanup.cartEmpty = removed.ok() && (await removed.json()).items?.length === 0;
      if (!cleanup.cartEmpty) throw new Error('Isolated cart cleanup failed.');
    } catch (error) {
      throw new Error(`${error.message} Fixtures: ${JSON.stringify(cleanup)}`, { cause: error });
    } finally {
      await context.close();
    }
  }
}

function expectedChecks(result) {
  return {
    finalVisible: result.visible.company === values.company
    && result.visible.email === values.email
    && result.visible.billingPhone === values.billingPhone
    && result.visible.shippingPhone === values.shippingPhone
    && result.visible.companyMode === true
    && result.visible.shippingFirstName === values.shippingFirstName
    && result.visible.shippingLastName === values.shippingLastName
    && result.visible.shippingPostcode === finalShippingPostcode,
    finalStore: result.store.company === values.company
    && result.store.email === values.email
    && result.store.billingPhone === values.billingPhone
    && result.store.shippingPhone === values.shippingPhone
    && result.store.companyMode === true
    && result.store.shippingFirstName === values.shippingFirstName
    && result.store.shippingLastName === values.shippingLastName
    && result.store.shippingPostcode === finalShippingPostcode,
    modeLifecycle: result.personalModeObserved
      && result.companyModeRestored
      && result.personalModeRoundTrip
      && result.companyModeRoundTrip
      && result.requestCompanyModes.includes(true)
      && result.personalModeExchange.success
      && !result.personalModeExchange.additionalFields['appleklinika/company_purchase']
      && result.companyModeExchange.success
      && result.companyModeExchange.additionalFields['appleklinika/company_purchase'] === true
      && result.companyModeExchange.additionalFields['appleklinika/company_name'] === values.company,
    noApiErrors: result.apiErrors.length === 0,
    cleanup: result.cleanup.cartEmpty === true,
    rerenders: result.rerenders.length === 3
      && result.rerenders.every((render) => render.inputRecreated
      && render.editedExchange.success && render.restoredExchange.success
      && render.restoredExchange.requestContacts.some((entry) => entry.shippingPostcode === render.latestShippingPostcode)
      && render.restoredExchange.responseContacts.some((entry) => entry.shippingPostcode === render.latestShippingPostcode
        && entry.company === values.company && entry.email === values.email
        && entry.billingPhone === values.billingPhone && entry.shippingPhone === values.shippingPhone)
      && render.visible.company === values.company
      && render.visible.email === values.email
      && render.visible.billingPhone === values.billingPhone
      && render.visible.shippingPhone === values.shippingPhone
      && render.visible.companyMode === true
      && render.visible.shippingFirstName === values.shippingFirstName
      && render.visible.shippingLastName === values.shippingLastName
      && render.visible.shippingPostcode === render.latestShippingPostcode
      && render.store.company === values.company
      && render.store.email === values.email
      && render.store.billingPhone === values.billingPhone
      && render.store.shippingPhone === values.shippingPhone
      && render.store.companyMode === true
      && render.store.shippingFirstName === values.shippingFirstName
      && render.store.shippingLastName === values.shippingLastName
      && render.store.shippingPostcode === render.latestShippingPostcode
      && render.afterPostcodeRestore.visible.shippingPostcode === render.latestShippingPostcode
      && render.afterPostcodeRestore.store.shippingPostcode === render.latestShippingPostcode),
    requestBilling: result.requestContacts.some((snapshot) => snapshot.company === values.company
      && snapshot.email === values.email
      && snapshot.billingPhone === values.billingPhone),
    requestShipping: result.requestContacts.some((snapshot) => snapshot.shippingPhone === values.shippingPhone),
    requestPostcode: result.requestContacts.some((snapshot) => snapshot.shippingPostcode === finalShippingPostcode),
    responseState: result.responseContacts.some((snapshot) => snapshot.company === values.company
      && snapshot.email === values.email
      && snapshot.billingPhone === values.billingPhone
      && snapshot.shippingPhone === values.shippingPhone),
    responsePostcode: result.responseContacts.some((snapshot) => snapshot.shippingPostcode === finalShippingPostcode),
    storeApiTraffic: result.mutatingResponses > 0
      && result.requestContacts.length > 0
      && result.responseContacts.length > 0,
    checkoutSteps: result.activeStepAfterDetails === '3'
      && result.activeStepAfterPayment === '4',
  };
}

function matchesExpected(result) {
  return Object.values(expectedChecks(result)).every(Boolean);
}

function summarize(result) {
  return {
    method: result.method,
    visible: result.visible,
    store: result.store,
    mutatingRequests: result.requests.filter((entry) => entry.startsWith('POST ')).length,
    mutatingResponses: result.mutatingResponses,
    requestLatestBillingSeen: result.requestContacts.some((snapshot) => snapshot.company === values.company
      && snapshot.email === values.email
      && snapshot.billingPhone === values.billingPhone),
    requestLatestShippingSeen: result.requestContacts.some((snapshot) => snapshot.shippingPhone === values.shippingPhone),
    responseLatestStateSeen: result.responseContacts.some((snapshot) => snapshot.company === values.company
      && snapshot.email === values.email
      && snapshot.billingPhone === values.billingPhone
      && snapshot.shippingPhone === values.shippingPhone),
    requestLatestPostcodeSeen: result.requestContacts.some((snapshot) => snapshot.shippingPostcode === finalShippingPostcode),
    responseLatestPostcodeSeen: result.responseContacts.some((snapshot) => snapshot.shippingPostcode === finalShippingPostcode),
    personalModeObserved: result.personalModeObserved,
    companyModeRestored: result.companyModeRestored,
    personalModeRoundTrip: result.personalModeRoundTrip,
    companyModeRoundTrip: result.companyModeRoundTrip,
    personalModeResponseStatus: result.personalModeResponseStatus,
    companyModeResponseStatus: result.companyModeResponseStatus,
    requestCompanyModes: result.requestCompanyModes,
    responseCompanyModes: result.responseCompanyModes,
    checks: expectedChecks(result),
    apiErrors: result.apiErrors,
    cleanup: result.cleanup,
    rerenders: result.rerenders,
    activeStepAfterDetails: result.activeStepAfterDetails,
    activeStepAfterPayment: result.activeStepAfterPayment,
    validationMessages: result.validationText.split('\n').filter((line) => /required|kötelező|postal code|irányítószám/i.test(line)),
    pass: matchesExpected(result),
  };
}

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: chromePath });

  try {
    const keyboard = await runMethod(browser, 'keyboard');
    const fill = await runMethod(browser, 'fill');
    const report = {
      expected: values,
      keyboard: summarize(keyboard),
      fill: summarize(fill),
      keyboardPass: matchesExpected(keyboard),
      fillPass: matchesExpected(fill),
    };

    console.log(JSON.stringify(report, null, 2));
    process.exitCode = report.keyboardPass && report.fillPass ? 0 : 1;
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exit(2);
});
