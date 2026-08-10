(function () {
    'use strict';

    document.querySelectorAll('.ak-address-form').forEach(function (form) {
        var billingPurpose = form.querySelector('[name="purpose_billing"]');
        var shippingPurpose = form.querySelector('[name="purpose_shipping"]');
        var billingType = form.querySelector('[data-ak-billing-type]');
        var companyIdentity = form.querySelector('[data-ak-company-identity]');
        var recipientIdentity = form.querySelector('[data-ak-recipient-identity]');
        var recipientLegend = form.querySelector('[data-ak-recipient-legend]');
        var company = form.querySelector('[name="company_name"]');
        var tax = form.querySelector('[name="tax_number"]');
        var firstName = form.querySelector('[name="first_name"]');
        var lastName = form.querySelector('[name="last_name"]');

        function companyMode() {
            var selected = form.querySelector('[name="billing_identity_type"]:checked');
            return Boolean(selected && selected.value === 'company');
        }

        function setFieldState(field, enabled, required) {
            if (!field) {
                return;
            }
            field.disabled = !enabled;
            field.required = Boolean(required);
            field.setAttribute('aria-required', required ? 'true' : 'false');
        }

        function setGroupState(group, visible) {
            if (!group) {
                return;
            }
            group.hidden = !visible;
            group.setAttribute('aria-hidden', visible ? 'false' : 'true');
        }

        function syncIdentity() {
            var isBilling = Boolean(billingPurpose && billingPurpose.checked);
            var isShipping = Boolean(shippingPurpose && shippingPurpose.checked);
            var isCompany = isBilling && companyMode();
            var showRecipient = !isBilling || !isCompany || isShipping;

            setGroupState(billingType, isBilling);
            setGroupState(companyIdentity, isCompany);
            setGroupState(recipientIdentity, showRecipient);
            setFieldState(company, isCompany, isCompany);
            setFieldState(tax, isCompany, isCompany);
            setFieldState(firstName, showRecipient, showRecipient);
            setFieldState(lastName, showRecipient, showRecipient);

            if (recipientLegend) {
                recipientLegend.textContent = isBilling && !isCompany ? 'Személyes számlázási név' : 'Szállítási címzett';
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

        form.querySelectorAll('[name="billing_identity_type"], [name="purpose_billing"], [name="purpose_shipping"]').forEach(function (control) {
            control.addEventListener('change', function () {
                syncIdentity();
                syncDefaultControls();
            });
        });

        syncIdentity();
        syncDefaultControls();
    });
}());
