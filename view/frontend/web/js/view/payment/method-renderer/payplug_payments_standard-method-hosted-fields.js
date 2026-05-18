/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'jquery',
    'ko',
    'Payplug_Payments/js/view/payment/method-renderer/payplug_payments_standard-method',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Ui/js/model/messageList',
    'mage/translate',
    'payplugHostedFields'
], function (
    $,
    ko,
    Component,
    fullScreenLoader,
    messageList,
    $t
) {
    'use strict';

    return Component.extend({
        hostedFieldsApi: null,
        hostedFieldsForm: null,
        hostedFieldsToken: null,
        hostedFieldsSelectedBrand: null,
        availableLanguages: ['fr', 'en', 'de', 'es', 'it', 'nl', 'zh', 'ru', 'pt', 'sk'],
        apiKeyId: window.checkoutConfig.payment.payplug_payments_standard.hosted_fields_api_key_id,
        apiKey: window.checkoutConfig.payment.payplug_payments_standard.hosted_fields_api_key,
        locale: window.checkoutConfig.payment.payplug_payments_standard.locale_code,
        inputStyles: {
            input: {
                "font-size": "14px",
                "line-height": "38px",
            },
            '::placeholder': {
                'letter-spacing': '0.1em'
            },
            ':invalid':  {
                "color": "red"
            }
        },
        /**
         * Init component
         *
         * @return {Object}
         */
        initialize: function () {
            this._super();

            this.isHostedFieldsPayment(true);

            $('body').on('change', '[name="payment[payplug_payments_standard][customer_card_id]"]', function () {
                let customerCard = $('.payplug-payments-customer-card:checked');

                if (customerCard.length > 0 && customerCard.data('card-id') !== '') {
                    this.showFullForm(false);
                } else {
                    this.showFullForm(true);
                }

                this.initPaymentForm();
            }.bind(this));

            if (this.getInitialSelectedCard()) {
                this.showFullForm(false);
            }
        },
        /**
         * Init payment form
         * @returns {Boolean}
         */
        initPaymentForm: function () {
            if (!this.isIntegrated()) {
                return false;
            }

            if (this.hostedFieldsApi && this.hostedFieldsApi.dispose) {
                this.hostedFieldsApi.dispose();
            }

            let lang = this.locale.split('_')[0];

            if (!this.availableLanguages.includes(lang)) {
                lang = 'fr';
            }

            let hostedFieldsConfig = {
                key: {
                    id: this.apiKeyId,
                    value: this.apiKey,
                },
                theme: {
                    mode: 'light'
                },
                fields: {
                    cryptogram: {
                        id: 'cvv-input-container',
                        placeholder: "CVV",
                        style: this.inputStyles
                    }
                },
                location: lang,
            };

            if (this.showFullForm() === true) {
                Object.assign(hostedFieldsConfig.fields, {
                    brand: {
                        id: 'brand-container',
                        version: 2,
                        useInlineSelection: false,
                        isCbPreferredNetwork: true
                    },
                    card: {
                        id: 'pan-input-container',
                        placeholder: '•••• •••• •••• ••••',
                        enableAutospacing: true,
                        style: this.inputStyles
                    },
                    expiry: {
                        id: 'exp-input-container',
                        placeholder: 'MM/YY',
                        style: this.inputStyles
                    }
                });
            }

            this.hostedFieldsApi = dalenys.hostedFields(hostedFieldsConfig);
            this.hostedFieldsApi.load();

            return true;
        },
        /**
         * Place order
         * @returns {Object}
         */
        placeOrder: function (data, event) {
            if (this.isIntegrated() === false) {
                this._super(data, event);
                return;
            }

            let self = this;
            let original = this._super.bind(this);

            fullScreenLoader.startLoader();

            this.hostedFieldsApi.createToken(function (result) {
                fullScreenLoader.stopLoader();

                if (result.execCode !== '0000') {
                    messageList.addErrorMessage({
                        message: $t('An error occurred while processing your form. Please try again later.')
                    });
                    return false;
                }

                self.hostedFieldsToken = result.hfToken;

                self.hostedFieldsSelectedBrand = self.showFullForm() === true ? result.selectedBrand : null;
                original(data, event);
            });
        },
        /**
         * Get payment method data
         */
        getData: function () {
            let data = this._super();

            let saveCard = window.checkoutConfig.payment.payplug_payments_standard.is_one_click && $('[name="save_card"]').is(':checked');

            data.additional_data = {
                ...(data.additional_data || {}),
                payplug_hosted_fields_payment: true,
                payplug_hosted_fields_token: this.hostedFieldsToken,
                payplug_hosted_fields_brand: this.hostedFieldsSelectedBrand,
                payplug_hosted_fields_save_card: saveCard
            };

            return data;
        },
    });
});
