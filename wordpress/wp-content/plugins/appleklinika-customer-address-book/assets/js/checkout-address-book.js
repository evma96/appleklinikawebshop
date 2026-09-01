(function () {
    'use strict';

    var namespace = 'appleklinika/address-book';
    var blocksCheckout = window.wc && window.wc.blocksCheckout;
    var latestSelectionRequest = Promise.resolve();
    var selectionRequestSequence = 0;

    function cartExtension() {
        if (!window.wp || !window.wp.data) {
            return null;
        }
        var cart = window.wp.data.select('wc/store/cart').getCartData();
        return cart && cart.extensions ? cart.extensions[namespace] : null;
    }

    function selectionData(root) {
        var data = {};
        root.querySelectorAll('[data-ak-address-purpose]').forEach(function (section) {
            var purpose = section.getAttribute('data-ak-address-purpose');
            var select = section.querySelector('select');
            var save = section.querySelector('[data-ak-address-save]');
            var label = section.querySelector('[data-ak-address-label]');
            var defaultControl = section.querySelector('[data-ak-address-default]');
            var selected = select ? select.value : '__one_off__';
            var parts = selected.split('|');
            data[purpose] = {
                mode: selected === '__one_off__' ? 'one_off' : 'saved',
                key: selected === '__one_off__' ? '' : parts[0],
                version: selected === '__one_off__' ? 0 : Number(parts[1] || 0)
            };
            if (selected === '__one_off__') {
                data[purpose].fields = oneOffAddressFields(root, purpose) || {};
            }
        });
        return data;
    }

    function saveIntentData(root) {
        var data = {};
        root.querySelectorAll('[data-ak-address-purpose]').forEach(function (section) {
            var purpose = section.getAttribute('data-ak-address-purpose');
            var save = section.querySelector('[data-ak-address-save]');
            var label = section.querySelector('[data-ak-address-label]');
            var defaultControl = section.querySelector('[data-ak-address-default]');
            data[purpose] = {
                save: Boolean(save && save.checked),
                set_default: Boolean(defaultControl && defaultControl.checked),
                label: label ? label.value.trim() : ''
            };
        });
        return data;
    }

    function sendSaveIntent(root) {
        return sendAddressBookUpdate(root, {
            selection: selectionData(root),
            intent: saveIntentData(root)
        });
    }

    function sendAddressBookUpdate(root, data) {
        if (!blocksCheckout || typeof blocksCheckout.extensionCartUpdate !== 'function') {
            return Promise.resolve();
        }
        var sequence = ++selectionRequestSequence;
        var request = function () {
            root.classList.add('is-updating');
            return blocksCheckout.extensionCartUpdate({ namespace: namespace, data: data })
                .then(function (result) {
                    root.querySelectorAll('[data-ak-address-notice]').forEach(function (notice) { notice.textContent = ''; });
                    return result;
                })
                .catch(function (error) {
                    var message = error && error.message ? error.message : 'A mentett cím kiválasztása nem sikerült. Kérjük, próbáld újra.';
                    root.querySelectorAll('[data-ak-address-notice]').forEach(function (notice) { notice.textContent = message; });
                    throw error;
                })
                .finally(function () {
                    if (sequence === selectionRequestSequence) {
                        root.classList.remove('is-updating');
                    }
                });
        };
        latestSelectionRequest = latestSelectionRequest.catch(function () {}).then(request);
        return latestSelectionRequest;
    }

    function oneOffAddressFields(root, purpose) {
        var section = root.querySelector('[data-ak-address-purpose="' + purpose + '"]');
        var select = section ? section.querySelector('select') : null;
        var fields = {};

        if (!section || !select || select.value !== '__one_off__') {
            return null;
        }

        ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country'].forEach(function (field) {
            var control = document.getElementById(purpose + '-' + field);
            if (control) {
                fields[field] = String(control.value || '');
            }
        });
        ['house_number', 'staircase', 'floor', 'door'].forEach(function (field) {
            var control = document.getElementById(purpose + '-appleklinika-' + field);
            if (control) {
                fields['appleklinika/' + field] = String(control.value || '');
            }
        });
        if (purpose === 'billing') {
            var companyPurchase = document.getElementById('order-appleklinika-company_purchase');
            var companyName = document.getElementById('order-appleklinika-company_name');
            var taxNumber = document.getElementById('order-appleklinika-tax_number');
            fields['appleklinika/company_purchase'] = companyPurchase && companyPurchase.checked ? '1' : '';
            fields['appleklinika/company_name'] = companyName ? String(companyName.value || '') : '';
            fields['appleklinika/tax_number'] = taxNumber ? String(taxNumber.value || '') : '';
        }

        return fields;
    }

    function sameAddressFields(expected, actual) {
        return Object.keys(expected).every(function (field) {
            return String(actual && actual[field] || '') === expected[field];
        });
    }

    function waitForOneOffAddressSync(root) {
        var expected = {};

        ['billing', 'shipping'].forEach(function (purpose) {
            var fields = oneOffAddressFields(root, purpose);
            if (fields) {
                // Company identity belongs to the checkout's additional-fields
                // contract, never to the Cart API address object. Waiting for it
                // here would make a valid one-off company address time out.
                delete fields['appleklinika/company_purchase'];
                delete fields['appleklinika/company_name'];
                delete fields['appleklinika/tax_number'];
                expected[purpose] = fields;
            }
        });

        if (Object.keys(expected).length === 0) {
            return Promise.resolve(true);
        }

        return new Promise(function (resolve) {
            var deadline = Date.now() + 3000;
            var verify = function () {
                var cart = window.wp && window.wp.data
                    ? window.wp.data.select('wc/store/cart').getCartData()
                    : null;
                var synchronized = cart && Object.keys(expected).every(function (purpose) {
                    return sameAddressFields(expected[purpose], cart[purpose + 'Address']);
                });

                if (synchronized) {
                    resolve(true);
                    return;
                }
                if (Date.now() >= deadline) {
                    resolve(false);
                    return;
                }
                window.setTimeout(verify, 50);
            };
            verify();
        });
    }

    function flushSelection(root) {
        return waitForOneOffAddressSync(root).then(function (addressesSynchronized) {
            if (!addressesSynchronized) {
                root.querySelectorAll('[data-ak-address-notice]').forEach(function (notice) {
                    notice.textContent = 'A megadott címet még szinkronizáljuk. Kérjük, próbáld újra.';
                });
                return false;
            }
            return sendSaveIntent(root).catch(function () { return false; });
        });
    }

    function installProgressFlush(root) {
        if (root.getAttribute('data-ak-address-progress-flush') === '1') {
            return;
        }
        root.setAttribute('data-ak-address-progress-flush', '1');
        root.addEventListener('change', function (event) {
            var control = event.target;
            if (!control || !control.matches || !control.matches('.wc-block-checkout__use-address-for-billing input[type="checkbox"]')) {
                return;
            }

            // Woo Blocks handles this control on its checkout root before this
            // bubbling handler runs. Sync against the current live host, rather
            // than a translated label or a speculative rendered frame.
            sync();
        });
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-checkout-step-controls="2"] .ak-checkout-step-controls__button');
            if (!button) {
                return;
            }
            if (button.getAttribute('data-ak-address-flushed') === '1') {
                button.removeAttribute('data-ak-address-flushed');
                return;
            }
            if (button.getAttribute('data-ak-address-flushing') === '1') {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            button.setAttribute('data-ak-address-flushing', '1');
            button.disabled = true;
            flushSelection(root).then(function (synchronized) {
                button.disabled = false;
                button.removeAttribute('data-ak-address-flushing');
                if (synchronized !== false) {
                    button.setAttribute('data-ak-address-flushed', '1');
                    button.click();
                }
            });
        }, true);
    }

    function setCheckoutControlValue(control, value) {
        var prototype = control.tagName === 'SELECT'
            ? window.HTMLSelectElement.prototype
            : window.HTMLInputElement.prototype;
        var setter = Object.getOwnPropertyDescriptor(prototype, 'value');
        if (setter && setter.set) {
            setter.set.call(control, value);
        } else {
            control.value = value;
        }
        control.dispatchEvent(new Event('input', { bubbles: true }));
        control.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setCheckoutControlChecked(control, checked) {
        if (control.checked !== checked) {
            control.click();
        }
    }

    function setCustomFields(section, option) {
        if (!option || !option.fields) {
            return;
        }
        var purpose = section.getAttribute('data-ak-address-purpose');
        ['country', 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode'].forEach(function (name) {
            var input = document.getElementById(purpose + '-' + name);
            if (input) {
                setCheckoutControlValue(input, option.fields[name] || '');
            }
        });
        ['house_number', 'staircase', 'floor', 'door'].forEach(function (name) {
            var input = document.getElementById(purpose + '-appleklinika-' + name);
            if (!input) {
                return;
            }
            setCheckoutControlValue(input, option.fields['appleklinika/' + name] || '');
        });
        if (purpose === 'billing') {
            var companyPurchaseInput = document.getElementById('order-appleklinika-company_purchase');
            if (companyPurchaseInput) {
                setCheckoutControlChecked(companyPurchaseInput, option.fields['appleklinika/company_purchase'] === '1');
            }
            {
                var input = document.getElementById('order-appleklinika-company_name');
                if (input) {
                    setCheckoutControlValue(input, option.fields['appleklinika/company_name'] || '');
                }
            }
            {
                var taxInput = document.getElementById('order-appleklinika-tax_number');
                if (taxInput) {
                    setCheckoutControlValue(taxInput, option.fields['appleklinika/tax_number'] || '');
                }
            }
        }
    }

    function clearSavedAddressProjection(section) {
        var purpose = section.getAttribute('data-ak-address-purpose');

        // Selecting a one-off address starts a new physical address. Woo Blocks
        // keeps its hidden core fields mounted, so explicitly clear every value
        // that may have been projected from the previously selected saved address.
        // Contact details are deliberately not part of this list: they remain
        // customer-profile data rather than address-book data.
        ['country', 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode'].forEach(function (name) {
            var input = document.getElementById(purpose + '-' + name);
            if (input) {
                setCheckoutControlValue(input, '');
            }
        });
        ['house_number', 'staircase', 'floor', 'door'].forEach(function (name) {
            var input = document.getElementById(purpose + '-appleklinika-' + name);
            if (input) {
                setCheckoutControlValue(input, '');
            }
        });
        if (purpose === 'billing') {
            var companyPurchaseInput = document.getElementById('order-appleklinika-company_purchase');
            if (companyPurchaseInput) {
                setCheckoutControlChecked(companyPurchaseInput, false);
            }
            ['order-appleklinika-company_name', 'order-appleklinika-tax_number'].forEach(function (id) {
                var input = document.getElementById(id);
                if (input) {
                    setCheckoutControlValue(input, '');
                }
            });
        }
    }

    function syncPresentation(section) {
        var select = section.querySelector('select');
        var isOneOff = !select || select.value === '__one_off__';
        section.classList.toggle('is-one-off', isOneOff);
        section.classList.toggle('has-saved-address', !isOneOff);

        if (!isOneOff) {
            var save = section.querySelector('[data-ak-address-save]');
            var saveDetails = section.querySelector('[data-ak-address-save-details]');
            var defaultControl = section.querySelector('[data-ak-address-default]');
            if (save) {
                save.checked = false;
            }
            if (saveDetails) {
                saveDetails.hidden = true;
            }
            if (defaultControl) {
                defaultControl.checked = false;
                defaultControl.disabled = true;
            }
        }
    }

    function liveCheckoutHost(purpose) {
        var host = document.getElementById(purpose + '-fields');
        return host && host.isConnected ? host : null;
    }

    function renderPurpose(root, purpose, options, current) {
        var host = liveCheckoutHost(purpose);
        if (!host || host.querySelector('[data-ak-address-purpose="' + purpose + '"]')) {
            return false;
        }
        var section = document.createElement('section');
        section.className = 'ak-checkout-address-selector';
        section.setAttribute('data-ak-address-purpose', purpose);
        var title = purpose === 'billing' ? 'Számlázási cím' : 'Szállítási cím';
        var selectorCaption = purpose === 'billing'
            ? 'Válassz mentett számlázási címet'
            : 'Válassz mentett szállítási címet';
        var select = document.createElement('select');
        select.id = 'ak-checkout-address-selector-' + purpose;
        var oneOff = document.createElement('option');
        oneOff.value = '__one_off__';
        oneOff.textContent = 'Új vagy egyszeri cím';
        select.appendChild(oneOff);
        options.forEach(function (option) {
            var item = document.createElement('option');
            item.value = option.key + '|' + option.version;
            item.textContent = option.label + ' — ' + option.name + ', ' + option.preview + (option.is_default ? ' (alapértelmezett)' : '');
            if (current && current.mode === 'saved' && current.key === option.key && Number(current.version) === Number(option.version)) {
                item.selected = true;
            } else if (!current && option.is_default) {
                item.selected = true;
            }
            select.appendChild(item);
        });
        var caption = document.createElement('label');
        caption.className = 'ak-checkout-address-selector__caption';
        caption.htmlFor = select.id;
        caption.textContent = selectorCaption;
        var notice = document.createElement('p');
        notice.className = 'ak-checkout-address-selector__notice';
        notice.setAttribute('data-ak-address-notice', '');
        section.appendChild(caption);
        section.appendChild(select);
        section.appendChild(notice);

        var savePanel = document.createElement('div');
        savePanel.className = 'ak-checkout-address-selector__save';
        savePanel.innerHTML = '<label><input type="checkbox" data-ak-address-save> Mentés a Címeim közé</label><div data-ak-address-save-details hidden><label class="ak-checkout-address-selector__label">Cím elnevezése<input type="text" data-ak-address-label maxlength="80"></label><label><input type="checkbox" data-ak-address-default disabled> Legyen alapértelmezett ' + (purpose === 'billing' ? 'számlázási' : 'szállítási') + ' cím</label></div>';
        section.appendChild(savePanel);
        host.insertBefore(section, host.firstChild);

        var matchingOption = function () {
            return options.find(function (option) { return select.value === option.key + '|' + option.version; });
        };
        select.addEventListener('change', function () {
            var item = matchingOption();
            syncPresentation(section);
            if (item) {
                setCustomFields(section, item);
            } else {
                clearSavedAddressProjection(section);
            }
        });
        var save = section.querySelector('[data-ak-address-save]');
        var defaultControl = section.querySelector('[data-ak-address-default]');
        var saveDetails = section.querySelector('[data-ak-address-save-details]');
        save.addEventListener('change', function () {
            saveDetails.hidden = !save.checked;
            defaultControl.disabled = !save.checked;
            if (!save.checked) {
                defaultControl.checked = false;
            }
        });
        syncPresentation(section);
        setCustomFields(section, matchingOption());
        return !current && select.value !== '__one_off__';
    }

    function sync() {
        var data = cartExtension();
        if (!data || !data.enabled) {
            return;
        }
        var checkout = document.querySelector('.wp-block-woocommerce-checkout');
        if (!checkout) {
            return;
        }
        var changed = renderPurpose(checkout, 'billing', data.billing || [], data.selection ? data.selection.billing : null);
        if (data.needs_shipping) {
            changed = renderPurpose(checkout, 'shipping', data.shipping || [], data.selection ? data.selection.shipping : null) || changed;
        }
        installProgressFlush(checkout);
    }

    var attempts = 0;
    var timer = window.setInterval(function () {
        sync();
        attempts += 1;
        if (attempts > 60 || document.querySelector('[data-ak-address-purpose]')) {
            window.clearInterval(timer);
        }
    }, 300);
    document.addEventListener('wc-blocks_render_blocks_frontend', sync);
    if (window.wp && window.wp.data && typeof window.wp.data.subscribe === 'function') {
        window.wp.data.subscribe(sync);
    }
}());
