define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/url',
    'Payplug_Payments/js/view/apple-pay/button-apple-pay',
], function ($, ko, Component, url, payplugApplePay) {
    'use strict';

    return Component.extend({
        createQuote: 'payplug_payments/applePay/CreateApplePayQuote',
        applePayIsAvailable: false,
        isVisible: ko.observable(false),

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
         * Create a new quote from the product in the product page
         * And then init Apple Pay Session
         * The async is required as ApplePaySession MUST be created from a user gesture
         *
         * @returns {void}
         */
        handleClick: async function () {
            payplugApplePay.clearOrderData();

            const applePayConfig = this.applePayConfig;
            const form = $('#product_addtocart_form');
            const isValid = form.validation('isValid');

            if (!isValid) {
                return;
            }

            try {
                const formData = new FormData(form[0]);
                const response = await $.ajax({
                    url: url.build(this.createQuote),
                    data: formData,
                    type: 'post',
                    dataType: 'json',
                    cache: false,
                    contentType: false,
                    processData: false
                });

                if (response.success) {
                    const baseAmount = parseFloat(response.base_amount);
                    const isVirtual = response.is_virtual;
                    const workflowType = this.workflowType;

                    const { domain, locale, merchand_name: merchandName, currency: currencyCode } = applePayConfig;
                    const config = { domain, locale, merchand_name: merchandName, currencyCode };

                    payplugApplePay.invalidateMiniCart(true);
                    payplugApplePay.setBaseAmount(baseAmount);
                    payplugApplePay.setIsVirtual(isVirtual);
                    payplugApplePay.setMerchandName(merchandName);
                    payplugApplePay.setWorkflowType(workflowType);
                    payplugApplePay.initApplePaySession(config);
                } else {
                    alert(response.message || 'Could not create quote for Apple Pay');
                }
            } catch (err) {
                alert('Error preparing Apple Pay quote');
            }
        }
    });
});
