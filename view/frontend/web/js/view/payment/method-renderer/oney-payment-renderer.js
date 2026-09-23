/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'jquery',
    'ko',
    'mage/translate',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'Payplug_Payments/js/action/redirect-on-success',
    'Payplug_Payments/js/model/oney-widget-loader'
], function ($, ko, $t, Component, fullScreenLoader, quote, priceUtils, redirectOnSuccessAction, oneyWidgetLoader) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_oney',
            selectedOption: null,
            oneyErrorMessage: '',
            oneyNotice: ''
        },
        redirectAfterPlaceOrder: false,
        isOneyPlaceOrderDisabled: ko.observable(true),

        /**
         * Init component
         *
         * @return {Object}
         */
        initialize: function () {
            var self = this;

            this._super();

            quote.paymentMethod.subscribe(function (value) {
                if (value && value.method === self.getCode()) {
                    self.updateOney();
                }
            });

            quote.shippingAddress.subscribe(function () {
                if (self.isSelected()) {
                    self.updateOney();
                }
            });

            quote.billingAddress.subscribe(function () {
                if (quote.billingAddress() !== null && self.isSelected()) {
                    self.updateOney();
                }
            });

            quote.totals.subscribe(function () {
                if (self.isSelected()) {
                    self.updateOney();
                }
            });

            quote.shippingMethod.subscribe(function () {
                if (self.isSelected()) {
                    self.updateOney();
                }
            });

            this.selectedOption.subscribe(function () {
                self.renderWidget();
            });

            return this;
        },

        /**
         * Init observable variables
         * @return {Object}
         */
        initObservable: function () {
            this._super().observe(['selectedOption', 'oneyErrorMessage', 'oneyNotice']);

            this.isActive = ko.computed(function () {
                return this.getCode() === this.isChecked() && '_active';
            }, this);

            this.placeOrderLabel = ko.computed(function () {
                var type = this.selectedOption();

                return type ? this.getOptionLabel(type) : $t('Place Order');
            }, this);

            return this;
        },

        /**
         * Is this payment method currently selected
         *
         * @returns {Boolean}
         */
        isSelected: function () {
            return this.getCode() === this.isChecked();
        },

        /**
         * Refresh Oney once the form is rendered, when the method was already selected before (e.g. page reload).
         *
         * @returns void
         */
        afterWidgetRender: function () {
            if (this.isSelected()) {
                this.updateOney();
            }
        },

        /**
         * Triggered after a payment has been placed.
         *
         * @returns void
         */
        afterPlaceOrder: function () {
            fullScreenLoader.stopLoader();
            redirectOnSuccessAction.execute();
        },

        /**
         * Retrieve the payment logo set in the admin configuration
         *
         * @returns {String}
         */
        getPaymentLogo: function () {
            return this.getConfiguration().logo;
        },

        /**
         * Retrieve the official Oney widget configuration
         *
         * @returns {Object}
         */
        getWidgetConfig: function () {
            return this.getConfiguration().widget || {};
        },

        /**
         * Oney option types (3x, 4x) available for this payment method
         *
         * @returns {Array}
         */
        getOneyOptions: function () {
            return this.getWidgetConfig().options || [];
        },

        /**
         * Label of an Oney option
         *
         * @param {String} type
         * @returns {String}
         */
        getOptionLabel: function (type) {
            return this.getPaymentTypeLabel().replace('%1', type);
        },

        /**
         * Id of the container in which the Oney checkout section is rendered
         *
         * @returns {String}
         */
        getPlaceholderId: function () {
            return 'oney-checkout-placeholder-' + this.getCode();
        },

        /**
         * Amount to simulate
         *
         * @returns {Number}
         */
        getAmount: function () {
            var totals = quote.totals();

            return totals ? parseFloat(totals['base_grand_total']) || 0 : 0;
        },

        /**
         * Validate Oney eligibility and refresh the payment form accordingly.
         *
         * @returns void
         */
        updateOney: function () {
            var validation = this.validateOney();

            if (!validation.valid) {
                this.processOneyFailure(validation.message);

                return;
            }

            this.processOneySuccess();
        },

        /**
         * Client side Oney eligibility checks, mirrored server side when placing the order.
         *
         * @returns {Object}
         */
        validateOney: function () {
            var config = this.getWidgetConfig(),
                amount = this.getAmount(),
                billingAddress = quote.billingAddress(),
                shippingAddress = quote.shippingAddress(),
                billingCountry = billingAddress ? billingAddress.countryId : null,
                shippingCountry = !quote.isVirtual() && shippingAddress ? shippingAddress.countryId : null,
                country = billingCountry || shippingCountry,
                totals = quote.totals(),
                itemsQty = totals ? parseFloat(totals['items_qty']) || 0 : 0;

            if (billingCountry && shippingCountry && billingCountry !== shippingCountry) {
                return {
                    valid: false,
                    message: $t('Shipping and billing adresses must be both in the same country.')
                };
            }

            if (country && (config.allowed_countries || []).indexOf(country) === -1) {
                return {
                    valid: false,
                    message: $t('Unavailable for the specified country')
                };
            }

            if (config.max_items && itemsQty >= config.max_items) {
                return {
                    valid: false,
                    message: $t('To pay with Oney, your cart must contain less than %1 items.')
                        .replace('%1', config.max_items)
                };
            }

            if (amount < parseFloat(config.min_amount) || amount > parseFloat(config.max_amount)) {
                return {
                    valid: false,
                    message: $t('To pay with Oney, the total amount of your cart must be between %1 and %2.')
                        .replace('%1', this.getFormattedPrice(config.min_amount))
                        .replace('%2', this.getFormattedPrice(config.max_amount))
                };
            }

            return {
                valid: true
            };
        },

        /**
         * Handles an Oney eligibility failure.
         *
         * @param {String} message
         */
        processOneyFailure: function (message) {
            this.isOneyPlaceOrderDisabled(true);
            this.updateOneyLogo(this.getConfiguration().logo_ko);
            this.oneyErrorMessage(message);
            this.oneyNotice('');
            this.selectedOption(null);
            this.clearWidget();
        },

        /**
         * Handles an Oney eligibility success.
         *
         * @returns void
         */
        processOneySuccess: function () {
            var options = this.getOneyOptions();

            this.isOneyPlaceOrderDisabled(false);
            this.updateOneyLogo(this.getConfiguration().logo);
            this.oneyErrorMessage('');

            if (!this.selectedOption() && options.length > 0) {
                this.selectedOption(options[0]);

                return;
            }

            this.renderWidget();
        },

        /**
         * Render the official Oney checkout section for the selected option.
         *
         * @returns void
         */
        renderWidget: function () {
            var self = this,
                config = this.getWidgetConfig(),
                type = this.selectedOption(),
                code = type ? (config.business_transaction_codes || {})[type] : null;

            if (!type) {
                return;
            }

            if (!config.is_enabled || !code) {
                this.showWidgetUnavailable();

                return;
            }

            this.oneyNotice('');

            oneyWidgetLoader.load(config.loader_url).done(function (oneyMerchantApp) {
                if (self.selectedOption() !== type) {
                    return;
                }

                oneyMerchantApp.loadCheckoutSection({
                    options: {
                        country: config.country,
                        language: config.language,
                        merchant_guid: config.merchant_guid,
                        payment_amount: self.getAmount(),
                        filter_by: 'business_transaction_code',
                        business_transaction_code: code,
                        checkout_placeholder: '#' + self.getPlaceholderId(),
                        errorCallback: function (status, response) {
                            self.handleWidgetError(status + ' - ' + response);
                        }
                    }
                });
            }).fail(function (error) {
                self.handleWidgetError(error);
            });
        },

        /**
         * Oney widget errors must never block the checkout: log, clear the section and show a notice.
         *
         * @param {*} error
         */
        handleWidgetError: function (error) {
            if (window.console && window.console.warn) {
                window.console.warn('Oney widget error', error);
            }

            this.showWidgetUnavailable();
        },

        /**
         * Show a non blocking notice when the Oney schedule cannot be displayed.
         *
         * @returns void
         */
        showWidgetUnavailable: function () {
            this.clearWidget();
            this.oneyNotice($t('Your payment schedule simulation is temporarily unavailable. ' +
                'You will find this information at the payment stage.'));
        },

        /**
         * Empty the Oney checkout section container.
         *
         * @returns void
         */
        clearWidget: function () {
            $('#' + this.getPlaceholderId()).empty();
        },

        /**
         * Updates the Oney logo.
         *
         * @param {String} logo
         * @returns void
         */
        updateOneyLogo: function (logo) {
            this.getOneyContainer().find('.oney-logo-checkout').attr('src', logo);
        },

        /**
         * Returns the given price, formatted according to the current quote's price format.
         *
         * @param {Number} price
         * @returns {String}
         */
        getFormattedPrice: function (price) {
            return priceUtils.formatPrice(price, quote.getPriceFormat());
        },

        /**
         * Retrieves the payment data, including additional data for the selected Oney option.
         *
         * @returns {Object}
         */
        getData: function () {
            var parentData = this._super();

            if (this.selectedOption()) {
                parentData['additional_data'] = {
                    'payplug_payments_oney_option': this.selectedOption()
                };
            }

            return parentData;
        },

        /**
         * Determines the CSS class to apply for prepaid card mentions.
         *
         * @returns {String}
         */
        getPrepaidMentionClass: function () {
            var mentionClass = 'prepaid-card-mention';

            if (/^it/.test(navigator.language)) {
                mentionClass += ' visible';
            }

            return mentionClass;
        },

        /**
         * Retrieves the jQuery object representing the Oney container element.
         *
         * @returns {jQuery}
         */
        getOneyContainer: function () {
            return $('[data-oney-container="' + this.getCode() + '"]');
        },

        /**
         * Indicates whether the current Oney payment is for the Italian market.
         *
         * @returns {Boolean}
         */
        isOneyItalian: function () {
            return this.getConfiguration().is_italian;
        },

        /**
         * Returns the configuration object associated with the current payment method.
         *
         * @returns {Object}
         */
        getConfiguration: function () {
            return {};
        },

        /**
         * Returns the label associated with the given payment type.
         *
         * @returns {String}
         */
        getPaymentTypeLabel: function () {
            return '';
        }
    });
});
