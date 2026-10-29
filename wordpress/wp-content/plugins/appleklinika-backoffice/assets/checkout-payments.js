/* Narrow Woo Blocks eligibility; native Woo remains responsible for selection and payment. */
(function () {
    'use strict';
    window.wc.wcBlocksRegistry.registerPaymentMethodExtensionCallbacks('appleklinika-launch-payments', {
        cod: function () { return false; },
        bacs: function () { return false; }
    });
}());
