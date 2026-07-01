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
    var taxPattern = '\\d{8}-\\d-\\d{2}';
    var addressDetailLabels = ['Házszám', 'Emelet', 'Lépcsőház', 'Ajtó'];

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

    function syncBillingInternalNameValues(billingSection, companyField) {
      if (!billingSection) {
        return;
      }

      var shippingSection = document.querySelector('#shipping-fields');
      var shippingForm = shippingSection ? (shippingSection.querySelector('.wc-block-components-address-form') || shippingSection) : null;
      var billingForm = billingSection.querySelector('.wc-block-components-address-form') || billingSection;
      var shippingFirst = checkoutAddressInputBySuffix(shippingForm, 'first_name');
      var shippingLast = checkoutAddressInputBySuffix(shippingForm, 'last_name');
      var billingFirst = checkoutAddressInputBySuffix(billingForm, 'first_name');
      var billingLast = checkoutAddressInputBySuffix(billingForm, 'last_name');
      var companyName = companyField && companyField.input ? normalizeText(companyField.input.value) : '';
      var fallbackName = companyName || normalizeText(billingFirst && billingFirst.value) || normalizeText(billingLast && billingLast.value);
      var firstValue = normalizeText(shippingFirst && shippingFirst.value) || fallbackName;
      var lastValue = normalizeText(shippingLast && shippingLast.value) || fallbackName;

      if (billingFirst && firstValue && billingFirst.value !== firstValue) {
        setCheckoutFieldValue(billingFirst, firstValue);
      }

      if (billingLast && lastValue && billingLast.value !== lastValue) {
        setCheckoutFieldValue(billingLast, lastValue);
      }
    }

    function bindBillingCompanyNameSync(billingSection, companyField) {
      if (!billingSection || !companyField || !companyField.input || billingSection.dataset.akBillingCompanyNameSyncBound === '1') {
        return;
      }

      billingSection.dataset.akBillingCompanyNameSyncBound = '1';

      ['first_name', 'last_name'].forEach(function (suffix) {
        var shippingInput = checkoutAddressInputBySuffix(document.querySelector('#shipping-fields'), suffix);

        if (shippingInput) {
          shippingInput.addEventListener('input', function () {
            if (billingSection.classList.contains('ak-checkout-company-mode')) {
              syncBillingInternalNameValues(billingSection, companyField);
            }
          });
        }
      });

      companyField.input.addEventListener('input', function () {
        if (billingSection.classList.contains('ak-checkout-company-mode')) {
          syncBillingInternalNameValues(billingSection, companyField);
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

      if (companyMode) {
        [fields.firstName, fields.lastName].forEach(function (field) {
          if (field && field.input && field.input.dataset.akPersonalBillingStored !== '1') {
            field.input.dataset.akPersonalBillingStored = '1';
            field.input.dataset.akPersonalBillingValue = field.input.value || '';
          }
        });

        syncBillingInternalNameValues(billingSection, companyField);
      } else {
        [fields.firstName, fields.lastName].forEach(function (field) {
          if (field && field.wrapper) {
            field.wrapper.classList.remove('is-company-hidden');
            field.wrapper.setAttribute('aria-hidden', 'false');
            field.input.required = true;
            field.input.setAttribute('aria-required', 'true');

            if (field.input.dataset.akPersonalBillingStored === '1') {
              setCheckoutFieldValue(field.input, field.input.dataset.akPersonalBillingValue || '');
              delete field.input.dataset.akPersonalBillingStored;
              delete field.input.dataset.akPersonalBillingValue;
            }
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

    function syncCheckoutAddressDetails() {
      syncCheckoutAddressDetailsForSection(document.querySelector('#shipping-fields'));
      syncCheckoutAddressDetailsForSection(document.querySelector('#billing-fields'));
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
      bindBillingCompanyNameSync(billingSection, companyField);
      syncBillingFormLayout(billingSection, companyField);

      return slot;
    }

    function syncCompanyCheckoutFields() {
      syncCompanyCheckoutHeading();
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

      if (!enabled && hiddenChanged) {
        clearCompanyCheckoutValues(companyField, taxField);
      }

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

    var config = window.appleklinikaCheckoutSummary || {};

    if (!config.html) {
      return;
    }

    var syncFrame = null;

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

      if (!slot.querySelector('.ak-checkout-summary')) {
        slot.innerHTML = config.html;
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
    var stepLabels = {
      1: 'Kosár',
      2: 'Szállítás és számlázás',
      3: 'Szállítási mód és fizetés',
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

    function createStepper(checkoutBlock) {
      var existingStepper = document.querySelector('.ak-checkout-stepper');

      if (existingStepper) {
        return existingStepper;
      }

      var stepper = document.createElement('nav');
      var list = document.createElement('ol');

      stepper.className = 'ak-checkout-stepper';
      stepper.setAttribute('aria-label', 'Pénztár folyamat');

      Object.keys(stepLabels).forEach(function (stepKey) {
        var step = Number(stepKey);
        var item = document.createElement('li');
        var control = step === 1 ? document.createElement('a') : document.createElement('button');
        var marker = document.createElement('span');
        var label = document.createElement('span');

        item.className = 'ak-checkout-stepper__item';
        item.setAttribute('data-step-item', String(step));
        control.className = 'ak-checkout-stepper__control';
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
            setActiveStep(step);
          });
        }

        control.appendChild(marker);
        control.appendChild(label);
        item.appendChild(control);
        list.appendChild(item);
      });

      stepper.appendChild(list);
      checkoutBlock.parentNode.insertBefore(stepper, checkoutBlock);

      return stepper;
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
            setActiveStep(3);
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
            setActiveStep(4);
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

    function syncCheckoutStepper() {
      var checkoutBlock = document.querySelector('.wp-block-woocommerce-checkout');

      if (!checkoutBlock) {
        return false;
      }

      var stepper = createStepper(checkoutBlock);
      var targets = checkoutStepTargets();
      var allTargets = uniqueElements(targets[2].concat(targets[3]).concat(targets[4]));

      createNavigationControls(targets);
      document.body.classList.add('ak-checkout-multistep');
      document.body.classList.remove('ak-checkout-step-2', 'ak-checkout-step-3', 'ak-checkout-step-4');
      document.body.classList.add('ak-checkout-step-' + activeStep);
      document.body.setAttribute('data-ak-checkout-step', String(activeStep));

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
          if (isActive) {
            control.setAttribute('aria-current', 'step');
          } else {
            control.removeAttribute('aria-current');
          }
        }
      });

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
      var current = Number(input.value) || 0;

      if (button.hasAttribute('data-cart-qty-decrease')) {
        input.value = String(Math.max(min, current - step));
      }

      if (button.hasAttribute('data-cart-qty-increase')) {
        input.value = String(current + step);
      }

      input.dispatchEvent(new Event('change', { bubbles: true }));
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
