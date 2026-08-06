(function () {
    'use strict';

    document.querySelectorAll('.ak-address-form').forEach(function (form) {
        var company = form.querySelector('[name="company_name"]');
        var tax = form.querySelector('[name="tax_number"]');
        var billingPurpose = form.querySelector('[name="purpose_billing"]');
        var shippingPurpose = form.querySelector('[name="purpose_shipping"]');

        function syncTaxRequirement() {
            if (company && tax) {
                tax.setAttribute('aria-required', company.value.trim() === '' ? 'false' : 'true');
            }
        }

        function syncDefaultControls() {
            [['billing', billingPurpose], ['shipping', shippingPurpose]].forEach(function (item) {
                var control = form.querySelector('[data-address-default="' + item[0] + '"]');
                var input = control ? control.querySelector('input') : null;
                var enabled = Boolean(item[1] && item[1].checked);
                if (!control || !input) {
                    return;
                }
                control.hidden = !enabled;
                input.disabled = !enabled;
                if (!enabled) {
                    input.checked = false;
                }
            });
        }

        if (company && tax) {
            company.addEventListener('input', syncTaxRequirement);
            syncTaxRequirement();
        }
        [billingPurpose, shippingPurpose].forEach(function (control) {
            if (control) {
                control.addEventListener('change', syncDefaultControls);
            }
        });
        syncDefaultControls();
    });
}());
