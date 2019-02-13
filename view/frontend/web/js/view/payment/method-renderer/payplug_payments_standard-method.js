/* @api */
define([
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/action/redirect-on-success',
    'Payplug_Payments/js/action/lightbox-on-success',
    'Payplug_Payments/js/action/oneclick-on-success',
    'jquery',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader'
], function (Component, redirectOnSuccessAction, lightboxOnSuccessAction, oneClickOnSuccessAction, jQuery, customerData, quote, fullScreenLoader) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_standard',
            tracks: { cards: true }
        },

        redirectAfterPlaceOrder: false,
        cards: [],
        sessionCardId: 'payplug-payments-card-id',

        initialize: function () {
            this._super();
            this.loadCards();

            return this;
        },

        afterPlaceOrder: function () {
            fullScreenLoader.stopLoader();
            sessionStorage.removeItem(this.sessionCardId);
            var customerCard = jQuery('.payplug-payments-customer-card:checked');
            if (customerCard.length > 0 && customerCard.data('card-id') !== '') {
                oneClickOnSuccessAction.execute(customerCard.data('card-id'));
                return;
            }
            if (window.checkoutConfig.payment.payplug_payments_standard.is_embedded) {
                lightboxOnSuccessAction.execute();
                return;
            }
            redirectOnSuccessAction.execute();
        },
        getCardLogo: function() {
            return window.checkoutConfig.payment.payplug_payments_standard.logo;
        },
        getCards: function() {
            return this.cards;
        },
        canDisplayCards: function() {
            return this.cards.length > 0;
        },
        loadCards: function () {
            if (window.checkoutConfig.payment.payplug_payments_standard.is_one_click) {
                var cacheKey = 'payplug-payments-cards';

                if (window.checkoutConfig.payment.payplug_payments_standard.should_refresh_cards) {
                    // Issue in Magento 2.1 with private content not refreshed properly by Magento
                    var self = this;
                    customerData.reload([cacheKey], false).done(function(jqXHR) {
                        self.cards = jqXHR[cacheKey].cards;
                    });
                } else {
                    var dataObject = customerData.get(cacheKey);
                    var data = dataObject();
                    if (data.cards) {
                        this.cards = data.cards;
                    }
                }
            }
        },
        isCardSelected: function (data) {
            var selectedCard = null;
            if (sessionStorage.getItem(this.sessionCardId) !== null) {
                selectedCard = sessionStorage.getItem(this.sessionCardId);
            } else {
                selectedCard = window.checkoutConfig.payment.payplug_payments_standard.selected_card_id;
            }

            if (data.id === selectedCard) {
                if (this.getSelectedMethod() === null) {
                    this.selectPaymentMethod();
                }
                return true;
            }
            return false;
        },
        isCardDisabled: function() {
            var selectedMethod = this.getSelectedMethod();
            return selectedMethod !== null && selectedMethod !== this.getCode();
        },
        getBrandLogo: function (brand) {
            brand = brand.toLowerCase();
            if (window.checkoutConfig.payment.payplug_payments_standard.brand_logos[brand]) {
                return window.checkoutConfig.payment.payplug_payments_standard.brand_logos[brand];
            }
            return window.checkoutConfig.payment.payplug_payments_standard.brand_logos.other;
        },
        getCardTemplate: function () {
            return 'Payplug_Payments/payment/card';
        },
        selectCard: function(data) {
            this.selectPaymentMethod();
            jQuery('.payplug-payments-error').hide();
            sessionStorage.setItem(this.sessionCardId, data.id);
            return true;
        },
        getSelectedMethod: function() {
            return quote.paymentMethod() ? quote.paymentMethod().method : null;
        },
        selectPaymentMethod: function() {
            jQuery('.payplug-payments-customer-card').prop('disabled', false);
            return this._super();
        },
        validate: function () {
            if (jQuery('.payplug-payments-customer-card').length > 0 && jQuery('.payplug-payments-customer-card:checked').length === 0) {
                jQuery('.payplug-payments-error').show();
                return false;
            }

            return true;
        }
    });
});
