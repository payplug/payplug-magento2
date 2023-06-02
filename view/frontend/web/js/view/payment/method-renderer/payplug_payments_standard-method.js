/* @api */
define([
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/action/redirect-on-success',
    'Payplug_Payments/js/action/lightbox-on-success',
    'jquery',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'payplugIntegrated',
    'mage/url',
    'ko',
    'mage/translate'
], function (Component, redirectOnSuccessAction, lightboxOnSuccessAction, jQuery, customerData, quote, fullScreenLoader, payplug, url, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_standard',
            tracks: { cards: true }
        },

        redirectAfterPlaceOrder: false,
        cards: [],
        sessionCardId: 'payplug-payments-card-id',
        integratedApi: null,
        integratedForm: null,
        canDisplayIntegratedForm: ko.observable(false),
        inputStyle:{
            default: {
                color: '#2B343D',
                fontFamily: 'Poppins, sans-serif',
                fontSize: '14px',
                textAlign: 'left',
                '::placeholder': {
                    color: '#969a9f',
                },
                ':focus': {
                    color: '#2B343D',
                }
            },
            invalid: {
                color: '#E91932'
            }
        },

        initialize: function () {
            this._super();
            this.loadCards();
            jQuery('body').on('change', '[name="payment[payplug_payments_standard][customer_card_id]"]', function() {
                var customerCard = jQuery('.payplug-payments-customer-card:checked');
                if (customerCard.length > 0 && customerCard.data('card-id') === '') {
                    this.canDisplayIntegratedForm(true);
                } else {
                    this.canDisplayIntegratedForm(false);
                }
            }.bind(this));
            if (!this.getInitialSelectedCard()) {
                this.canDisplayIntegratedForm(true);
            }

            return this;
        },

        afterPlaceOrder: function () {
            sessionStorage.removeItem(this.sessionCardId);
            if (this.getSelectedCardId() !== '') {
                fullScreenLoader.stopLoader();
                lightboxOnSuccessAction.execute(true);
                return;
            }
            if (!this.isIntegrated()) {
                if (window.checkoutConfig.payment.payplug_payments_standard.is_embedded) {
                    fullScreenLoader.stopLoader();
                    lightboxOnSuccessAction.execute();
                    return;
                }

                fullScreenLoader.stopLoader();
                redirectOnSuccessAction.execute();
                return;
            }

            fullScreenLoader.startLoader();
            let self = this;
            jQuery.ajax({
                url: url.build('payplug_payments/payment/standard') + '?should_redirect=0&integrated=1',
                type: "GET",
                dataType: 'json',
                success: function(response) {
                    if (response.error === true) {
                        alert(response.message);
                        window.location.replace(response.url);
                    } else {
                        if (typeof response.payment_id !== 'undefined' && response.payment_id) {
                            let selectedScheme = payplug.Scheme.AUTO;
                            let selectedCardType = jQuery('.form-integrated [name="scheme"]:checked');
                            if (selectedCardType.length > 0) {
                                selectedCardType = selectedCardType.data('card-type');
                                if (payplug.Scheme[selectedCardType]) {
                                    selectedScheme = payplug.Scheme[selectedCardType];
                                }
                            }
                            fullScreenLoader.stopLoader();
                            let saveCard = window.checkoutConfig.payment.payplug_payments_standard.is_one_click &&
                                jQuery('[name="save_card"]').is(':checked');
                            self.integratedApi.pay(response.payment_id, selectedScheme, {save_card: saveCard});
                            self.integratedApi.onCompleted(function (event) {
                                window.location.replace(url.build('payplug_payments/payment/paymentReturn'));
                            });
                        } else {
                            window.location.replace(url.build('payplug_payments/payment/cancel'));
                        }
                    }
                }
            });
        },
        getCardLogo: function() {
            return window.checkoutConfig.payment.payplug_payments_standard.logo;
        },
        getCards: function() {
            return this.cards;
        },
        canDisplayCardsInTitle: function() {
            return window.checkoutConfig.payment.payplug_payments_standard.display_cards_in_container === false &&
                this.canDisplayCards();
        },
        canDisplayCardsInContainer: function() {
            return window.checkoutConfig.payment.payplug_payments_standard.display_cards_in_container === true &&
                this.canDisplayCards();
        },
        canDisplayCards: function() {
            return window.checkoutConfig.payment.payplug_payments_standard.is_one_click && this.cards.length > 0;
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
            if (data.id === this.getInitialSelectedCard()) {
                if (this.getSelectedMethod() === null) {
                    this.selectPaymentMethod();
                }
                return true;
            }
            return false;
        },
        getInitialSelectedCard: function() {
            if (sessionStorage.getItem(this.sessionCardId) !== null) {
                return sessionStorage.getItem(this.sessionCardId);
            }

            return window.checkoutConfig.payment.payplug_payments_standard.selected_card_id;
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
        },
        getData: function () {
            var parentData = this._super();

            var customerCardId = this.getSelectedCardId();
            if (customerCardId !== '') {
                parentData['additional_data'] = {
                    'payplug_payments_customer_card_id': customerCardId
                };
            }

            return parentData;
        },
        getSelectedCardId: function() {
            var customerCard = jQuery('.payplug-payments-customer-card:checked');
            if (customerCard.length > 0 && customerCard.data('card-id')) {
                return customerCard.data('card-id');
            }

            return '';
        },
        placeOrder: function(data, event) {
           if (this.isIntegrated() && this.getSelectedCardId() === '') {
               let allFieldsAlreadyValid = true;
               jQuery.each(this.integratedForm, function (key, data) {
                   allFieldsAlreadyValid = allFieldsAlreadyValid && this.integratedForm[key].valid;
               }.bind(this));
               if (allFieldsAlreadyValid) {
                   this._super(data, event);

                   return;
               }

               this.integratedApi.validateForm();
               let original = this._super.bind(this);
               this.integratedApi.onValidateForm(({isFormValid}) => {
                   if (isFormValid) {
                       original(data, event);
                   }
               });
               return;
           }
           this._super(data, event);
        },
        isIntegrated: function () {
            return window.checkoutConfig.payment.payplug_payments_standard.is_integrated &&
                typeof window.checkoutConfig.payment.payplug_payments_standard.is_sandbox !== 'undefined';
        },
        initIntegratedForm: function() {
            if (!this.isIntegrated()) {
                return;
            }
            if (this.integratedApi !== null) {
                return;
            }

            this.integratedApi = new payplug.IntegratedPayment(window.checkoutConfig.payment.payplug_payments_standard.is_sandbox);
            this.integratedApi.setDisplayMode3ds(payplug.DisplayMode3ds.LIGHTBOX);
            this.integratedForm = {};
            this.integratedForm.cardholder = {element: this.integratedApi.cardHolder(
                document.querySelector('.cardholder-input-container'),
                {default: this.inputStyle.default, placeholder: jQuery.mage.__('Payplug Card Holder placeholder')}
            ), valid: false};
            this.integratedForm.pan = {element: this.integratedApi.cardNumber(
                document.querySelector('.pan-input-container'),
                {default: this.inputStyle.default, placeholder: jQuery.mage.__('Payplug Card Number placeholder')}
            ), valid: false};
            this.integratedForm.cvv = {element: this.integratedApi.cvv(
                document.querySelector('.cvv-input-container'),
                {default: this.inputStyle.default, placeholder: jQuery.mage.__('Payplug Card CVV placeholder')}
            ), valid: false};
            this.integratedForm.exp = {element: this.integratedApi.expiration(
                document.querySelector('.exp-input-container'),
                {default: this.inputStyle.default, placeholder: jQuery.mage.__('Payplug Card Expiration placeholder')}
            ), valid: false};

            let self = this;
            jQuery.each(this.integratedForm, function (key, data) {
                data.element.onChange(function(result) {
                    self.integratedForm[key].valid = result.valid;
                    let inputContainer = jQuery('.' + key + '-input-container');
                    if (result.valid) {
                        inputContainer.removeClass('error-invalid').removeClass('error-empty');
                    } else if (result.error) {
                        if (result.error.name === 'FIELD_EMPTY') {
                            inputContainer.removeClass('error-invalid').addClass('error-empty');
                        } else {
                            inputContainer.addClass('error-invalid').removeClass('error-empty');
                        }
                    }
                });
            });
        },
    });
});
