/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'Payplug_Payments/js/view/payment/method-renderer/payplug_payments_standard-method',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Ui/js/model/messageList',
    'mage/translate',
    'payplugHostedFields'
], function (
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

        /**
         * Init payment form
         * @returns {Boolean}
         */
        initPaymentForm: function () {
            if (!this.isIntegrated()) {
                return false;
            }

            if (this.hostedFieldsApi !== null) {
                return false;
            }

            const apiKeyId = window.checkoutConfig.payment.payplug_payments_standard.hosted_fields_api_key_id;
            const apiKey = window.checkoutConfig.payment.payplug_payments_standard.hosted_fields_api_key;
            const locale = window.checkoutConfig.payment.payplug_payments_standard.locale_code;
            let lang = locale.split('_')[0];

            if (!this.availableLanguages.includes(lang)) {
                lang = 'fr';
            }

            const inputStyles = {
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
            }

            this.hostedFieldsApi = dalenys.hostedFields({
                key: {
                    id: apiKeyId,
                    value: apiKey,
                },
                theme: {
                    mode: 'light'
                },
                fields: {
                    brand: {
                        id: 'brand-container',
                        version: 2,
                        useInlineSelection: false,
                        isCbPreferredNetwork: true
                    },
                    card: {
                        id: 'pan-input-container',
                        placeholder: "•••• •••• •••• ••••",
                        enableAutospacing: true,
                        style: inputStyles
                    },
                    expiry: {
                        id: 'exp-input-container',
                        placeholder: "MM/YY",
                        style: inputStyles
                    },
                    cryptogram: {
                        id: 'cvv-input-container',
                        placeholder: "CVV",
                        style: inputStyles
                    },
                },
                location: lang,
            });

            this.hostedFieldsApi.load();

            return true;
        },
        /**
         * Place order
         * @returns {Object}
         */
        placeOrder: function (data, event) {
            if (this.isIntegrated() !== true || this.getSelectedCardId() !== '') {
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
                self.hostedFieldsSelectedBrand = result.selectedBrand;
                original(data, event);
            });
        },
        /**
         * Get payment method data
         */
        getData: function () {
            let data = this._super();

            data.additional_data = {
                payplug_hosted_fields_payment: true,
                payplug_hosted_fields_token: this.hostedFieldsToken,
                payplug_hosted_fields_brand: this.hostedFieldsSelectedBrand,
            }

            return data;
        },
    });
});
