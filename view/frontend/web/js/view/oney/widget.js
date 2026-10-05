/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'jquery',
    'Payplug_Payments/js/model/oney-widget-loader'
], function ($, oneyWidgetLoader) {
    'use strict';

    $.widget('payplug.oneyWidget', {
        options: {
            loader_url: '',
            country: '',
            language: '',
            merchant_guid: '',
            business_transaction_codes: {},
            amount: 0,
            min_amount: 0,
            max_amount: 0,
            is_product: false,
            is_enabled: false,
            buttonSelector: '.oneyCta_button',
            disabledClass: 'oneyCta_button-disabled',
            loadingClass: 'oneyCta_button-loading',
            errorSelector: '.oneyCta_error',
            noticeSelector: '.oneyCta_notice',
            qtySelector: '[name="qty"]',
            priceBoxSelector: '.product-info-main .price-box',
            unitPriceSelector: '.price-wrapper[data-price-type="finalPrice"]'
        },

        /**
         * @private
         */
        _create: function () {
            this.button = this.element.find(this.options.buttonSelector);
            this.error = this.element.find(this.options.errorSelector);
            this.notice = this.element.find(this.options.noticeSelector);
            this.unitPrice = this._getInitialUnitPrice();
            this.isOpening = false;

            this._bind();
            this._refreshEligibility();
        },

        /**
         * @private
         */
        _bind: function () {
            var self = this;

            this._on(this.button, {
                click: '_onClick'
            });

            if (!this.options.is_product) {
                this._bindCartTotals();

                return;
            }

            $(document).on('input change', this.options.qtySelector, function () {
                self._refreshEligibility();
            });

            $(document).on('priceUpdated', this.options.priceBoxSelector, function (event, displayPrices) {
                self._updateUnitPrice(displayPrices);
                self._refreshEligibility();
            });
        },

        /**
         * Get amount to simulate: product price times quantity, or cart total
         *
         * @returns {Number}
         */
        getAmount: function () {
            var amount = parseFloat(this.options.amount) || 0,
                qty;

            if (!this.options.is_product) {
                return amount;
            }

            qty = parseFloat($(this.options.qtySelector).val());
            if (isNaN(qty) || qty <= 0) {
                qty = 1;
            }

            return Math.round(this.unitPrice * qty * 100) / 100;
        },

        /**
         * Is amount eligible to Oney
         *
         * @param {Number} amount
         * @returns {Boolean}
         */
        isEligible: function (amount) {
            if (!this.options.is_enabled) {
                return false;
            }

            return amount >= parseFloat(this.options.min_amount) && amount <= parseFloat(this.options.max_amount);
        },

        /**
         * Open the official Oney simulation popin
         *
         * @param {Number} amount
         */
        openSimulation: function (amount) {
            var self = this;

            if (this.isOpening) {
                return;
            }

            this.isOpening = true;
            this.notice.prop('hidden', true);
            this._setLoading(true);

            oneyWidgetLoader.load(this.options.loader_url).done(function (oneyMerchantApp) {
                oneyMerchantApp.loadSimulationPopin({
                    options: self._buildOptions(amount)
                });
            }).fail(function (error) {
                self._handleError(error);
            }).always(function () {
                self.isOpening = false;
                self._setLoading(false);
            });
        },

        /**
         * Follow cart totals updated without page reload (e.g. shipping estimation)
         *
         * @private
         */
        _bindCartTotals: function () {
            var self = this;

            if (!window.checkoutConfig) {
                return;
            }

            require(['Magento_Checkout/js/model/quote'], function (quote) {
                quote.totals.subscribe(function (totals) {
                    if (!totals) {
                        return;
                    }

                    self.option('amount', parseFloat(totals['base_grand_total']) || 0);
                    self._refreshEligibility();
                });
            });
        },

        /**
         * Get unit price from the final price rendered on page load, falling back on server price
         *
         * @returns {Number}
         * @private
         */
        _getInitialUnitPrice: function () {
            var wrapper = $(this.options.priceBoxSelector).find(this.options.unitPriceSelector).first(),
                unitPrice = parseFloat(wrapper.attr('data-price-amount'));

            if (isNaN(unitPrice)) {
                return parseFloat(this.options.amount) || 0;
            }

            return unitPrice;
        },

        /**
         * Update unit price with the final price computed by the price box (selected variant, custom options)
         *
         * @param {Object} displayPrices
         * @private
         */
        _updateUnitPrice: function (displayPrices) {
            var finalPrice = displayPrices ? displayPrices.finalPrice : null,
                amount = finalPrice ? parseFloat(finalPrice.amount) : NaN;

            if (isNaN(amount)) {
                return;
            }

            this.unitPrice = Object.values(finalPrice.adjustments || {}).reduce(function (total, adjustment) {
                return total + (parseFloat(adjustment) || 0);
            }, amount);
        },

        /**
         * Build Oney widget options
         *
         * @param {Number} amount
         * @returns {Object}
         * @private
         */
        _buildOptions: function (amount) {
            var self = this;

            return {
                country: this.options.country,
                language: this.options.language,
                merchant_guid: this.options.merchant_guid,
                payment_amount: amount,
                filter_by: 'business_transaction_codes',
                business_transaction_codes: Object.values(this.options.business_transaction_codes),
                errorCallback: function (status, response) {
                    self._handleError(status + ' - ' + response);
                }
            };
        },

        /**
         * @param {Event} event
         * @private
         */
        _onClick: function (event) {
            var amount = this.getAmount();

            event.preventDefault();

            if (!this.isEligible(amount)) {
                this.notice.prop('hidden', true);
                this.error.prop('hidden', !this.error.prop('hidden') || this.error.text().trim() === '');

                return;
            }

            this.error.prop('hidden', true);
            this.openSimulation(amount);
        },

        /**
         * @private
         */
        _refreshEligibility: function () {
            var isEligible = this.isEligible(this.getAmount());

            this._setDisabled(!isEligible);

            if (isEligible) {
                this.error.prop('hidden', true);
            }
        },

        /**
         * @param {Boolean} isDisabled
         * @private
         */
        _setDisabled: function (isDisabled) {
            this.button
                .toggleClass(this.options.disabledClass, isDisabled)
                .attr('aria-disabled', isDisabled ? 'true' : 'false');
        },

        /**
         * @param {Boolean} isLoading
         * @private
         */
        _setLoading: function (isLoading) {
            this.button
                .toggleClass(this.options.loadingClass, isLoading)
                .attr('aria-busy', isLoading ? 'true' : 'false');
        },

        /**
         * Oney widget errors must never break the page: log and tell the customer the simulation is unavailable
         *
         * @param {*} error
         * @private
         */
        _handleError: function (error) {
            if (window.console && window.console.warn) {
                window.console.warn('Oney widget error', error);
            }

            this.notice.prop('hidden', false);
        }
    });

    return $.payplug.oneyWidget;
});
