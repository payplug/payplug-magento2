/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'jquery',
    'ko',
    'mage/translate',
    'mage/url',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'Payplug_Payments/js/action/redirect-on-success'
], function ($, ko, $t, url, Component, fullScreenLoader, quote, priceUtils, redirectOnSuccessAction) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_oney'
        },
        redirectAfterPlaceOrder: false,
        isLoading: false,
        isOneyPlaceOrderDisabled: ko.observable(false),
        scheduleLabels: [
            {days: 30, label: $t('In 30 days')},
            {days: 60, label: $t('In 60 days')},
            {days: 90, label: $t('In 90 days')},
        ],

        /**
         * Init component
         *
         * @return {Object}
         */
        initialize: function () {
            var self = this;

            this._super();

            quote.paymentMethod.subscribe(function (value) {
                self.isLoading = false;
                if (value && value.method === self.getCode()) {
                    self.updateOney();
                }
            });

            quote.shippingAddress.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateOney();
                }
            });

            quote.billingAddress.subscribe(function () {
                if (quote.billingAddress() !== null) {
                    if (self.getCode() === self.isChecked()) {
                        self.updateOney();
                    }
                }
            });

            quote.totals.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateOney();
                }
            });
            
            quote.shippingMethod.subscribe(function () {
                if (self.getCode() === self.isChecked()) {
                    self.updateOney();
                }
            });

            $('body').on('click', '.oneyPayment .oneyOption', function(e){
                e.preventDefault();
                self.selectOneyOption($(this));
            });

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
         * Fetches Oney payment details from the server and update the payment form with the schedule options.
         *
         * @returns {Boolean}
         */
        updateOney: function () {
            const self = this;

            if (self.isLoading) {
                return false;
            }

            self.updateLoading(true);
            this.isOneyPlaceOrderDisabled(true);

            try {
                $.ajax({
                    url: url.build('payplug_payments/oney/simulationCheckout'),
                    type: 'POST',
                    data: {
                        'amount': quote.totals()['base_grand_total'],
                        'billingCountry': quote.billingAddress() ? quote.billingAddress().countryId : null,
                        'shippingCountry': quote.shippingAddress() ? quote.shippingAddress().countryId : null,
                        'isVirtual': quote.isVirtual() ? 1 : 0,
                        'paymentMethod': self.getCode()
                    }
                }).done(function (response) {
                    if (response.success && response.data.success) {
                        self.processOneySuccess(response.data);
                    } else {
                        self.processOneyFailure(response.data.message);
                    }
                    
                    self.updateLoading(false);
                }).fail(function () {
                    self.processOneyFailure($t('An error occurred while getting Oney details. Please try again.'));
                    self.updateLoading(false);
                });

                return true;
            } catch (e) {
                self.updateLoading(false);
                return false;
            }
        },

        /**
         * Update the loading state of the payment method.
         *
         * @param {Boolean} isLoading
         * @returns void
         */
        updateLoading: function (isLoading) {
            this.isLoading = isLoading;

            if (isLoading) {
                fullScreenLoader.startLoader();
            } else {
                fullScreenLoader.stopLoader();
            }
        },
        
        /**
         * Handles the failure of the Oney payment process.
         *
         * @param {String} message
         */
        processOneyFailure: function (message) {
            this.isOneyPlaceOrderDisabled(true);
            this.updateOneyLogo(this.getConfiguration().logo_ko);
            this.updateOneyError(message);
            this.updatePlaceOrderButtonLabel(null);
            this.getOneyContainer().find('.oneyPayment').removeClass('oneyPayment-open').html('');
        },
        
        /**
         * Handles the success of the Oney payment process.
         *
         * @param {Object} oneySimulationResult
         * @returns void
         */
        processOneySuccess: function (oneySimulationResult) {
            this.isOneyPlaceOrderDisabled(false);
            this.updateOneyLogo(this.getConfiguration().logo);
            this.updateOneyError('');
            this.updatePlaceOrderButtonLabel('3x');

            var optionTypeToSelect = null;
            var oneyPayment = this.getOneyContainer().find('.oneyPayment');
            var previouslySelectedOption = oneyPayment.find('input[type="radio"]:checked');

            if (previouslySelectedOption.length > 0 && previouslySelectedOption.val() !== '') {
                optionTypeToSelect = previouslySelectedOption.val();
            }

            this.buildOneyDetail(oneySimulationResult);
            oneyPayment.addClass('oneyPayment-open');

            var optionToSelect = $(oneyPayment.find('.oneyOption')[0]);

            if (optionTypeToSelect !== null) {
                optionToSelect = $(oneyPayment.find('input[type="radio"][value="' + optionTypeToSelect + '"]').closest('.oneyOption'));
            }

            this.selectOneyOption(optionToSelect);
        },
        
        /**
         * Handles the selection of an Oney option.
         *
         * @param {jQuery} option
         * @returns void
         */
        selectOneyOption: function (option) {
            var oneyPayment = this.getOneyContainer().find('.oneyPayment');

            oneyPayment.find('.oneyOption').removeClass('oneyOption-selected');
            option.addClass('oneyOption-selected');

            oneyPayment.find('input[type="radio"]').prop('checked', false);
            option.find('input[type="radio"]').prop('checked', true);

            this.updatePlaceOrderButtonLabel(option.data('type'));
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
         * Updates the Oney error message.
         *
         * @param {String} message
         * @returns void
         */
        updateOneyError: function (message) {
            let oneyError = this.getOneyContainer().find('.oney-checkout-error');
            if (message === '') {
                oneyError.removeClass('active');
            } else {
                oneyError.addClass('active');
            }
            oneyError.html(message);
        },
        
        /**
         * Updates the label of the Place Order button with the given type.
         *
         * @param {String|null} type
         * @returns void
         */
        updatePlaceOrderButtonLabel: function (type) {
            var label;

            if (type === null) {
                label = $t('Place Order');
            } else {
                label = this.getPaymentTypeLabel().replace('%1', type);
            }

            this.getOneyContainer().find('.oney-submit-button').find('span').html(label);
        },
        
        /**
         * Builds the Oney payment detail content, based on the given simulation result.
         * 
         * @param {Object} oneySimulationResult
         * @returns void
         */
        buildOneyDetail: function (oneySimulationResult) {
            var tmpDiv = $('<div></div>');
            var optionsWrapper = $('<div></div>').addClass('oneyOption_wrapper');
            var label = this.getPaymentTypeLabel();

            for (var i = 0; i < oneySimulationResult.options.length; i++) {
                var optionData = oneySimulationResult.options[i];
                var type = optionData.type;
                var option = $('<label></label>', { 'data-type': type }).addClass('oneyOption oneyOption-' + type);

                // Title
                var title = $('<div></div>').addClass('oneyOption_title');
                title.append($('<span></span>').addClass('oneyOption_logo oneyLogo oneyLogo-' + type));
                title.append(label.replace('%1', type));
                option.append(title);

                // Option detail
                if (typeof optionData.schedules !== 'undefined' && optionData.schedules.length > 0) {
                    var detail = $('<div></div>').addClass('oneyOption_prices');
                    var list = $('<ul></ul>').addClass('oneyOption_list');

                    var firstDeposit = $('<li></li>')
                        .append($('<span></span>').html($t('Total order amount')))
                        .append($('<span></span>').addClass('oneyOption_price').html(this.getFormattedPrice(optionData.first_deposit)));

                    list.append(firstDeposit);

                    var daysForPayment = 30;

                    for (var j = 0; j < optionData.schedules.length; j++) {
                        var scheduleData = optionData.schedules[j];

                        var schedule = $('<li></li>')
                            .append($('<span></span>').html(this.getScheduleLabel(daysForPayment)))
                            .append($('<span></span>').addClass('oneyOption_price').html(this.getFormattedPrice(scheduleData.amount)));

                        list.append(schedule);
                        daysForPayment += 30;
                    }

                    var totalAmount = $('<li></li>')
                        .append($('<span></span>').html($t('Total cost')))
                        .append($('<span></span>').addClass('oneyOption_price').html(this.getFormattedPrice(optionData.total_amount)));

                    list.append(totalAmount);
                    detail.append(list);
                    option.append(detail);
                }

                // Option radio button
                var radio = $('<div></div>').addClass('oneyOption_radio').append(
                    $('<div></div>').addClass('radio').append(
                        $('<span></span>').append(
                            $('<input>').attr('type', 'radio').attr('name', 'oney_type').val(type)
                        )
                    )
                );

                option.append(radio);
                optionsWrapper.append(option);
            }

            tmpDiv.append(optionsWrapper);

            if (this.getConfiguration().more_info_url) {
                tmpDiv.append(
                    $('<a></a>')
                        .attr('class', 'more-info')
                        .attr('href', this.getConfiguration().more_info_url)
                        .attr('target', '_blank')
                        .html($t('More info'))
                );
            }

            this.getOneyContainer().find('.oneyPayment').html(tmpDiv.html());
        },
        
        /**
         * Retrieves the schedule label corresponding to the given number of days.
         *
         * @param {Number} days
         * @returns {String|null}
         */
        getScheduleLabel: function (days) {
            let label = null;
            
            this.scheduleLabels.forEach(function(item) {
                if (item.days === days) {
                    label = item.label;

                    return false;
                }
            });

            return label;
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

            var oneyOption = this.getOneyContainer().find('.oneyPayment').find('input[type="radio"]:checked');
            if (oneyOption.length > 0 && oneyOption.val() !== '') {
                parentData['additional_data'] = {
                    'payplug_payments_oney_option': oneyOption.val()
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
            let mentionClass = 'prepaid-card-mention';

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
        getOneyContainer: function() {
            return $('[data-oney-container="' + this.getCode() + '"]');
        },
        
        /**
         * Indicates whether the current Oney payment is for the Italian market.
         *
         * @returns {Boolean}
         */
        isOneyItalian: function() {
            return this.getConfiguration().is_italian;
        },
        
        /**
         * Returns the configuration object associated with the current payment method.
         * 
         * @returns {Object}
         */
        getConfiguration: function() {
            return {};
        },
        
        /**
         * Returns the label associated with the given payment type.
         *
         * @returns {String}
         */
        getPaymentTypeLabel: function() {
            return '';
        }
    });
});
