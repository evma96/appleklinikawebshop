(function () {
    'use strict';

    var namespace = 'appleklinika/address-book';
    var initializedDefaults = false;

    function cartExtension() {
        if (!window.wp || !window.wp.data || !window.wc || !window.wc.wcBlocksData) {
            return null;
        }
        var cart = window.wp.data.select(window.wc.wcBlocksData.cartStore).getCartData();
        return cart && cart.extensions ? cart.extensions[namespace] : null;
    }

    function sendSelection(root) {
        if (!window.wc || !window.wc.blocksCheckout || typeof window.wc.blocksCheckout.extensionCartUpdate !== 'function') {
            return;
        }
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
                version: selected === '__one_off__' ? 0 : Number(parts[1] || 0),
                save: Boolean(save && save.checked),
                set_default: Boolean(defaultControl && defaultControl.checked),
                label: label ? label.value.trim() : ''
            };
        });
        root.classList.add('is-updating');
        window.wc.blocksCheckout.extensionCartUpdate({ namespace: namespace, data: data })
            .catch(function (error) {
                var message = error && error.message ? error.message : 'A mentett cím kiválasztása nem sikerült. Kérjük, próbáld újra.';
                root.querySelectorAll('[data-ak-address-notice]').forEach(function (notice) { notice.textContent = message; });
            })
            .finally(function () { root.classList.remove('is-updating'); });
    }

    function setCustomFields(section, option) {
        if (!option || !option.fields) {
            return;
        }
        var purpose = section.getAttribute('data-ak-address-purpose');
        ['house_number', 'staircase', 'floor', 'door'].forEach(function (name) {
            var input = document.getElementById('ak_' + purpose + '_' + name);
            if (!input) {
                return;
            }
            input.value = option.fields['appleklinika/' + name] || '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
        if (purpose === 'billing') {
            ['company_name', 'tax_number'].forEach(function (name) {
                var input = document.getElementById('ak_billing_' + name);
                if (!input) {
                    return;
                }
                input.value = option.fields['appleklinika/' + name] || '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
    }

    function renderPurpose(root, purpose, options, current) {
        var host = document.getElementById(purpose + '-fields');
        if (!host || host.querySelector('[data-ak-address-purpose="' + purpose + '"]')) {
            return false;
        }
        var section = document.createElement('section');
        section.className = 'ak-checkout-address-selector';
        section.setAttribute('data-ak-address-purpose', purpose);
        var title = purpose === 'billing' ? 'Számlázási cím' : 'Szállítási cím';
        var select = document.createElement('select');
        select.setAttribute('aria-label', title + ' kiválasztása');
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
        var heading = document.createElement('h3');
        heading.textContent = title;
        var notice = document.createElement('p');
        notice.className = 'ak-checkout-address-selector__notice';
        notice.setAttribute('data-ak-address-notice', '');
        section.appendChild(heading);
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
            if (item) {
                setCustomFields(section, item);
            }
            sendSelection(root);
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
            sendSelection(root);
        });
        section.querySelector('[data-ak-address-label]').addEventListener('change', function () { sendSelection(root); });
        defaultControl.addEventListener('change', function () { sendSelection(root); });
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
        if (changed && !initializedDefaults) {
            initializedDefaults = true;
            sendSelection(checkout);
        }
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
}());
