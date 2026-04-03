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

    const isHostedFieldsActive = window.checkoutConfig.payment.payplug_payments_standard.is_hosted_fields_active;
    const componentFile = isHostedFieldsActive === true ? 'payplug_payments_standard-method-hosted-fields' : 'payplug_payments_standard-method-integrated';

    rendererList.push(
        {
            type: 'payplug_payments_standard',
            component: 'Payplug_Payments/js/view/payment/method-renderer/' + componentFile
        }
    );

    /** Add view logic here if needed */
    return Component.extend({});
});
