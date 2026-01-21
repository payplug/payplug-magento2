/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push(
        {
            type: 'payplug_payments_bizum',
            component: 'Payplug_Payments/js/view/payment/method-renderer/payplug_payments_bizum-method'
        }
    );

    /** Add view logic here if needed */
    return Component.extend({});
});
