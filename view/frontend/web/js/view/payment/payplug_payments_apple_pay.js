define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push(
        {
            type: 'payplug_payments_apple_pay',
            component: 'Payplug_Payments/js/view/payment/method-renderer/payplug_payments_apple_pay-method'
        }
    );

    return Component.extend({});
});
