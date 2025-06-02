define([
    'jquery',
    'ko',
    'uiComponent',
    'Payplug_Payments/js/view/apple-pay/button-apple-pay',
    'Magento_Catalog/js/price-box',
], function ($, ko, Component, payplugApplePay, priceBox) {
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
            const isValid = this._checkFormValidation();

            if (!isValid) {
                return;
            }

            payplugApplePay.clearOrderData();
            this._initApplePay();
        },

        /**
         * Validates the product add-to-cart form.
         *
         * @private
         * @returns {boolean} True if the form is valid; otherwise, false.
         */
        _checkFormValidation: function() {
            const form = $('#product_addtocart_form');
            return form.validation('isValid');
        },

        /**
         * Returns the product price amount.
         *
         * @returns {Number|null} The product price amount or null if the price could not be retrieved.
         */
        _getAmountPrice: function() {
            const priceBox = $('.product-info-price .price-wrapper');
            const price = priceBox.data('price-amount') || null;

            if (priceBox.length && price) {
                return priceBox.data('price-amount');
            } else {
                console.error('Could not get the product price');
            }
        },

        /**
         * Initializes the Apple Pay session with the necessary configuration.
         *
         * @returns {void}
         */
        _initApplePay: function() {
            const amountPrice = this._getAmountPrice();
            const applePayConfig = this.applePayConfig;
            const { domain, locale, merchand_name: merchandName, currency: currencyCode } = applePayConfig;
            const config = { domain, locale, merchand_name: merchandName, currencyCode };
            const workflowType = this.workflowType;

            if (!amountPrice || !merchandName || !workflowType || !Object.keys(config).length ) {
                return;
            }

            payplugApplePay.invalidateMiniCart(true);
            payplugApplePay.setBaseAmount(amountPrice);
            payplugApplePay.setIsVirtual(true);
            payplugApplePay.setMerchandName(merchandName);
            payplugApplePay.setWorkflowType(workflowType);
            payplugApplePay.initApplePaySession(config);
        }
    });
});
