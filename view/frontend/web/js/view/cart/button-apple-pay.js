/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Payplug_Payments/js/view/apple-pay/button-apple-pay',
], function (ko, Component, quote, payplugApplePay) {
    'use strict';

    return Component.extend({
        applePayIsAvailable: false,
        isVisible: ko.observable(false),
        isDisabled: ko.observable(false),

        /**
         * Initializes the component.
         *
         * @returns {void}
         */
        initialize: function () {
            this._super();

            this.applePayIsAvailable = payplugApplePay.getApplePayAvailability();
            this.isVisible(this.applePayIsAvailable);
        },

        /**
         * Handles button click event.
         *
         * @returns {void}
         */
        handleClick: function () {
            const baseAmount = this._getBaseAmount();
            const isVirtual = quote.isVirtual();
            const merchandName = window.checkoutConfig.payment.payplug_payments_apple_pay.merchand_name;
            const workflowType = this.workflowType;
            const currencyCode = quote.totals()['quote_currency_code'];
            const config = Object.assign(window.checkoutConfig.payment.payplug_payments_apple_pay, { currencyCode });
            
            this.isDisabled(true);

            payplugApplePay.clearOrderData();
            payplugApplePay.setBaseAmount(baseAmount);
            payplugApplePay.setIsVirtual(isVirtual);
            payplugApplePay.setMerchandName(merchandName);
            payplugApplePay.setWorkflowType(workflowType);
            payplugApplePay.initApplePaySession(config);

            payplugApplePay.applePaySession.oncancel = () => {
                this.isDisabled(false);
            };
        },

        /**
         * Calculates the base amount without shipping cost
         *
         * @private
         * @returns {Number} The base amount without shipping cost
         */
        _getBaseAmount: function () {
            let grandTotal = quote.totals()['grand_total'] + quote.totals()['tax_amount'];
            const totalSegments = quote.totals()['total_segments'];

            if (totalSegments) {
                const totalSegment = totalSegments.find(segment => segment.code.includes('grand_total'));

                if (totalSegment) {
                    grandTotal = totalSegment.value;
                }
            }

            let shippingAmount = quote.totals()['shipping_amount'];

            return (parseFloat(grandTotal) - parseFloat(shippingAmount));
        }
    });
});
