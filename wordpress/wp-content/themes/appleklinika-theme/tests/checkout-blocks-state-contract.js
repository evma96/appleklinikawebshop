/* eslint-disable no-console */
'use strict';

const { chromium } = require('playwright');

const chromePath = process.env.AK_CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const baseUrl = process.env.AK_CHECKOUT_BASE_URL || 'http://localhost:8080';
const productPath = process.env.AK_CHECKOUT_PRODUCT_PATH
  || '/?product=demo-ipad-air-11-inch-m4-256-gb-kek-blue-wi-fi-a';

const values = {
  email: 'state-contract@example.test',
  billingPhone: '+36305550111',
  shippingPhone: '+36305550222',
  company: 'State Contract QA Kft.',
  taxNumber: '12345678-1-23',
};

const firstValues = {
  email: 'state-contract-first@example.test',
  billingPhone: '+36305550333',
  shippingPhone: '+36305550444',
  company: 'State Contract First QA Kft.',
  taxNumber: '87654321-2-41',
};

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
        url: response.url(),
        status: response.status(),
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

async function fillAddress(page, section, address) {
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
      await input.fill(value);
    }
  }
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

async function runMethod(browser, method) {
  const traffic = { requests: [], responses: [] };
  const { context, page } = await openGuestCheckout(browser, traffic);

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
  });
  await fillAddress(page, 'billing', {
    firstName: 'Számla',
    lastName: 'Minta',
    postcode: '1024',
    city: 'Budapest',
    address1: 'QA Számla utca 2.',
  });
  await write(field(page, 'billingPhone'), firstValues.billingPhone);
  await write(field(page, 'shippingPhone'), firstValues.shippingPhone);
  await field(page, 'companyMode').check();
  await field(page, 'company').waitFor({ state: 'visible' });
  await field(page, 'company').evaluate((input, marker) => input.setAttribute('data-ak-state-contract-original', marker), `${method}-company`);
  await write(field(page, 'company'), firstValues.company);
  await write(field(page, 'taxNumber'), firstValues.taxNumber);

  await write(field(page, 'email'), values.email);
  await write(field(page, 'billingPhone'), values.billingPhone);
  await write(field(page, 'shippingPhone'), values.shippingPhone);
  await write(field(page, 'company'), values.company);
  await write(field(page, 'taxNumber'), values.taxNumber);

  await page.waitForTimeout(2500);

  const rerenders = [];
  for (let index = 1; index <= 3; index += 1) {
    const inputMarker = `${method}-render-${index}`;
    await page.locator('#shipping-postcode').fill(String(1111 + index));
    await page.locator('#shipping-postcode').press('Tab');
    const responsePromise = page.waitForResponse(
      (response) => response.url().includes('/wc/store/') && response.request().method() !== 'GET',
      { timeout: 10000 }
    );
    await page.locator('#shipping-country').selectOption('US');
    await page.locator('#shipping-state').waitFor({ state: 'visible' });
    await page.locator('#shipping-state').evaluate((input, marker) => {
      input.setAttribute('data-ak-state-contract-node', marker);
    }, inputMarker);
    await responsePromise;
    await page.locator('#shipping-country').selectOption('HU');
    await page.locator('#shipping-state').waitFor({ state: 'hidden' });
    await page.locator('#shipping-country').selectOption('US');
    await page.locator('#shipping-state').waitFor({ state: 'visible' });
    const inputRecreated = await page.locator('#shipping-state').getAttribute('data-ak-state-contract-node') !== inputMarker;
    await page.locator('#shipping-country').selectOption('HU');
    await page.locator('#shipping-state').waitFor({ state: 'hidden' });
    await field(page, 'shippingPhone').waitFor({ state: 'visible' });
    await page.waitForTimeout(500);

    rerenders.push({
      index,
      inputRecreated,
      visible: {
        company: await field(page, 'company').inputValue(),
        email: await field(page, 'email').inputValue(),
        billingPhone: await field(page, 'billingPhone').inputValue(),
        shippingPhone: await field(page, 'shippingPhone').inputValue(),
        companyMode: await field(page, 'companyMode').isChecked(),
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
  };
  const store = await customerState(page);
  const emailRecreated = await field(page, 'email').getAttribute('data-ak-state-contract-original') !== `${method}-email`;
  const companyRecreated = await field(page, 'company').getAttribute('data-ak-state-contract-original') !== `${method}-company`;
  const mutatingResponses = traffic.responses.filter((entry) => entry.method !== 'GET');

  await context.close();

  return {
    method,
    visible,
    store,
    requests: traffic.requests.map((entry) => `${entry.method} ${new URL(entry.url).pathname}`),
    responses: traffic.responses.map((entry) => `${entry.status} ${entry.method} ${new URL(entry.url).pathname}`),
    mutatingResponses: mutatingResponses.length,
    emailRecreated,
    companyRecreated,
    requestContacts: extractContactSnapshots(traffic.requests),
    responseContacts: extractContactSnapshots(traffic.responses),
    rerenders,
  };
}

function matchesExpected(result) {
  return result.visible.company === values.company
    && result.visible.email === values.email
    && result.visible.billingPhone === values.billingPhone
    && result.visible.shippingPhone === values.shippingPhone
    && result.visible.companyMode === true
    && result.store.company === values.company
    && result.store.email === values.email
    && result.store.billingPhone === values.billingPhone
    && result.store.shippingPhone === values.shippingPhone
    && result.store.companyMode === true
    && result.mutatingResponses > 0
    && result.rerenders.length === 3
    && result.rerenders.every((render) => render.inputRecreated
      && render.visible.company === values.company
      && render.visible.email === values.email
      && render.visible.billingPhone === values.billingPhone
      && render.visible.shippingPhone === values.shippingPhone
      && render.visible.companyMode === true
      && render.store.company === values.company
      && render.store.email === values.email
      && render.store.billingPhone === values.billingPhone
      && render.store.shippingPhone === values.shippingPhone
      && render.store.companyMode === true)
    && result.requestContacts.some((snapshot) => snapshot.company === values.company
      && snapshot.email === values.email
      && snapshot.billingPhone === values.billingPhone)
    && result.requestContacts.some((snapshot) => snapshot.shippingPhone === values.shippingPhone)
    && result.responseContacts.some((snapshot) => snapshot.company === values.company
      && snapshot.email === values.email
      && snapshot.billingPhone === values.billingPhone
      && snapshot.shippingPhone === values.shippingPhone);
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
    rerenders: result.rerenders,
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
