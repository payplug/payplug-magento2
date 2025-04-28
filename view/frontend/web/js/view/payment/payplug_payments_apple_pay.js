/* @api */
define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    let cfg = window.checkoutConfig.payment.payplug_payments_apple_pay || {};

    if (cfg.enabled_on_checkout) {
        rendererList.push(
            {
                type: 'payplug_payments_apple_pay',
                component: 'Payplug_Payments/js/view/payment/method-renderer/payplug_payments_apple_pay-method'
            }
        );
    }
    return Component.extend({});
});
