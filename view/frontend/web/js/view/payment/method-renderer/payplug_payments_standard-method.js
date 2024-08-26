/* @api */
define([
    'ko',
    'jquery',
    'mage/url',
    'mage/translate',
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/action/redirect-on-success',
    'Payplug_Payments/js/action/lightbox-on-success',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'payplugIntegrated'
], function (
    ko,
    $,
    url,
    $t,
    Component,
    redirectOnSuccessAction,
    lightboxOnSuccessAction,
    customerData,
    quote,
    fullScreenLoader,
    payplug
) {
    'use strict';

    const PAYPLUG_DOMAIN = "https://secure-qa.payplug.com";

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

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();
            this.loadCards();
            $('body').on('change', '[name="payment[payplug_payments_standard][customer_card_id]"]', function() {
                var customerCard = $('.payplug-payments-customer-card:checked');
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

        /**
         * After place order
         */
        afterPlaceOrder: function () {
            const self = this;

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

            $.ajax({
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
                            let selectedCardType = $('.form-integrated [name="scheme"]:checked');
                            if (selectedCardType.length > 0) {
                                selectedCardType = selectedCardType.data('card-type');
                                if (payplug.Scheme[selectedCardType]) {
                                    selectedScheme = payplug.Scheme[selectedCardType];
                                }
                            }
                            let saveCard = window.checkoutConfig.payment.payplug_payments_standard.is_one_click &&
                                $('[name="save_card"]').is(':checked');
                            self.integratedApi.pay(response.payment_id, selectedScheme, {save_card: saveCard});
                            self.integratedApi.onCompleted(function (event) {

                              $.ajax({
                                url: url.build('payplug_payments/payment/checkPayment'),
                                type: "GET",
                                dataType: 'json',
                                data: {payment_id: response.payment_id},
                                success: function (res) {

                                  fullScreenLoader.stopLoader();


                                  if(res.error === true){
                                    window.location.replace(url.build('payplug_payments/payment/cancel') + '?form_key=' + $.cookie('form_key'));
                                  }else{
                                    window.location.replace(url.build('payplug_payments/payment/paymentReturn'));
                                  }
                                }
                              });
                            });
                          fullScreenLoader.stopLoader();
                        } else {
                            window.location.replace(url.build('payplug_payments/payment/cancel') + '?form_key=' + $.cookie('form_key'));
                        }
                    }
                }
            });
        },

        /**
         * Get card logo
         * @returns {String}
         */
        getCardLogo: function() {
            return window.checkoutConfig.payment.payplug_payments_standard.logo;
        },

        /**
         * Get cards
         * @returns {Object}
         */
        getCards: function() {
            return this.cards;
        },

        /**
         * Can display cards in title
         * @returns {Boolean}
         */
        canDisplayCardsInTitle: function() {
            return window.checkoutConfig.payment.payplug_payments_standard.display_cards_in_container === false &&
                this.canDisplayCards();
        },

        /**
         * Can display cards in container
         * @returns {Boolean}
         */
        canDisplayCardsInContainer: function() {
            return window.checkoutConfig.payment.payplug_payments_standard.display_cards_in_container === true &&
                this.canDisplayCards();
        },

        /**
         * Can display cards
         * @returns {Boolean}
         */
        canDisplayCards: function() {
            return window.checkoutConfig.payment.payplug_payments_standard.is_one_click && this.cards.length > 0;
        },

        /**
         * Load cards
         */
        loadCards: function () {
            if (window.checkoutConfig.payment.payplug_payments_standard.is_one_click) {
                var cacheKey = 'payplug-payments-cards';
                var self = this;
                customerData.reload([cacheKey], false).done(function(jqXHR) {
                    self.cards = jqXHR[cacheKey].cards;
                });
            }
        },

        /**
         * Is card selected
         * @returns {Boolean}
         */
        isCardSelected: function (data) {
            if (data.id === this.getInitialSelectedCard()) {
                if (this.getSelectedMethod() === null) {
                    this.selectPaymentMethod();
                }
                return true;
            }
            return false;
        },

        /**
         * Get initial selected card
         * @returns {Number}
         */
        getInitialSelectedCard: function() {
            if (sessionStorage.getItem(this.sessionCardId) !== null) {
                return sessionStorage.getItem(this.sessionCardId);
            }

            return window.checkoutConfig.payment.payplug_payments_standard.selected_card_id;
        },

        /**
         * Is card disabled
         * @returns {Boolean}
         */
        isCardDisabled: function() {
            var selectedMethod = this.getSelectedMethod();
            return selectedMethod !== null && selectedMethod !== this.getCode();
        },

        /**
         * Get brand logo
         * @returns {Object}
         */
        getBrandLogo: function (brand) {
            const lowerCasedBrand = brand.toLowerCase();

            if (window.checkoutConfig.payment.payplug_payments_standard.brand_logos[lowerCasedBrand]) {
                return window.checkoutConfig.payment.payplug_payments_standard.brand_logos[lowerCasedBrand];
            }

            return window.checkoutConfig.payment.payplug_payments_standard.brand_logos.other;
        },

        /**
         * Get card template
         * @returns {String}
         */
        getCardTemplate: function () {
            return 'Payplug_Payments/payment/card';
        },

        /**
         * Select card
         * @returns {Boolean}
         */
        selectCard: function(data) {
            this.selectPaymentMethod();
            $('.payplug-payments-error').hide();
            sessionStorage.setItem(this.sessionCardId, data.id);
            return true;
        },

        /**
         * Get selected method
         * @returns {String}
         */
        getSelectedMethod: function() {
            return quote.paymentMethod() ? quote.paymentMethod().method : null;
        },

        /**
         * Select payment method
         * @returns {Object}
         */
        selectPaymentMethod: function() {
            $('.payplug-payments-customer-card').prop('disabled', false);
            return this._super();
        },

        /**
         * Validate
         * @returns {Boolean}
         */
        validate: function () {
            if ($('.payplug-payments-customer-card').length > 0 && $('.payplug-payments-customer-card:checked').length === 0) {
                $('.payplug-payments-error').show();
                return false;
            }

            return true;
        },

        /**
         * Get data
         * @returns {Object}
         */
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

        /**
         * Get selected card Id
         * @returns {String}
         */
        getSelectedCardId: function() {
            var customerCard = $('.payplug-payments-customer-card:checked');
            if (customerCard.length > 0 && customerCard.data('card-id')) {
                return customerCard.data('card-id');
            }

            return '';
        },

        /**
         * Place order
         * @returns {Object}
         */
        placeOrder: function(data, event) {
           if (this.isIntegrated() && this.getSelectedCardId() === '') {
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

        /**
         * Is integrated
         * @returns {Boolean}
         */
        isIntegrated: function () {
            return window.checkoutConfig.payment.payplug_payments_standard.is_integrated &&
                typeof window.checkoutConfig.payment.payplug_payments_standard.is_sandbox !== 'undefined';
        },

        /**
         * Init integrated form
         * @returns {Boolean}
         */
        initIntegratedForm: function() {
            const self = this;

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
                {default: this.inputStyle.default, placeholder: $t('Payplug Card Holder placeholder')}
            ), valid: false};

            this.integratedForm.pan = {element: this.integratedApi.cardNumber(
                document.querySelector('.pan-input-container'),
                {default: this.inputStyle.default, placeholder: $t('Payplug Card Number placeholder')}
            ), valid: false};

            this.integratedForm.cvv = {element: this.integratedApi.cvv(
                document.querySelector('.cvv-input-container'),
                {default: this.inputStyle.default, placeholder: $t('Payplug Card CVV placeholder')}
            ), valid: false};

            this.integratedForm.exp = {element: this.integratedApi.expiration(
                document.querySelector('.exp-input-container'),
                {default: this.inputStyle.default, placeholder: $t('Payplug Card Expiration placeholder')}
            ), valid: false};

            $.each(this.integratedForm, function (key, data) {
                data.element.onChange(function(result) {
                    self.integratedForm[key].valid = result.valid;
                    let inputContainer = $('.' + key + '-input-container');
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
