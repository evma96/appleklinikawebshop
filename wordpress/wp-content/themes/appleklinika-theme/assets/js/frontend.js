(function () {
  var lastScrollY = window.scrollY || 0;
  var ticking = false;

  function formatPrice(value) {
    return String(Math.round(Number(value) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Ft';
  }

  var wishlistConfig = window.appleklinikaWishlist || {};

  function setWishlistButtonState(button, isFavorite) {
    button.classList.toggle('is-active', isFavorite);
    button.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
    button.setAttribute('aria-label', isFavorite ? 'Eltávolítás a kedvencekből' : 'Hozzáadás a kedvencekhez');
  }

  function removeWishlistAccountItem(productId) {
    document.querySelectorAll('[data-wishlist-item="' + productId + '"]').forEach(function (item) {
      item.remove();
    });
  }

  function initWishlistButtons() {
    var initialIds = wishlistConfig.productIds || [];
    var activeIds = initialIds.map(function (productId) {
      return Number(productId);
    });

    document.querySelectorAll('.ak-wishlist-button[data-product-id]').forEach(function (button) {
      var productId = Number(button.getAttribute('data-product-id'));

      if (!productId) {
        return;
      }

      setWishlistButtonState(button, activeIds.indexOf(productId) !== -1 || button.classList.contains('is-active'));

      button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        if (!wishlistConfig.isLoggedIn) {
          if (wishlistConfig.loginUrl) {
            window.location.href = wishlistConfig.loginUrl;
          }

          return;
        }

        if (!wishlistConfig.ajaxUrl || !wishlistConfig.nonce) {
          return;
        }

        button.disabled = true;

        var body = new window.URLSearchParams();
        body.append('action', 'appleklinika_toggle_wishlist');
        body.append('nonce', wishlistConfig.nonce);
        body.append('product_id', String(productId));

        window.fetch(wishlistConfig.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: body.toString()
        })
          .then(function (response) {
            return response.json();
          })
          .then(function (payload) {
            if (!payload || !payload.success || !payload.data) {
              return;
            }

            document.querySelectorAll('.ak-wishlist-button[data-product-id="' + productId + '"]').forEach(function (matchingButton) {
              setWishlistButtonState(matchingButton, Boolean(payload.data.isFavorite));
            });

            if (!payload.data.isFavorite) {
              removeWishlistAccountItem(productId);
            }
          })
          .finally(function () {
            button.disabled = false;
          });
      });
    });
  }

  function normalizeText(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  var checkoutCompanyBillingState = { company: '', taxNumber: '' };

  function checkoutFieldByLabelInContainer(container, labelText) {
    var root = container || document;
    var labels = Array.prototype.slice.call(root.querySelectorAll('label'));

    for (var i = 0; i < labels.length; i += 1) {
      var label = labels[i];
      var text = normalizeText(label.textContent);

      if (text.indexOf(labelText) === -1) {
        continue;
      }

      var input = label.getAttribute('for') ? document.getElementById(label.getAttribute('for')) : null;

      if (!input) {
        input = label.closest('div, p, span') ? label.closest('div, p, span').querySelector('input') : null;
      }

      if (!input) {
        continue;
      }

      return {
        input: input,
        wrapper: input.closest('.wc-block-components-text-input, .wc-block-components-checkbox, .wc-block-components-form-field, .components-base-control, div')
      };
    }

    return null;
  }

  function checkoutFieldByLabel(labelText) {
    return checkoutFieldByLabelInContainer(document, labelText);
  }

  function checkoutUsesShippingAsBilling() {
    var sameAddressField = checkoutFieldByLabel('A szállítási és számlázási cím megegyezik.');

    return Boolean(sameAddressField && sameAddressField.input.checked);
  }

  function checkoutCompanyBillingReviewState() {
    var companyToggle = document.getElementById('order-appleklinika-company_purchase');

    if (companyToggle && !companyToggle.checked) {
      checkoutCompanyBillingState = { company: '', taxNumber: '' };

      return { company: '', taxNumber: '' };
    }

    var companyName = document.getElementById('order-appleklinika-company_name');
    var taxNumber = document.getElementById('order-appleklinika-tax_number');

    if (companyToggle && companyToggle.checked && companyName && taxNumber) {
      checkoutCompanyBillingState = {
        company: normalizeText(companyName.value),
        taxNumber: normalizeText(taxNumber.value)
      };
    }

    return checkoutCompanyBillingState;
  }

  function dispatchCheckoutFieldUpdate(input) {
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function setCheckoutFieldValue(input, value) {
    var valueDescriptor = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');

    if (valueDescriptor && typeof valueDescriptor.set === 'function') {
      valueDescriptor.set.call(input, value);
    } else {
      input.value = value;
    }

    dispatchCheckoutFieldUpdate(input);
  }

  function cartUrl() {
    return wishlistConfig.cartUrl || '/';
  }

  function initCompanyCheckoutFields() {
    if (!document.body.classList.contains('woocommerce-checkout')) {
      return;
    }

    var previousEnabled = null;
    var profileSaveDefaultInitialized = false;
    var taxPattern = '\\d{8}-\\d-\\d{2}';
    var addressDetailLabels = ['Házszám', 'Lépcsőház', 'Emelet', 'Ajtó'];

    function taxNumberDigits(value) {
      return String(value || '').replace(/\D/g, '').slice(0, 11);
    }

    function formatTaxNumber(value) {
      var digits = taxNumberDigits(value);
      var firstBlock = digits.slice(0, 8);
      var middleBlock = digits.slice(8, 9);
      var lastBlock = digits.slice(9, 11);

      if (digits.length <= 8) {
        return firstBlock;
      }

      if (digits.length <= 9) {
        return firstBlock + '-' + middleBlock;
      }

      return firstBlock + '-' + middleBlock + '-' + lastBlock;
    }

    function prepareTaxNumberInput(input) {
      input.maxLength = 13;
      input.setAttribute('inputmode', 'numeric');
      input.setAttribute('pattern', taxPattern);
      input.removeAttribute('placeholder');
      input.setAttribute('title', 'Példa: 12345678-1-23');
      input.setAttribute('autocomplete', 'off');

      var preparedValue = formatTaxNumber(input.value);

      if (input.value !== preparedValue) {
        setCheckoutFieldValue(input, preparedValue);
      }

      if (input.dataset.akTaxNumberBound === '1') {
        return;
      }

      input.dataset.akTaxNumberBound = '1';

      input.addEventListener('input', function () {
        var normalized = formatTaxNumber(input.value);

        if (input.value !== normalized) {
          setCheckoutFieldValue(input, normalized);
        }
      });
    }

    function syncRequiredState(field, required) {
      field.input.required = required;
      field.input.setAttribute('aria-required', required ? 'true' : 'false');
    }

    function syncCompanyCheckoutHeading() {
      Array.prototype.slice.call(document.querySelectorAll('body.woocommerce-checkout h2, body.woocommerce-checkout [role="group"] > div')).forEach(function (element) {
        if (normalizeText(element.textContent) === 'Additional order information') {
          element.textContent = 'Céges adatok';
        }
      });
    }

    function syncCheckoutProfileSaveField() {
      var input = document.getElementById('contact-appleklinika-save_to_profile');

      if (!input) {
        return;
      }

      var wrapper = input.closest('.wc-block-components-checkbox');

      if (!wrapper) {
        return;
      }

      wrapper.classList.add('ak-checkout-profile-save');

      if (!profileSaveDefaultInitialized) {
        profileSaveDefaultInitialized = true;

        if (input.checked) {
          input.checked = false;
          dispatchCheckoutFieldUpdate(input);
        }
      }

      var helper = wrapper.querySelector('.ak-checkout-profile-save__helper');

      if (!helper) {
        helper = document.createElement('p');
        helper.className = 'ak-checkout-profile-save__helper';
        helper.textContent = 'Bekapcsolva a céges vásárlás, az adószám, valamint a házszám, emelet, lépcsőház és ajtó adatai is elmentésre kerülnek a következő vásárláshoz.';
        wrapper.appendChild(helper);
      }
    }

    function setCompanyPurchaseState(purchaseField, companyField, taxField, enabled) {
      if (purchaseField.input.checked !== enabled) {
        purchaseField.input.checked = enabled;
        dispatchCheckoutFieldUpdate(purchaseField.input);
      }

      syncRequiredState(companyField, enabled);
      syncRequiredState(taxField, enabled);

      [companyField, taxField].forEach(function (field) {
        if (!field.wrapper) {
          return;
        }

        field.wrapper.classList.add('ak-checkout-company-field');
        field.wrapper.classList.toggle('is-hidden', !enabled);
        field.wrapper.setAttribute('aria-hidden', enabled ? 'false' : 'true');
      });
    }

    function clearCompanyCheckoutValues(companyField, taxField) {
      [companyField.input, taxField.input].forEach(function (input) {
        if (input.value !== '') {
          setCheckoutFieldValue(input, '');
        }
      });
    }

    function clearBillingPersonalIdentity(billingSection) {
      var billingForm = billingSection ? (billingSection.querySelector('.wc-block-components-address-form') || billingSection) : null;

      ['first_name', 'last_name'].forEach(function (suffix) {
        var input = checkoutAddressInputBySuffix(billingForm, suffix);

        if (input && input.value !== '') {
          setCheckoutFieldValue(input, '');
        }
      });
    }

    function insertElementAfter(element, reference) {
      if (!element || !reference || !reference.parentNode) {
        return;
      }

      reference.parentNode.insertBefore(element, reference.nextSibling);
    }

    function checkoutAddressFieldWrapper(input) {
      if (!input) {
        return null;
      }

      return input.closest('.wc-block-components-text-input, .wc-block-components-form-field, .components-base-control');
    }

    function checkoutAddressTwoWrapper(input, form) {
      var wrapper = checkoutAddressFieldWrapper(input);

      if (!wrapper || wrapper === form || wrapper.classList.contains('wc-block-components-address-form')) {
        return null;
      }

      return wrapper;
    }

    function checkoutAddressInputBySuffix(container, suffix) {
      if (!container) {
        return null;
      }

      return container.querySelector('input[id$="-' + suffix + '"], input[name$="' + suffix + '"]');
    }

    function checkoutAddressInputField(container, suffix) {
      var input = checkoutAddressInputBySuffix(container, suffix);

      if (!input) {
        return null;
      }

      return {
        input: input,
        wrapper: checkoutAddressFieldWrapper(input)
      };
    }

    function setFieldHidden(field, hidden) {
      if (!field || !field.wrapper) {
        return;
      }

      field.wrapper.classList.toggle('is-company-hidden', hidden);
      field.wrapper.setAttribute('aria-hidden', hidden ? 'true' : 'false');
      field.input.required = !hidden;
      field.input.setAttribute('aria-required', hidden ? 'false' : 'true');
    }

    function syncBillingCompanyValue(billingSection, companyField, enabled) {
      if (!billingSection) {
        return;
      }

      var billingForm = billingSection.querySelector('.wc-block-components-address-form') || billingSection;
      var standardCompany = checkoutAddressInputBySuffix(billingForm, 'company');
      var value = enabled && companyField && companyField.input ? normalizeText(companyField.input.value) : '';

      if (standardCompany && standardCompany.value !== value) {
        setCheckoutFieldValue(standardCompany, value);
      }

      var companyWrapper = checkoutAddressFieldWrapper(standardCompany);
      if (companyWrapper) {
        companyWrapper.classList.add('ak-checkout-billing-standard-company');
        companyWrapper.setAttribute('aria-hidden', 'true');
      }
    }

    function bindBillingCompanyValueSync(billingSection, companyField) {
      if (!billingSection || !companyField || !companyField.input || billingSection.dataset.akBillingCompanyNameSyncBound === '1') {
        return;
      }

      billingSection.dataset.akBillingCompanyNameSyncBound = '1';

      companyField.input.addEventListener('input', function () {
        if (billingSection.classList.contains('ak-checkout-company-mode')) {
          syncBillingCompanyValue(billingSection, companyField, true);
        }
      });
    }

    function syncBillingFormLayout(billingSection, companyField) {
      if (!billingSection) {
        return;
      }

      var billingForm = billingSection.querySelector('.wc-block-components-address-form') || billingSection;
      var fields = {
        firstName: checkoutAddressInputField(billingForm, 'first_name'),
        lastName: checkoutAddressInputField(billingForm, 'last_name'),
        postcode: checkoutAddressInputField(billingForm, 'postcode'),
        city: checkoutAddressInputField(billingForm, 'city'),
        address: checkoutAddressInputField(billingForm, 'address_1'),
        phone: checkoutAddressInputField(billingForm, 'phone')
      };
      var companyMode = billingSection.classList.contains('ak-checkout-company-mode');

      billingForm.classList.add('ak-checkout-billing-grid');

      [
        ['firstName', 'ak-checkout-billing-person-name'],
        ['lastName', 'ak-checkout-billing-person-name'],
        ['postcode', 'ak-checkout-billing-half-field'],
        ['city', 'ak-checkout-billing-half-field'],
        ['address', 'ak-checkout-billing-address-main-field'],
        ['phone', 'ak-checkout-billing-phone-field']
      ].forEach(function (entry) {
        var field = fields[entry[0]];

        if (field && field.wrapper) {
          field.wrapper.classList.add(entry[1]);
        }
      });

      setFieldHidden(fields.firstName, companyMode);
      setFieldHidden(fields.lastName, companyMode);

      if (!companyMode) {
        [fields.firstName, fields.lastName].forEach(function (field) {
          if (field && field.wrapper) {
            field.wrapper.classList.remove('is-company-hidden');
            field.wrapper.setAttribute('aria-hidden', 'false');
            field.input.required = true;
            field.input.setAttribute('aria-required', 'true');
          }
        });
      }
    }

    function syncCheckoutAddressDetailsForSection(section) {
      if (!section) {
        return false;
      }

      var form = section.querySelector('.wc-block-components-address-form') || section;
      form.classList.remove('ak-checkout-address-2-hidden');
      form.removeAttribute('aria-hidden');

      var addressInput = form.querySelector('input[id$="-address_1"], input[name$="address_1"]');
      var addressWrapper = checkoutAddressFieldWrapper(addressInput);
      var detailFields = addressDetailLabels.map(function (label) {
        return checkoutFieldByLabelInContainer(form, label);
      }).filter(Boolean);

      if (!addressWrapper || detailFields.length === 0) {
        return false;
      }

      var slot = form.querySelector('.ak-checkout-address-details-slot');

      if (!slot) {
        slot = document.createElement('div');
        slot.className = 'ak-checkout-address-details-slot';
        insertElementAfter(slot, addressWrapper);
      } else if (slot.previousElementSibling !== addressWrapper) {
        insertElementAfter(slot, addressWrapper);
      }

      detailFields.forEach(function (field) {
        if (!field.wrapper) {
          return;
        }

        field.wrapper.classList.add('ak-checkout-address-detail-field');

        if (field.wrapper.parentNode !== slot) {
          slot.appendChild(field.wrapper);
        }
      });

      var addressTwoInput = form.querySelector('input[id$="-address_2"], input[name$="address_2"]');
      var addressTwoWrapper = checkoutAddressTwoWrapper(addressTwoInput, form);

      if (addressTwoWrapper) {
        addressTwoWrapper.classList.add('ak-checkout-address-2-hidden');
        addressTwoWrapper.setAttribute('aria-hidden', 'true');
      }

      return true;
    }

    function syncCheckoutAddressGridForSection(section) {
      if (!section) {
        return;
      }

      var form = section.querySelector('.wc-block-components-address-form') || section;
      var fields = {
        firstName: checkoutAddressInputField(form, 'first_name'),
        lastName: checkoutAddressInputField(form, 'last_name'),
        postcode: checkoutAddressInputField(form, 'postcode'),
        city: checkoutAddressInputField(form, 'city'),
        address: checkoutAddressInputField(form, 'address_1'),
        phone: checkoutAddressInputField(form, 'phone')
      };

      form.classList.add('ak-checkout-address-grid');

      [
        ['firstName', 'ak-checkout-address-person-name'],
        ['lastName', 'ak-checkout-address-person-name'],
        ['postcode', 'ak-checkout-address-half-field'],
        ['city', 'ak-checkout-address-half-field'],
        ['address', 'ak-checkout-address-main-field'],
        ['phone', 'ak-checkout-address-phone-field']
      ].forEach(function (entry) {
        var field = fields[entry[0]];

        if (field && field.wrapper) {
          field.wrapper.classList.add(entry[1]);
        }
      });
    }

    function syncCheckoutAddressDetails() {
      var shippingSection = document.querySelector('#shipping-fields');
      var billingSection = document.querySelector('#billing-fields');

      syncCheckoutAddressDetailsForSection(shippingSection);
      syncCheckoutAddressDetailsForSection(billingSection);
      syncCheckoutAddressGridForSection(shippingSection);
      syncCheckoutAddressGridForSection(billingSection);
    }

    function moveCompanyFieldsIntoBillingSection(purchaseField, companyField, taxField) {
      var billingSection = document.querySelector('#billing-fields');
      var sourceStep = purchaseField.wrapper ? purchaseField.wrapper.closest('.wc-block-components-checkout-step') : document.querySelector('#order-fields');

      if (sourceStep && sourceStep.id === 'order-fields') {
        sourceStep.classList.add('ak-checkout-company-source-hidden');
      }

      if (!billingSection) {
        if (purchaseField.wrapper) {
          purchaseField.wrapper.classList.add('is-hidden');
          purchaseField.wrapper.setAttribute('aria-hidden', 'true');
        }

        return null;
      }

      var billingForm = billingSection.querySelector('.wc-block-components-address-form') || billingSection;
      var firstNameField = checkoutAddressInputField(billingForm, 'first_name');
      var lastNameField = checkoutAddressInputField(billingForm, 'last_name');
      var slot = billingSection.querySelector('.ak-checkout-company-billing-slot');

      if (!slot) {
        slot = document.createElement('div');
        slot.className = 'ak-checkout-company-billing-slot';

        var nameAnchor = billingForm.querySelector('.wc-block-components-address-form__last_name, .wc-block-components-address-form__first_name');

        if (nameAnchor && nameAnchor.parentNode === billingForm) {
          billingForm.insertBefore(slot, nameAnchor);
        } else {
          billingForm.appendChild(slot);
        }
      }

      var identitySlot = slot.querySelector('.ak-checkout-billing-identity-slot');

      if (!identitySlot) {
        identitySlot = document.createElement('div');
        identitySlot.className = 'ak-checkout-billing-identity-slot';
        slot.appendChild(identitySlot);
      }

      var personalRow = identitySlot.querySelector('.ak-checkout-billing-personal-row');

      if (!personalRow) {
        personalRow = document.createElement('div');
        personalRow.className = 'ak-checkout-billing-personal-row';
        identitySlot.appendChild(personalRow);
      }

      var companyRow = identitySlot.querySelector('.ak-checkout-billing-company-row');

      if (!companyRow) {
        companyRow = document.createElement('div');
        companyRow.className = 'ak-checkout-billing-company-row';
        identitySlot.appendChild(companyRow);
      }

      if (purchaseField.wrapper && purchaseField.wrapper.parentNode !== slot) {
        slot.insertBefore(purchaseField.wrapper, identitySlot);
      } else if (purchaseField.wrapper && purchaseField.wrapper.nextElementSibling !== identitySlot) {
        slot.insertBefore(purchaseField.wrapper, identitySlot);
      }

      [firstNameField, lastNameField].forEach(function (field) {
        if (field && field.wrapper && field.wrapper.parentNode !== personalRow) {
          personalRow.appendChild(field.wrapper);
        }
      });

      [companyField.wrapper, taxField.wrapper].forEach(function (wrapper) {
        if (wrapper && wrapper.parentNode !== companyRow) {
          companyRow.appendChild(wrapper);
        }
      });

      if (purchaseField.wrapper) {
        purchaseField.wrapper.classList.remove('is-hidden');
        purchaseField.wrapper.setAttribute('aria-hidden', 'false');
      }

      var enabled = Boolean(purchaseField.input.checked);

      slot.classList.toggle('is-company-enabled', enabled);
      identitySlot.classList.toggle('is-company-enabled', enabled);
      personalRow.classList.toggle('is-hidden', enabled);
      personalRow.setAttribute('aria-hidden', enabled ? 'true' : 'false');
      companyRow.classList.toggle('is-hidden', !enabled);
      companyRow.setAttribute('aria-hidden', enabled ? 'false' : 'true');
      billingSection.classList.toggle('ak-checkout-company-mode', enabled);
      bindBillingCompanyValueSync(billingSection, companyField);
      syncBillingCompanyValue(billingSection, companyField, enabled);
      syncBillingFormLayout(billingSection, companyField);

      return slot;
    }

    function syncCompanyCheckoutFields() {
      syncCompanyCheckoutHeading();
      syncCheckoutProfileSaveField();
      syncCheckoutAddressDetails();

      var purchaseField = checkoutFieldByLabel('Cégként vásárolok');
      var companyField = checkoutFieldByLabel('Cégnév');
      var taxField = checkoutFieldByLabel('Adószám');

      if (!purchaseField || !companyField || !taxField) {
        return false;
      }

      var enabled = Boolean(purchaseField.input.checked);
      var hiddenChanged = previousEnabled !== enabled;

      if (purchaseField.wrapper) {
        purchaseField.wrapper.classList.add('ak-checkout-company-toggle');
      }

      prepareTaxNumberInput(taxField.input);

      var companySlot = moveCompanyFieldsIntoBillingSection(purchaseField, companyField, taxField);

      if (!companySlot) {
        setCompanyPurchaseState(purchaseField, companyField, taxField, false);
        clearCompanyCheckoutValues(companyField, taxField);
        previousEnabled = false;
        return true;
      }

      enabled = Boolean(purchaseField.input.checked);
      setCompanyPurchaseState(purchaseField, companyField, taxField, enabled);

      if (enabled && hiddenChanged) {
        clearBillingPersonalIdentity(document.querySelector('#billing-fields'));
      }

      if (!enabled && hiddenChanged) {
        clearCompanyCheckoutValues(companyField, taxField);
      }

      document.dispatchEvent(new CustomEvent('appleklinika:checkout-company-mode-changed'));
      previousEnabled = enabled;

      return true;
    }

    document.addEventListener('change', function (event) {
      var purchaseField = checkoutFieldByLabel('Cégként vásárolok');

      if (purchaseField && event.target === purchaseField.input) {
        syncCompanyCheckoutFields();
      }
    });

    syncCheckoutAddressDetails();
    syncCompanyCheckoutFields();

    var observer = new MutationObserver(function () {
      syncCompanyCheckoutFields();
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  function initCheckoutSummary() {
    if (!document.body.classList.contains('woocommerce-checkout')) {
      return;
    }

    var syncFrame = null;
    var lastSummaryHtml = '';

    function escapeHtml(value) {
      var element = document.createElement('div');
      element.textContent = String(value || '');
      return element.innerHTML;
    }

    function decodeHtmlEntities(value) {
      var element = document.createElement('textarea');
      element.innerHTML = String(value || '');
      return element.value;
    }

    var checkoutStores = {
      cartStore: 'wc/store/cart',
      paymentStore: 'wc/store/payment',
      validationStore: 'wc/store/validation'
    };

    function blocksStore(name) {
      return checkoutStores[name] || null;
    }

    function blocksSelector(store, method, fallback) {
      if (!store || !window.wp || !window.wp.data || typeof window.wp.data.select !== 'function') {
        return fallback;
      }

      try {
        var selectors = window.wp.data.select(store);
        return selectors && typeof selectors[method] === 'function' ? selectors[method]() : fallback;
      } catch (error) {
        return fallback;
      }
    }

    function formatStoreMoney(amount, totals) {
      if (amount === undefined || amount === null || amount === '') {
        return '—';
      }

      var minorUnit = Number(totals.currency_minor_unit || 0);
      var currency = String(totals.currency_code || 'HUF');
      var numericAmount = Number(amount) / Math.pow(10, minorUnit);

      if (!Number.isFinite(numericAmount)) {
        return '—';
      }

      try {
        return new Intl.NumberFormat('hu-HU', {
          style: 'currency',
          currency: currency,
          minimumFractionDigits: 0,
          maximumFractionDigits: minorUnit
        }).format(numericAmount);
      } catch (error) {
        return String(totals.currency_prefix || '') + numericAmount.toFixed(minorUnit) + String(totals.currency_suffix || '');
      }
    }

    function addressSummary(address, companyBilling) {
      if (!address || !address.address_1) {
        return 'Még nincs megadva';
      }

      var companyName = companyBilling ? companyBilling.company : address.company;
      var recipient = companyName || [address.first_name, address.last_name].filter(Boolean).join(' ');
      var locality = [address.postcode, address.city].filter(Boolean).join(' ');
      var taxNumber = companyName && companyBilling.taxNumber ? 'Adószám: ' + companyBilling.taxNumber : '';

      return [recipient, taxNumber, address.address_1, address.address_2, locality].filter(Boolean).join(', ');
    }

    function checkoutFormAddress(prefix) {
      var field = function (name) {
        var input = document.getElementById(prefix + '-' + name);
        return input ? input.value.trim() : '';
      };
      var company = field('company');
      var addressDetails = [
        field('appleklinika-staircase') ? 'Lépcsőház: ' + field('appleklinika-staircase') : '',
        field('appleklinika-floor') ? 'Emelet: ' + field('appleklinika-floor') : '',
        field('appleklinika-door') ? 'Ajtó: ' + field('appleklinika-door') : ''
      ].filter(Boolean);

      if (prefix === 'billing') {
        var companyBilling = checkoutCompanyBillingReviewState();

        if (companyBilling.company) {
          company = companyBilling.company;
        }
      }

      return {
        first_name: field('first_name'),
        last_name: field('last_name'),
        company: company,
        address_1: [field('address_1'), field('appleklinika-house_number')].filter(Boolean).join(' '),
        address_2: [field('address_2')].concat(addressDetails).filter(Boolean).join(', '),
        postcode: field('postcode'),
        city: field('city')
      };
    }

    function currentCheckoutAddress(prefix, storeAddress) {
      var formAddress = checkoutFormAddress(prefix);
      var address = Object.assign({}, storeAddress || {});

      Object.keys(formAddress).forEach(function (key) {
        if (formAddress[key]) {
          address[key] = formAddress[key];
        }
      });

      return address;
    }

    function effectiveBillingAddress(billing, shipping) {
      if (!checkoutUsesShippingAsBilling() || !shipping || !shipping.address_1) {
        return billing;
      }

      var companyBilling = checkoutCompanyBillingReviewState();

      if (!companyBilling.company) {
        return shipping;
      }

      var companyAddress = checkoutFormAddress('billing');

      return Object.assign({}, shipping, {
        company: companyBilling.company,
        first_name: companyAddress.first_name,
        last_name: companyAddress.last_name
      });
    }

    function selectedShippingMethod(cart) {
      var packages = Array.isArray(cart.shipping_rates) ? cart.shipping_rates : [];
      var selected = [];

      packages.forEach(function (shippingPackage) {
        (shippingPackage.shipping_rates || []).forEach(function (rate) {
          if (rate && rate.selected && rate.name) {
            selected.push(rate.name);
          }
        });
      });

      if (selected.length) {
        return selected.join(', ');
      }

      var selectedInput = document.querySelector('#shipping-option input:checked');
      var selectedOption = selectedInput
        ? selectedInput.closest('.wc-block-components-radio-control__option, label')
        : null;
      var primaryLabel = selectedOption
        ? selectedOption.querySelector('.wc-block-components-radio-control__label')
        : null;
      var shippingLabel = primaryLabel
        ? primaryLabel.textContent.trim()
        : (selectedOption ? selectedOption.textContent.trim() : '');

      return shippingLabel || 'Még nincs kiválasztva';
    }

    function selectedPaymentMethod() {
      var paymentStore = blocksStore('paymentStore');
      var activePayment = blocksSelector(paymentStore, 'getActivePaymentMethod', '');
      var selectedInput = document.querySelector('.wc-block-components-radio-control__input[name*="payment"]:checked, input[id*="payment-method-options"]:checked');
      var paymentLabelElement = selectedInput && selectedInput.id
        ? document.querySelector('label[for="' + selectedInput.id + '"] .wc-block-components-payment-method-label')
        : null;
      var paymentLabel = paymentLabelElement ? paymentLabelElement.textContent.trim() : '';

      if (paymentLabel) {
        return paymentLabel;
      }

      if (activePayment === 'bacs') {
        return 'Banki átutalás';
      }

      return activePayment ? String(activePayment) : 'Válassz fizetési módot';
    }

    function currentCheckoutSummaryHtml() {
      var cartStore = blocksStore('cartStore');
      var cart = blocksSelector(cartStore, 'getCartData', null);

      if (!cart) {
        return '';
      }

      var totals = cart.totals || {};
      var items = Array.isArray(cart.items) ? cart.items : [];
      var itemHtml = items.map(function (item) {
        var image = item.images && item.images[0] ? (item.images[0].thumbnail || item.images[0].src || '') : '';
        var quantity = Number(item.quantity || 0);
        var lineTotal = item.totals && item.totals.line_total !== undefined ? item.totals.line_total : '';

        return '<article class="ak-checkout-summary__item">'
          + '<div class="ak-checkout-summary__thumb">'
          + (image ? '<img class="ak-checkout-summary__image" src="' + escapeHtml(image) + '" alt="">' : '') + '</div>'
          + '<div class="ak-checkout-summary__item-body"><h3 class="ak-checkout-summary__item-title">' + escapeHtml(decodeHtmlEntities(item.name)) + '</h3></div>'
          + '<span class="ak-checkout-summary__qty" aria-label="Mennyiség">' + escapeHtml(quantity) + '</span>'
          + '<div class="ak-checkout-summary__item-aside"><div class="ak-checkout-summary__item-price">' + escapeHtml(formatStoreMoney(lineTotal, totals)) + '</div></div>'
          + '</article>';
      }).join('');
      var discount = Number(totals.total_discount || 0);
      var tax = Number(totals.total_tax || 0);
      var billing = currentCheckoutAddress(
        'billing',
        blocksSelector(cartStore, 'getBillingAddress', cart.billing_address || {})
      );
      var shipping = currentCheckoutAddress(
        'shipping',
        blocksSelector(cartStore, 'getShippingAddress', cart.shipping_address || {})
      );
      var companyBilling = checkoutCompanyBillingReviewState();

      billing = effectiveBillingAddress(billing, shipping);
      var detailRows = [
        ['Számlázási cím', addressSummary(billing, companyBilling)],
        ['Szállítási cím', addressSummary(shipping)],
        ['Szállítási mód', selectedShippingMethod(cart)],
        ['Fizetési mód', selectedPaymentMethod()]
      ].map(function (row) {
        return '<div class="ak-checkout-summary__detail"><span>' + escapeHtml(row[0]) + '</span><strong>' + escapeHtml(row[1]) + '</strong></div>';
      }).join('');

      return '<aside class="ak-checkout-summary" aria-label="Rendelés összesítő">'
        + '<h2 class="ak-checkout-summary__title">Rendelés összesítő</h2>'
        + (itemHtml ? '<div class="ak-checkout-summary__items">' + itemHtml + '</div>' : '<p class="ak-checkout-summary__empty">A kosarad jelenleg üres.</p>')
        + '<div class="ak-checkout-summary__totals">'
        + '<div class="ak-checkout-summary__row"><span>Részösszeg</span><strong>' + escapeHtml(formatStoreMoney(totals.total_items, totals)) + '</strong></div>'
        + (discount > 0 ? '<div class="ak-checkout-summary__row"><span>Kedvezmény</span><strong>−' + escapeHtml(formatStoreMoney(discount, totals)) + '</strong></div>' : '')
        + '<div class="ak-checkout-summary__row"><span>Szállítás</span><strong>' + escapeHtml(formatStoreMoney(totals.total_shipping, totals)) + '</strong></div>'
        + '<div class="ak-checkout-summary__row"><span>Adó</span><strong>' + escapeHtml(formatStoreMoney(tax, totals)) + '</strong></div>'
        + '<div class="ak-checkout-summary__row ak-checkout-summary__row--total"><span>Végösszeg</span><strong>' + escapeHtml(formatStoreMoney(totals.total_price, totals)) + '</strong></div>'
        + '</div><div class="ak-checkout-summary__details" aria-live="polite">' + detailRows + '</div></aside>';
    }

    function syncCheckoutSummary() {
      var defaultSummary = document.querySelector('body.woocommerce-checkout .wc-block-components-sidebar');

      if (!defaultSummary || !defaultSummary.parentNode) {
        return false;
      }

      var slot = document.querySelector('body.woocommerce-checkout .ak-checkout-summary-slot');

      if (!slot) {
        slot = document.createElement('div');
        slot.className = 'ak-checkout-summary-slot ak-checkout-step-persistent-summary';
        defaultSummary.parentNode.insertBefore(slot, defaultSummary.nextSibling);
      }

      var summaryHtml = currentCheckoutSummaryHtml();

      if (!summaryHtml) {
        return false;
      }

      if (summaryHtml !== lastSummaryHtml) {
        slot.innerHTML = summaryHtml;
        lastSummaryHtml = summaryHtml;
      }

      defaultSummary.classList.add('ak-checkout-default-summary-hidden');
      defaultSummary.setAttribute('aria-hidden', 'true');
      slot.classList.remove('ak-checkout-step-hidden');
      slot.setAttribute('aria-hidden', 'false');

      return true;
    }

    function scheduleCheckoutSummarySync() {
      if (syncFrame !== null) {
        return;
      }

      syncFrame = window.requestAnimationFrame(function () {
        syncFrame = null;
        syncCheckoutSummary();
      });
    }

    syncCheckoutSummary();

    window.setTimeout(scheduleCheckoutSummarySync, 250);
    window.setTimeout(scheduleCheckoutSummarySync, 1000);

    document.addEventListener('change', scheduleCheckoutSummarySync);
    document.addEventListener('input', scheduleCheckoutSummarySync);

    if (window.wp && window.wp.data && typeof window.wp.data.subscribe === 'function') {
      window.wp.data.subscribe(scheduleCheckoutSummarySync);
    }

    var observer = new MutationObserver(scheduleCheckoutSummarySync);
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  function initCheckoutStepper() {
    if (!document.body.classList.contains('woocommerce-checkout')) {
      return;
    }

    var activeStep = 2;
    var syncFrame = null;
    var lastFinalReviewHtml = '';
    var summaryHome = null;
    var stepLabels = {
      1: 'Kosár',
      2: 'Adatok',
      3: 'Szállítás és fizetés',
      4: 'Összegzés'
    };

    function closestCheckoutStep(selector) {
      var element = document.querySelector(selector);

      if (!element) {
        return null;
      }

      return element.closest('.wc-block-components-checkout-step') || element;
    }

    function uniqueElements(elements) {
      return elements.filter(function (element, index) {
        return element && elements.indexOf(element) === index;
      });
    }

    function finalReviewEscapeHtml(value) {
      var element = document.createElement('div');
      element.textContent = String(value || '');
      return element.innerHTML;
    }

    function checkoutFieldValue(id) {
      var field = document.getElementById(id);
      return field && typeof field.value === 'string' ? field.value.trim() : '';
    }

    function addressReview(prefix, physicalPrefix) {
      var addressPrefix = physicalPrefix || prefix;
      var companyBilling = prefix === 'billing' ? checkoutCompanyBillingReviewState() : { company: '', taxNumber: '' };
      var companyName = companyBilling.company;
      var recipient = companyName || [checkoutFieldValue(prefix + '-last_name'), checkoutFieldValue(prefix + '-first_name')].filter(Boolean).join(' ');
      var locality = [checkoutFieldValue(addressPrefix + '-postcode'), checkoutFieldValue(addressPrefix + '-city')].filter(Boolean).join(' ');
      var street = [checkoutFieldValue(addressPrefix + '-address_1'), checkoutFieldValue(addressPrefix + '-appleklinika-house_number')].filter(Boolean).join(' ');
      var location = [locality, street].filter(Boolean).join(', ');
      var lines = [recipient, location, checkoutFieldValue(addressPrefix + '-address_2')].filter(Boolean);

      if (companyName) {
        var taxNumber = companyBilling.taxNumber;
        if (taxNumber) {
          lines.splice(1, 0, 'Adószám: ' + taxNumber);
        }
      }

      return lines;
    }

    function effectiveBillingReview() {
      if (!checkoutUsesShippingAsBilling()) {
        return addressReview('billing');
      }

      var companyBilling = checkoutCompanyBillingReviewState();

      return companyBilling.company
        ? addressReview('billing', 'shipping')
        : addressReview('shipping');
    }

    function currentShippingReview() {
      var selected = document.querySelector('#shipping-option input:checked');
      var option = selected ? selected.closest('.wc-block-components-radio-control__option, label') : null;
      var title = option && option.querySelector('.wc-block-components-radio-control__label');
      var price = option && option.querySelector('.wc-block-components-radio-control__secondary-label, .wc-block-components-radio-control__description');

      return {
        title: title ? title.textContent.trim() : (option ? option.textContent.trim() : 'Még nincs kiválasztva'),
        price: price ? price.textContent.trim() : ''
      };
    }

    function currentPaymentReview() {
      var selected = document.querySelector('.wc-block-components-radio-control__input[name*="payment"]:checked, input[id*="payment-method-options"]:checked');
      var label = selected && selected.id
        ? document.querySelector('label[for="' + selected.id + '"] .wc-block-components-payment-method-label')
        : null;

      return label && label.textContent.trim()
        ? label.textContent.trim()
        : (selected ? selected.value : 'Még nincs kiválasztva');
    }

    function finalReviewAction(label, step) {
      return '<button class="ak-checkout-final-review__edit" type="button" data-ak-checkout-review-step="' + String(step) + '" aria-label="' + finalReviewEscapeHtml(label) + '">' + finalReviewEscapeHtml(label) + '</button>';
    }

    function finalReviewValues(lines) {
      var content = lines.filter(Boolean).map(function (line) {
        return '<p>' + finalReviewEscapeHtml(line) + '</p>';
      }).join('');

      return '<div class="ak-checkout-final-review__values">' + (content || '<p>Még nincs megadva</p>') + '</div>';
    }

    function finalReviewIcon(icon) {
      var icons = {
        contact: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8c.8-3.2 3.2-5 7-5s6.2 1.8 7 5" /></svg>',
        delivery: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h11v10H3zM14 9h3.5L21 12.5V16h-7zM7 19a1.7 1.7 0 1 0 0-3.4A1.7 1.7 0 0 0 7 19Zm10 0a1.7 1.7 0 1 0 0-3.4A1.7 1.7 0 0 0 17 19Z" /></svg>',
        billing: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 21V4h10v17M3 21h18M8 8h4M8 12h4M8 16h4M17 8h2M17 12h2M17 16h2" /></svg>',
        payment: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 10h18M7 15h4" /></svg>'
      };

      return '<span class="ak-checkout-final-review__marker">' + (icons[icon] || '') + '</span>';
    }

    function finalReviewActions(actions) {
      return '<div class="ak-checkout-final-review__actions">' + actions.map(function (action) {
        return finalReviewAction(action.label, action.step);
      }).join('') + '</div>';
    }

    function finalReviewTimelineItem(icon, title, content, actions) {
      return '<section class="ak-checkout-final-review__timeline-item">'
        + finalReviewIcon(icon)
        + '<div class="ak-checkout-final-review__timeline-content"><h3>' + finalReviewEscapeHtml(title) + '</h3>'
        + content
        + finalReviewActions(actions) + '</div></section>';
    }

    function finalReviewNote(note) {
      return '<section class="ak-checkout-final-review__note"><h3>Megjegyzés</h3>'
        + finalReviewValues([note])
        + finalReviewActions([{ label: 'Megjegyzés módosítása', step: 2 }]) + '</section>';
    }

    function shippingReviewLines(shippingMethod) {
      var price = String(shippingMethod.price || '').trim();
      var isFree = /(^|\s)0\s*(?:ft|huf)\b|ingyen/i.test(price);

      return [shippingMethod.title, price && !isFree && price !== shippingMethod.title ? price : ''].filter(Boolean);
    }

    function finalReviewHtml() {
      var contact = [checkoutFieldValue('email'), checkoutFieldValue('billing-phone') || checkoutFieldValue('shipping-phone')].filter(Boolean);
      var billing = effectiveBillingReview();
      var shipping = addressReview('shipping');
      var shippingMethod = currentShippingReview();
      var fulfilment = shippingReviewLines(shippingMethod);
      var payment = [currentPaymentReview()];
      var note = document.querySelector('#order-notes textarea');
      var noteHtml = note && note.value.trim()
        ? finalReviewNote(note.value.trim())
        : '';

      return '<section class="ak-checkout-final-review" aria-labelledby="ak-checkout-final-review-title">'
        + '<div class="ak-checkout-final-review__intro"><h2 id="ak-checkout-final-review-title">Rendelés áttekintése</h2><p>Ellenőrizd az adatokat a véglegesítés előtt.</p></div>'
        + '<div class="ak-checkout-final-review__timeline">'
        + finalReviewTimelineItem('contact', 'Kapcsolattartás', finalReviewValues(contact), [{ label: 'Módosítás', step: 2 }])
        + finalReviewTimelineItem('delivery', 'Kézbesítés',
          finalReviewValues(shipping)
          + '<div class="ak-checkout-final-review__delivery-method"><p>Szállítási mód</p>' + finalReviewValues(fulfilment) + '</div>',
          [{ label: 'Cím módosítása', step: 2 }, { label: 'Szállítás módosítása', step: 3 }])
        + finalReviewTimelineItem('billing', 'Számlázási adatok', finalReviewValues(billing), [{ label: 'Módosítás', step: 2 }])
        + finalReviewTimelineItem('payment', 'Fizetési mód', finalReviewValues(payment), [{ label: 'Módosítás', step: 3 }])
        + noteHtml
        + '</div></section>';
    }

    function syncCheckoutFinalReview() {
      var terms = document.querySelector('.wc-block-checkout__terms');
      var reviewSlot = null;

      if (!terms || !terms.parentNode) {
        return null;
      }

      Array.prototype.some.call(terms.parentNode.children, function (child) {
        if (child.classList && child.classList.contains('ak-checkout-final-review-slot')) {
          reviewSlot = child;
          return true;
        }
        return false;
      });

      if (!reviewSlot) {
        reviewSlot = document.createElement('div');
        reviewSlot.className = 'ak-checkout-final-review-slot';
        terms.parentNode.insertBefore(reviewSlot, terms);
      }

      var html = finalReviewHtml();
      if (html !== lastFinalReviewHtml || !reviewSlot.querySelector('.ak-checkout-final-review')) {
        reviewSlot.innerHTML = html;
        lastFinalReviewHtml = html;
      }

      reviewSlot.querySelectorAll('[data-ak-checkout-review-step]').forEach(function (button) {
        button.onclick = function () {
          setActiveStep(Number(button.getAttribute('data-ak-checkout-review-step')) || 2);
        };
      });

      return reviewSlot;
    }

    function positionMobileFinalReviewSummary() {
      var summary = document.querySelector('.ak-checkout-summary-slot');
      var terms = document.querySelector('.wc-block-checkout__terms');
      var mobileStepFour = activeStep === 4 && window.matchMedia('(max-width: 1023px)').matches;

      if (!summary || !terms || !terms.parentNode) {
        return;
      }

      if (mobileStepFour) {
        if (!summaryHome) {
          summaryHome = { parent: summary.parentNode, nextSibling: summary.nextSibling };
        }
        if (summary.parentNode !== terms.parentNode || summary.nextSibling !== terms) {
          terms.parentNode.insertBefore(summary, terms);
        }
        summary.classList.add('ak-checkout-summary-slot--final-review');
        return;
      }

      if (summaryHome && summaryHome.parent) {
        if (summaryHome.nextSibling && summaryHome.nextSibling.parentNode === summaryHome.parent) {
          summaryHome.parent.insertBefore(summary, summaryHome.nextSibling);
        } else {
          summaryHome.parent.appendChild(summary);
        }
      }
      summary.classList.remove('ak-checkout-summary-slot--final-review');
    }

    function checkoutStepTargets() {
      var companyToggle = document.querySelector('.ak-checkout-company-toggle');
      var companyStep = companyToggle ? companyToggle.closest('.wc-block-components-checkout-step') : null;
      var orderSummary = document.querySelector('.wc-block-components-sidebar');

      return {
        2: uniqueElements([
          closestCheckoutStep('#contact-fields'),
          closestCheckoutStep('#shipping-fields'),
          closestCheckoutStep('#billing-fields'),
          companyStep,
          closestCheckoutStep('.wc-block-checkout__add-note')
        ]),
        3: uniqueElements([
          closestCheckoutStep('#shipping-option'),
          closestCheckoutStep('#payment-method')
        ]),
        4: uniqueElements([
          document.querySelector('.ak-checkout-final-review-slot'),
          document.querySelector('.wc-block-checkout__terms'),
          document.querySelector('.wc-block-components-checkout-place-order-button')
        ]),
        persistent: uniqueElements([
          orderSummary
        ])
      };
    }

    function setActiveStep(step) {
      activeStep = Math.max(2, Math.min(4, Number(step) || 2));
      syncCheckoutStepper();
    }

    function checkoutValidationApi() {
      var validationStore = 'wc/store/validation';

      if (!validationStore || !window.wp || !window.wp.data) {
        return null;
      }

      try {
        return {
          selectors: window.wp.data.select(validationStore),
          dispatch: window.wp.data.dispatch(validationStore)
        };
      } catch (error) {
        return null;
      }
    }

    function validationErrors() {
      var validation = checkoutValidationApi();

      if (!validation || !validation.selectors || typeof validation.selectors.getValidationErrors !== 'function') {
        return [];
      }

      var errors = validation.selectors.getValidationErrors() || {};

      return Object.keys(errors).map(function (key) {
        return { key: key, error: errors[key] || {} };
      }).filter(function (entry) {
        return entry.error.message || entry.error.hidden === false;
      });
    }

    function validationIdentity(entry) {
      return (String(entry.key || '') + ' ' + String((entry.error || {}).message || '')).toLowerCase();
    }

    function billingPersonalNameValidation(entry) {
      var identity = validationIdentity(entry);

      return /billing[-_](first_name|last_name)/.test(identity)
        || (/(keresztnév|vezetéknév|first.?name|last.?name)/.test(identity) && !/(shipping|szállítás)/.test(identity));
    }

    function companyBillingValidation(entry) {
      var identity = validationIdentity(entry);

      return /(appleklinika.*(company|tax)|(company|tax).*appleklinika|cégnév|adószám)/.test(identity);
    }

    function clearInactiveBillingValidationErrors() {
      var validation = checkoutValidationApi();

      if (!validation || !validation.dispatch || typeof validation.dispatch.clearValidationError !== 'function') {
        return;
      }

      var companyToggle = document.getElementById('order-appleklinika-company_purchase');
      var companyMode = Boolean(companyToggle && companyToggle.checked);
      var cleared = false;

      validationErrors().forEach(function (entry) {
        var inactive = companyMode
          ? billingPersonalNameValidation(entry)
          : companyBillingValidation(entry);

        if (inactive) {
          validation.dispatch.clearValidationError(entry.key);
          cleared = true;
        }
      });

      if (cleared) {
        window.setTimeout(function () {
          var message = document.querySelector('.ak-checkout-step-validation-message');
          var currentErrors = validationErrors().filter(function (entry) {
            return validationErrorStep(entry) === activeStep;
          });

          if (message && currentErrors.length === 0) {
            message.textContent = '';
          }
        }, 0);
      }
    }

    document.addEventListener('appleklinika:checkout-company-mode-changed', clearInactiveBillingValidationErrors);

    function validationErrorStep(entry) {
      var identity = (entry.key + ' ' + String(entry.error.message || '')).toLowerCase();

      if (identity.indexOf('terms') !== -1 || identity.indexOf('privacy') !== -1) {
        return 4;
      }

      if (identity.indexOf('payment') !== -1 || identity.indexOf('shipping-rate') !== -1 || identity.indexOf('shipping_method') !== -1) {
        return 3;
      }

      if (identity.indexOf('contact') !== -1 || identity.indexOf('billing') !== -1 || identity.indexOf('shipping') !== -1 || identity.indexOf('company') !== -1 || identity.indexOf('tax') !== -1 || identity.indexOf('address') !== -1) {
        return 2;
      }

      return activeStep;
    }

    function focusValidationError(step, entries) {
      var matching = entries.filter(function (entry) {
        return validationErrorStep(entry) === step;
      });
      var field = null;

      if (matching.length) {
        field = document.getElementById(matching[0].key)
          || document.getElementById(matching[0].key.replace(/_/g, '-'));
      }

      if (!field) {
        var targets = checkoutStepTargets()[step] || [];

        targets.some(function (target) {
          field = target.querySelector('[aria-invalid="true"]');
          return Boolean(field);
        });
      }

      if (field && typeof field.focus === 'function') {
        window.requestAnimationFrame(function () {
          field.focus();
        });
      }
    }

    function announceValidationError(step, entries) {
      var message = document.querySelector('.ak-checkout-step-validation-message');

      if (!message) {
        message = document.createElement('p');
        message.className = 'ak-checkout-step-validation-message';
        message.setAttribute('role', 'alert');
        var stepper = document.querySelector('.ak-checkout-stepper');

        if (stepper && stepper.parentNode) {
          stepper.parentNode.insertBefore(message, stepper.nextSibling);
        }
      }

      if (message) {
        var firstError = entries.filter(function (entry) {
          return validationErrorStep(entry) === step;
        })[0];
        message.textContent = firstError && firstError.error.message
          ? String(firstError.error.message)
          : 'Ellenőrizd a megadott adatokat a továbblépés előtt.';
      }
    }

    function validateCurrentStep(onValid) {
      var validation = checkoutValidationApi();

      if (!validation || !validation.dispatch || typeof validation.dispatch.showAllValidationErrors !== 'function') {
        announceValidationError(activeStep, []);
        return;
      }

      validation.dispatch.showAllValidationErrors();
      window.setTimeout(function () {
        clearInactiveBillingValidationErrors();
        var errors = validationErrors();
        var currentErrors = errors.filter(function (entry) {
          return validationErrorStep(entry) === activeStep;
        });

        if (currentErrors.length) {
          announceValidationError(activeStep, currentErrors);
          focusValidationError(activeStep, currentErrors);
          return;
        }

        var message = document.querySelector('.ak-checkout-step-validation-message');
        if (message) {
          message.textContent = '';
        }
        onValid();
      }, 0);
    }

    function requestStep(step) {
      var requestedStep = Math.max(2, Math.min(4, Number(step) || 2));

      if (requestedStep <= activeStep) {
        setActiveStep(requestedStep);
        return;
      }

      validateCurrentStep(function () {
        setActiveStep(Math.min(requestedStep, activeStep + 1));
      });
    }

    function createStepper(checkoutBlock) {
      var existingStepper = document.querySelector('.ak-checkout-stepper');

      if (existingStepper) {
        return existingStepper;
      }

      var stepper = document.createElement('nav');
      var mobileStatus = document.createElement('p');
      var mobileStatusCount = document.createElement('strong');
      var mobileStatusLabel = document.createElement('span');
      var list = document.createElement('ol');

      stepper.className = 'ak-checkout-stepper';
      stepper.setAttribute('aria-label', 'Pénztár folyamat');
      mobileStatus.className = 'ak-checkout-stepper__mobile-status';
      mobileStatus.setAttribute('aria-live', 'polite');
      mobileStatusCount.className = 'ak-checkout-stepper__mobile-count';
      mobileStatusLabel.className = 'ak-checkout-stepper__mobile-label';
      mobileStatus.appendChild(mobileStatusCount);
      mobileStatus.appendChild(mobileStatusLabel);

      Object.keys(stepLabels).forEach(function (stepKey) {
        var step = Number(stepKey);
        var item = document.createElement('li');
        var control = step === 1 ? document.createElement('a') : document.createElement('button');
        var marker = document.createElement('span');
        var label = document.createElement('span');

        item.className = 'ak-checkout-stepper__item';
        item.setAttribute('data-step-item', String(step));
        control.className = 'ak-checkout-stepper__control';
        control.setAttribute('aria-label', String(step) + '. ' + stepLabels[step]);
        marker.className = 'ak-checkout-stepper__marker';
        label.className = 'ak-checkout-stepper__label';
        marker.textContent = step === 1 ? '✓' : String(step);
        label.textContent = stepLabels[step];

        if (step === 1) {
          control.href = cartUrl();
        } else {
          control.type = 'button';
          control.setAttribute('data-checkout-step-trigger', String(step));
          control.addEventListener('click', function () {
            requestStep(step);
          });
        }

        control.appendChild(marker);
        control.appendChild(label);
        item.appendChild(control);
        list.appendChild(item);
      });

      stepper.appendChild(mobileStatus);
      stepper.appendChild(list);
      checkoutBlock.parentNode.insertBefore(stepper, checkoutBlock);

      return stepper;
    }

    function createCheckoutHeading(checkoutBlock) {
      var existingHeading = document.querySelector('.ak-checkout-title');

      if (existingHeading) {
        return existingHeading;
      }

      var heading = document.createElement('h1');
      heading.className = 'ak-checkout-title';
      heading.textContent = 'Pénztár';
      checkoutBlock.parentNode.insertBefore(heading, checkoutBlock);

      return heading;
    }

    function createNavigationControls(targets) {
      if (!document.querySelector('[data-checkout-step-controls="2"]')) {
        var step2Target = targets[2][targets[2].length - 1] || targets[2][0];

        if (step2Target && step2Target.parentNode) {
          var step2Controls = document.createElement('div');
          var cartLink = document.createElement('a');
          var nextPayment = document.createElement('button');

          step2Controls.className = 'ak-checkout-step-controls';
          step2Controls.setAttribute('data-checkout-step-controls', '2');
          cartLink.className = 'ak-checkout-step-controls__link';
          cartLink.href = cartUrl();
          cartLink.textContent = 'Vissza a kosárhoz';
          nextPayment.className = 'ak-checkout-step-controls__button';
          nextPayment.type = 'button';
          nextPayment.textContent = 'Tovább a szállítás és fizetéshez';
          nextPayment.addEventListener('click', function () {
            requestStep(3);
          });
          step2Controls.appendChild(cartLink);
          step2Controls.appendChild(nextPayment);
          step2Target.parentNode.insertBefore(step2Controls, step2Target.nextSibling);
        }
      }

      if (!document.querySelector('[data-checkout-step-controls="3"]')) {
        var step3Target = targets[3][targets[3].length - 1] || targets[3][0];

        if (step3Target && step3Target.parentNode) {
          var step3Controls = document.createElement('div');
          var backDetails = document.createElement('button');
          var nextSummary = document.createElement('button');

          step3Controls.className = 'ak-checkout-step-controls';
          step3Controls.setAttribute('data-checkout-step-controls', '3');
          backDetails.className = 'ak-checkout-step-controls__link';
          backDetails.type = 'button';
          backDetails.textContent = 'Vissza az adatokhoz';
          backDetails.addEventListener('click', function () {
            setActiveStep(2);
          });
          nextSummary.className = 'ak-checkout-step-controls__button';
          nextSummary.type = 'button';
          nextSummary.textContent = 'Tovább az összegzéshez';
          nextSummary.addEventListener('click', function () {
            requestStep(4);
          });
          step3Controls.appendChild(backDetails);
          step3Controls.appendChild(nextSummary);
          step3Target.parentNode.insertBefore(step3Controls, step3Target.nextSibling);
        }
      }

      if (!document.querySelector('[data-checkout-step-controls="4"]')) {
        var placeOrder = document.querySelector('.wc-block-components-checkout-place-order-button');

        if (placeOrder && placeOrder.parentNode) {
          var step4Controls = document.createElement('div');
          var backPayment = document.createElement('button');

          step4Controls.className = 'ak-checkout-step-controls ak-checkout-step-controls--summary';
          step4Controls.setAttribute('data-checkout-step-controls', '4');
          backPayment.className = 'ak-checkout-step-controls__link';
          backPayment.type = 'button';
          backPayment.textContent = 'Vissza a szállítás és fizetéshez';
          backPayment.addEventListener('click', function () {
            setActiveStep(3);
          });
          step4Controls.appendChild(backPayment);
          placeOrder.parentNode.insertBefore(step4Controls, placeOrder);
        }
      }
    }

    function positionStep3Controls(targets) {
      var controls = document.querySelector('[data-checkout-step-controls="3"]');
      var step3Targets = targets[3] || [];
      var lastMethodSection = step3Targets[step3Targets.length - 1];

      if (!controls || !lastMethodSection || !lastMethodSection.parentNode) {
        return;
      }

      if (controls.previousElementSibling !== lastMethodSection) {
        lastMethodSection.parentNode.insertBefore(controls, lastMethodSection.nextSibling);
      }
    }

    function syncCheckoutStepper() {
      var checkoutBlock = document.querySelector('.wp-block-woocommerce-checkout');

      if (!checkoutBlock) {
        return false;
      }

      createCheckoutHeading(checkoutBlock);
      var stepper = createStepper(checkoutBlock);
      syncCheckoutFinalReview();
      var targets = checkoutStepTargets();
      var allTargets = uniqueElements(targets[2].concat(targets[3]).concat(targets[4]));

      createNavigationControls(targets);
      positionStep3Controls(targets);
      document.body.classList.add('ak-checkout-multistep');
      document.body.classList.remove('ak-checkout-step-2', 'ak-checkout-step-3', 'ak-checkout-step-4');
      document.body.classList.add('ak-checkout-step-' + activeStep);
      document.body.setAttribute('data-ak-checkout-step', String(activeStep));
      positionMobileFinalReviewSummary();

      allTargets.forEach(function (target) {
        var visible = targets[activeStep].indexOf(target) !== -1;

        target.classList.add('ak-checkout-step-section');
        target.classList.toggle('ak-checkout-step-hidden', !visible);
        target.setAttribute('aria-hidden', visible ? 'false' : 'true');
      });

      targets.persistent.forEach(function (target) {
        target.classList.add('ak-checkout-step-persistent-summary');
        target.classList.remove('ak-checkout-step-hidden');
        target.setAttribute('aria-hidden', 'false');
      });

      document.querySelectorAll('.ak-checkout-step-controls').forEach(function (controls) {
        var visible = controls.getAttribute('data-checkout-step-controls') === String(activeStep);

        controls.classList.toggle('ak-checkout-step-hidden', !visible);
        controls.setAttribute('aria-hidden', visible ? 'false' : 'true');
      });

      stepper.querySelectorAll('[data-step-item]').forEach(function (item) {
        var step = Number(item.getAttribute('data-step-item'));
        var isActive = step === activeStep;
        var isComplete = step < activeStep;
        var control = item.querySelector('.ak-checkout-stepper__control');

        item.classList.toggle('is-active', isActive);
        item.classList.toggle('is-complete', isComplete);
        item.classList.toggle('is-pending', step > activeStep);

        if (control) {
          var stateLabel = isActive ? 'aktuális' : (isComplete ? 'teljesítve' : 'következik');
          var marker = item.querySelector('.ak-checkout-stepper__marker');

          control.setAttribute('aria-label', String(step) + '. ' + stepLabels[step] + ', ' + stateLabel);

          if (marker) {
            marker.textContent = isComplete ? '✓' : String(step);
          }

          if (isActive) {
            control.setAttribute('aria-current', 'step');
          } else {
            control.removeAttribute('aria-current');
          }
        }
      });

      var mobileStatusCount = stepper.querySelector('.ak-checkout-stepper__mobile-count');
      var mobileStatusLabel = stepper.querySelector('.ak-checkout-stepper__mobile-label');

      if (mobileStatusCount) {
        mobileStatusCount.textContent = String(activeStep) + ' / 4';
      }

      if (mobileStatusLabel) {
        mobileStatusLabel.textContent = stepLabels[activeStep];
      }

      return true;
    }

    function scheduleCheckoutStepperSync() {
      if (syncFrame !== null) {
        return;
      }

      syncFrame = window.requestAnimationFrame(function () {
        syncFrame = null;
        syncCheckoutStepper();
      });
    }

    syncCheckoutStepper();

    var observer = new MutationObserver(scheduleCheckoutStepperSync);
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });

    document.addEventListener('change', scheduleCheckoutStepperSync);
    document.addEventListener('input', scheduleCheckoutStepperSync);
    window.addEventListener('resize', scheduleCheckoutStepperSync);
  }

  function initCheckoutPaymentAvailabilityGuard() {
    if (!document.body.classList.contains('woocommerce-checkout')) {
      return;
    }

    var unavailableMessage = 'There are no payment methods available. Please contact us for help placing your order.';
    var scheduled = false;

    function hasAvailablePaymentMethod() {
      var paymentStore = 'wc/store/payment';

      if (window.wp && window.wp.data && typeof window.wp.data.select === 'function') {
        try {
          var selectors = window.wp.data.select(paymentStore);
          var methods = selectors && typeof selectors.getAvailablePaymentMethods === 'function'
            ? selectors.getAvailablePaymentMethods()
            : null;

          if (methods && Object.keys(methods).length > 0) {
            return true;
          }
        } catch (error) {
          // The visible WooCommerce control remains a safe fallback while the
          // Blocks store is being replaced during a checkout update.
        }
      }

      return Array.prototype.some.call(
        document.querySelectorAll('#payment-method input[type="radio"]'),
        function (input) { return !input.disabled && input.getAttribute('aria-disabled') !== 'true'; }
      );
    }

    function clearOnlyStaleUnavailablePaymentAnnouncement() {
      scheduled = false;

      if (!hasAvailablePaymentMethod()) {
        return;
      }

      document.querySelectorAll('.a11y-speak-region[aria-live="assertive"]').forEach(function (region) {
        if (region.textContent.replace(/\s+/g, ' ').trim() === unavailableMessage) {
          region.textContent = '';
        }
      });
    }

    function schedulePaymentAnnouncementSync() {
      if (scheduled) {
        return;
      }

      scheduled = true;
      window.requestAnimationFrame(clearOnlyStaleUnavailablePaymentAnnouncement);
    }

    schedulePaymentAnnouncementSync();

    var observer = new MutationObserver(schedulePaymentAnnouncementSync);
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      characterData: true
    });

    if (window.wp && window.wp.data && typeof window.wp.data.subscribe === 'function') {
      window.wp.data.subscribe(schedulePaymentAnnouncementSync);
    }
  }

  function updatePriceFilter(filter) {
    var minInput = filter.querySelector('[data-price-min]');
    var maxInput = filter.querySelector('[data-price-max]');
    var minLabel = filter.querySelector('[data-price-min-label]');
    var maxLabel = filter.querySelector('[data-price-max-label]');

    if (!minInput || !maxInput || !minLabel || !maxLabel) {
      return;
    }

    var min = Number(minInput.value);
    var max = Number(maxInput.value);

    if (min > max) {
      if (document.activeElement === minInput) {
        maxInput.value = String(min);
        max = min;
      } else {
        minInput.value = String(max);
        min = max;
      }
    }

    minLabel.textContent = formatPrice(min);
    maxLabel.textContent = formatPrice(max);
  }

  document.querySelectorAll('[data-price-filter]').forEach(function (filter) {
    updatePriceFilter(filter);
    filter.addEventListener('input', function () {
      updatePriceFilter(filter);
    });
  });

  initWishlistButtons();
  initCompanyCheckoutFields();
  initCheckoutSummary();
  initCheckoutStepper();
  initCheckoutPaymentAvailabilityGuard();

  document.querySelectorAll('.woocommerce-ordering').forEach(function (form) {
    var select = form.querySelector('select.orderby');

    if (!select || form.querySelector('.ak-sort-dropdown')) {
      return;
    }

    var wrapper = document.createElement('div');
    var button = document.createElement('button');
    var list = document.createElement('div');
    var selectedOption = select.options[select.selectedIndex];

    wrapper.className = 'ak-sort-dropdown';
    button.className = 'ak-sort-dropdown__button';
    button.type = 'button';
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');
    button.textContent = selectedOption ? selectedOption.textContent : '';

    list.className = 'ak-sort-dropdown__list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;

    Array.prototype.forEach.call(select.options, function (option) {
      var item = document.createElement('button');

      item.className = 'ak-sort-dropdown__option';
      item.type = 'button';
      item.setAttribute('role', 'option');
      item.setAttribute('data-value', option.value);
      item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
      item.textContent = option.textContent;

      item.addEventListener('click', function () {
        select.value = option.value;
        button.textContent = option.textContent;
        closeSortDropdown();
        select.dispatchEvent(new Event('change', { bubbles: true }));
        form.submit();
      });

      list.appendChild(item);
    });

    function closeSortDropdown() {
      wrapper.classList.remove('is-open');
      button.setAttribute('aria-expanded', 'false');
      list.hidden = true;
    }

    function openSortDropdown() {
      wrapper.classList.add('is-open');
      button.setAttribute('aria-expanded', 'true');
      list.hidden = false;
    }

    button.addEventListener('click', function () {
      if (wrapper.classList.contains('is-open')) {
        closeSortDropdown();
      } else {
        openSortDropdown();
      }
    });

    wrapper.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeSortDropdown();
        button.focus();
      }
    });

    document.addEventListener('click', function (event) {
      if (!wrapper.contains(event.target)) {
        closeSortDropdown();
      }
    });

    wrapper.appendChild(button);
    wrapper.appendChild(list);
    form.classList.add('ak-sort-enhanced');
    form.appendChild(wrapper);
  });

  document.querySelectorAll('[data-cart-qty-control]').forEach(function (control) {
    var input = control.querySelector('input[type="number"]');

    if (!input) {
      return;
    }

    control.addEventListener('click', function (event) {
      var button = event.target.closest('button');

      if (!button) {
        return;
      }

      var step = Number(input.getAttribute('step')) || 1;
      var min = Number(input.getAttribute('min')) || 0;
      var max = Number(input.getAttribute('max')) || 0;
      var current = Number(input.value) || 0;

      if (button.hasAttribute('data-cart-qty-decrease')) {
        input.value = String(Math.max(min, current - step));
      }

      if (button.hasAttribute('data-cart-qty-increase')) {
        input.value = String(max > 0 ? Math.min(max, current + step) : current + step);
      }

      input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    input.addEventListener('change', function () {
      var max = Number(input.getAttribute('max')) || 0;
      var nextValue = Number(input.value) || 0;

      if (max > 0 && nextValue > max) {
        input.value = String(max);
      }
    });
  });

  function initAccountBillingCompanyMode() {
    if (!document.body.classList.contains('woocommerce-account')) {
      return;
    }

    var billingCard = document.querySelector('.ak-account-address--billing');
    var companyToggle = document.getElementById('ak_billing_is_company');
    var taxNumberInput = document.getElementById('ak_billing_tax_number');

    if (!billingCard || !companyToggle) {
      return;
    }

    function accountTaxNumberDigits(value) {
      return String(value || '').replace(/\D/g, '').slice(0, 11);
    }

    function formatAccountTaxNumber(value) {
      var digits = accountTaxNumberDigits(value);
      var firstBlock = digits.slice(0, 8);
      var middleBlock = digits.slice(8, 9);
      var lastBlock = digits.slice(9, 11);

      if (digits.length <= 8) {
        return firstBlock;
      }

      if (digits.length <= 9) {
        return firstBlock + '-' + middleBlock;
      }

      return firstBlock + '-' + middleBlock + '-' + lastBlock;
    }

    function setRequired(input, required) {
      if (!input) {
        return;
      }

      input.required = required;
      input.setAttribute('aria-required', required ? 'true' : 'false');
    }

    function syncAccountCompanyMode() {
      var enabled = companyToggle.checked;

      billingCard.classList.toggle('is-company-enabled', enabled);
      setRequired(document.getElementById('billing_first_name'), !enabled);
      setRequired(document.getElementById('billing_last_name'), !enabled);
      setRequired(document.getElementById('billing_company'), enabled);
      setRequired(taxNumberInput, enabled);
    }

    if (taxNumberInput) {
      taxNumberInput.maxLength = 13;
      taxNumberInput.setAttribute('inputmode', 'numeric');
      taxNumberInput.setAttribute('pattern', '\\d{8}-\\d-\\d{2}');
      taxNumberInput.setAttribute('title', 'Példa: 12345678-1-23');
      taxNumberInput.setAttribute('autocomplete', 'off');

      taxNumberInput.addEventListener('input', function () {
        var normalized = formatAccountTaxNumber(taxNumberInput.value);

        if (taxNumberInput.value !== normalized) {
          taxNumberInput.value = normalized;
        }
      });
    }

    companyToggle.addEventListener('change', syncAccountCompanyMode);
    syncAccountCompanyMode();
  }

  initAccountBillingCompanyMode();

  window.addEventListener('scroll', function () {
    if (ticking) {
      return;
    }

    window.requestAnimationFrame(function () {
      var currentScrollY = window.scrollY || 0;
      var shouldHide = currentScrollY > lastScrollY && currentScrollY > 140;

      document.body.classList.toggle('ak-header-hidden', shouldHide);
      lastScrollY = currentScrollY;
      ticking = false;
    });

    ticking = true;
  }, { passive: true });
}());
