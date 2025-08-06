define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push(
        {
            type: 'payplug_payments_installment_plan',
            component: 'Payplug_Payments/js/view/payment/method-renderer/payplug_payments_installment_plan-method'
        }
    );

    /** Add view logic here if needed */
    return Component.extend({});
});
