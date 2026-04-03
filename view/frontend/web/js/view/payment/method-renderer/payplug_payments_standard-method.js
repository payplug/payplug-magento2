/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'jquery',
    'ko',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/quote',
    'Payplug_Payments/js/action/redirect-on-success',
    'Payplug_Payments/js/action/lightbox-on-success',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/url'
], function (
    $,
    ko,
    Component,
    customerData,
    quote,
    redirectOnSuccessAction,
    lightboxOnSuccessAction,
    fullScreenLoader,
    url
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_standard',
            tracks: { cards: true }
        },
        paymentReturn: 'payplug_payments/payment/paymentReturn',
        checkPayment: 'payplug_payments/payment/checkPayment',
        standard: 'payplug_payments/payment/standard',
        redirectAfterPlaceOrder: false,
        cards: [],
        sessionCardId: 'payplug-payments-card-id',
        isIntegratedPayment:  ko.observable(true),
        isHostedFieldsPayment:  ko.observable(false),
        canDisplayPaymentForm: ko.observable(false),
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
         * Init component
         *
         * @return {Object}
         */
        initialize: function () {
            this._super();
            this.loadCards();

            const isHostedFieldsActive = window.checkoutConfig.payment.payplug_payments_standard.is_hosted_fields_active;

            if (isHostedFieldsActive) {
                this.isIntegratedPayment(false);
                this.isHostedFieldsPayment(true);
            }

            $('body').on('change', '[name="payment[payplug_payments_standard][customer_card_id]"]', function () {
                var customerCard = $('.payplug-payments-customer-card:checked');

                if (customerCard.length > 0 && customerCard.data('card-id') === '') {
                    this.canDisplayPaymentForm(true);
                } else {
                    this.canDisplayPaymentForm(false);
                }
            }.bind(this));

            if (!this.getInitialSelectedCard()) {
                this.canDisplayPaymentForm(true);
            }

            return this;
        },

        /**
         * Init observable variables
         * @return {Object}
         */
        initObservable: function () {
            this._super();

            this.isActive = ko.computed(function () {
                return this.getCode() === this.isChecked() && '_active';
            }, this);

            return this;
        },

        /**
         * Get card logo
         * @returns {String}
         */
        getCardLogo: function () {
            return window.checkoutConfig.payment.payplug_payments_standard.logo;
        },

        /**
         * Get cards
         * @returns {Object}
         */
        getCards: function () {
            return this.cards;
        },

        /**
         * Can display cards in title
         * @returns {Boolean}
         */
        canDisplayCardsInTitle: function () {
            return window.checkoutConfig.payment.payplug_payments_standard.display_cards_in_container === false &&
                this.canDisplayCards();
        },

        /**
         * Can display cards in container
         * @returns {Boolean}
         */
        canDisplayCardsInContainer: function () {
            return window.checkoutConfig.payment.payplug_payments_standard.display_cards_in_container === true &&
                this.canDisplayCards();
        },

        /**
         * Can display cards
         * @returns {Boolean}
         */
        canDisplayCards: function () {
            return window.checkoutConfig.payment.payplug_payments_standard.is_one_click && this.cards.length > 0;
        },

        /**
         * Load cards
         */
        loadCards: function () {
            if (window.checkoutConfig.payment.payplug_payments_standard.is_one_click) {
                const self = this;
                const cacheKey = 'payplug-payments-cards';

                customerData
                    .reload([cacheKey], false)
                    .done(function (jqXHR) {
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
        getInitialSelectedCard: function () {
            if (sessionStorage.getItem(this.sessionCardId) !== null) {
                return sessionStorage.getItem(this.sessionCardId);
            }

            return window.checkoutConfig.payment.payplug_payments_standard.selected_card_id;
        },

        /**
         * Is card disabled
         * @returns {Boolean}
         */
        isCardDisabled: function () {
            const selectedMethod = this.getSelectedMethod();
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
        selectCard: function (data) {
            this.selectPaymentMethod();
            $('.payplug-payments-error').hide();
            sessionStorage.setItem(this.sessionCardId, data.id);
            return true;
        },

        /**
         * Get selected method
         * @returns {String}
         */
        getSelectedMethod: function () {
            return quote.paymentMethod() ? quote.paymentMethod().method : null;
        },

        /**
         * Select payment method
         * @returns {Object}
         */
        selectPaymentMethod: function () {
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
        getSelectedCardId: function () {
            var customerCard = $('.payplug-payments-customer-card:checked');
            if (customerCard.length > 0 && customerCard.data('card-id')) {
                return customerCard.data('card-id');
            }

            return '';
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
         * After place order
         * Triggered after a payment has been placed.
         *
         * @returns void
         */
        afterPlaceOrder: function () {
            const self = this;

            fullScreenLoader.startLoader();

            sessionStorage.removeItem(this.sessionCardId);

            if (this.getSelectedCardId() !== '') {
                redirectOnSuccessAction.execute();
                return;
            }

            if (!this.isIntegrated()) {
                if (window.checkoutConfig.payment.payplug_payments_standard.is_embedded) {
                    lightboxOnSuccessAction.execute();
                    return;
                }

                redirectOnSuccessAction.execute();
                return;
            }

            const standardUrl = url.build(this.standard) + '?should_redirect=0&has_payment_form=1';
            const paymentReturnUrl = url.build(this.paymentReturn);

            $.ajax({
                url: standardUrl,
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    console.log(response);
                    if (response.error === true) {
                        alert(response.message);
                        window.location.replace(response.url);
                        return;
                    }

                    if (!response.payment_id) {
                        window.location.replace(paymentReturnUrl);
                        return;
                    }

                    self.afterStandardSuccess(response);
                }
            });
        },
        /**
         * Process After Standard Success
         * @returns {void}
         */
        afterStandardSuccess: function (response) {
            /**
             * Default behavior
             */

            if (response.url && response.url_post_params) {
                const redirectForm = $('<form>', {
                    method: 'POST',
                    action: response.url
                });

                response.url_post_params.split('&').forEach(pair => {
                    const [key, value] = pair.split('=');
                    $('<input>', {
                        type: 'hidden',
                        name: decodeURIComponent(key),
                        value: decodeURIComponent(value || '')
                    }).appendTo(redirectForm);
                });

                redirectForm.appendTo('body').submit();
                return;
            }

            if (response.url) {
                window.location.replace(response.url);
                return;
            }

            const paymentReturnUrl = url.build(this.paymentReturn);
            window.location.replace(paymentReturnUrl);
        }
    });
});
