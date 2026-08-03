(function () {
    'use strict';

    document.querySelectorAll('.ak-address-form').forEach(function (form) {
        var company = form.querySelector('[name="company_name"]');
        var tax = form.querySelector('[name="tax_number"]');
        if (!company || !tax) {
            return;
        }
        company.addEventListener('input', function () {
            tax.setAttribute('aria-required', company.value.trim() === '' ? 'false' : 'true');
        });
    });
}());
