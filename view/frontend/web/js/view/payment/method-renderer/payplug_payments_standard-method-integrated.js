/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'jquery',
    'Payplug_Payments/js/view/payment/method-renderer/payplug_payments_standard-method',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/url',
    'mage/translate',
    'payplugIntegrated'
], function (
    $,
    Component,
    fullScreenLoader,
    url,
    $t,
    payplug
) {
    'use strict';

    return Component.extend({
        integratedApi: null,
        integratedForm: null,
        /**
         * Init payment form
         * @returns {Boolean}
         */
        initPaymentForm: function () {
            const self = this;

            if (!this.isIntegrated()) {
                return false;
            }

            if (this.integratedApi !== null) {
                return false;
            }

            this.integratedApi = new payplug.IntegratedPayment(window.checkoutConfig.payment.payplug_payments_standard.is_sandbox);
            this.integratedApi.setDisplayMode3ds(payplug.DisplayMode3ds.REDIRECT);
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
                data.element.onChange(function (result) {
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

            return true;
        },
        /**
         * Place order
         * @returns {Object}
         */
        placeOrder: function (data, event) {
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
         * Process After Standard Success
         * @returns {void}
         */
        afterStandardSuccess: function (response) {
            /**
             * Need to pay then check, with Payplug Integrated Payment
             */

            let selectedScheme = payplug.Scheme.AUTO;
            let selectedCardType = $('.payment-form [name="scheme"]:checked');

            if (selectedCardType.length > 0) {
                selectedCardType = selectedCardType.data('card-type');

                if (payplug.Scheme[selectedCardType]) {
                    selectedScheme = payplug.Scheme[selectedCardType];
                }
            }

            let saveCard = window.checkoutConfig.payment.payplug_payments_standard.is_one_click && $('[name="save_card"]').is(':checked');

            const paymentReturnUrl = url.build(this.paymentReturn);
            const checkPaymentUrl = url.build(this.checkPayment);

            this.integratedApi.pay(response.payment_id, selectedScheme, {save_card: saveCard});
            this.integratedApi.onCompleted(function () {
                $.ajax({
                    url: checkPaymentUrl,
                    type: 'GET',
                    dataType: 'json',
                    data: { payment_id: response.payment_id },
                    success: function (res) {
                        if (res.error === true) {
                            window.location.replace(paymentReturnUrl + '?failure_message=' + res.message);
                        } else {
                            window.location.replace(paymentReturnUrl);
                        }
                    },
                    complete: function () {
                        fullScreenLoader.stopLoader();
                    }
                });
            });
        }
    });
});
