/* @api */
define([
    'Magento_Checkout/js/view/payment/default',
    'Payplug_Payments/js/action/redirect-on-success',
    'Magento_Checkout/js/model/full-screen-loader',
    'jquery',
    'Magento_Checkout/js/model/quote',
    'mage/url',
    'Magento_Catalog/js/price-utils',
    'ko',
    'mage/translate'
], function (Component, redirectOnSuccessAction, fullScreenLoader, $, quote, urlBuilder, priceUtils, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Payplug_Payments/payment/payplug_payments_oney'
        },

        redirectAfterPlaceOrder: false,
        isLoading: false,
        isOneyPlaceOrderDisabled: ko.observable(false),

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
        afterPlaceOrder: function () {
            fullScreenLoader.stopLoader();
            redirectOnSuccessAction.execute();
        },
        getPaymentLogo: function() {
            return this.getConfiguration().logo;
        },
        updateOney: function() {
            var self = this;
            if (self.isLoading) {
                return false;
            }
            self.updateLoading(true);
            this.isOneyPlaceOrderDisabled(true);
            try {
                $.ajax({
                    url: urlBuilder.build('payplug_payments/oney/simulationCheckout'),
                    type: "POST",
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
                }).fail(function (response) {
                    self.processOneyFailure($.mage.__('An error occurred while getting Oney details. Please try again.'));
                    self.updateLoading(false);
                });

                return true;
            } catch (e) {
                self.updateLoading(false);
                return false;
            }
        },
        updateLoading: function(isLoading) {
            this.isLoading = isLoading;
            if (isLoading) {
                fullScreenLoader.startLoader();
            } else {
                fullScreenLoader.stopLoader();
            }
        },
        processOneyFailure: function(message) {
            this.isOneyPlaceOrderDisabled(true);
            this.updateOneyLogo(this.getConfiguration().logo_ko);
            this.updateOneyError(message);
            this.updatePlaceOrderButtonLabel(null);
            this.getOneyContainer().find('.oneyPayment').removeClass('oneyPayment-open').html('');
        },
        processOneySuccess: function(oneySimulationResult) {
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
        selectOneyOption: function(option){
            var oneyPayment = this.getOneyContainer().find('.oneyPayment');
            oneyPayment.find('.oneyOption').removeClass('oneyOption-selected');
            option.addClass('oneyOption-selected');

            oneyPayment.find('input[type="radio"]').prop('checked', false);
            option.find('input[type="radio"]').prop('checked', true);

            this.updatePlaceOrderButtonLabel(option.data('type'));
        },
        updateOneyLogo: function(logo){
            this.getOneyContainer().find('.oney-logo-checkout').attr('src', logo);
        },
        updateOneyError: function(message) {
            let oneyError = this.getOneyContainer().find('.oney-checkout-error');
            if (message === '') {
                oneyError.removeClass('active');
            } else {
                oneyError.addClass('active');
            }
            oneyError.html(message);
        },
        updatePlaceOrderButtonLabel: function(type){
            var label;
            if (type === null) {
                label = $.mage.__('Place Order');
            } else {
                label = $.mage.__(this.getPaymentTypeLabel());
                label = label.replace('%1', type);
            }

            this.getOneyContainer().find('.oney-submit-button').find('span').html(label);
        },
        buildOneyDetail: function(oneySimulationResult){
            var tmpDiv = $('<div/>');
            var optionsWrapper = $('<div/>').addClass('oneyOption_wrapper');
            var label = $.mage.__(this.getPaymentTypeLabel());

            for (var i = 0; i < oneySimulationResult.options.length; i++) {
                var optionData = oneySimulationResult.options[i];
                var type = optionData.type;
                var fullType = optionData.type;
                var option = $('<label/>', { 'data-type': fullType }).addClass('oneyOption oneyOption-' + fullType);

                // Title
                var title = $('<div/>').addClass('oneyOption_title');
                title.append($('<span/>').addClass('oneyOption_logo oneyLogo oneyLogo-' + type));
                title.append(label.replace('%1', type));
                option.append(title);

                // Option detail
                if (typeof optionData.schedules !== 'undefined' && optionData.schedules.length > 0) {
                    var detail = $('<div/>').addClass('oneyOption_prices');
                    var list = $('<ul/>').addClass('oneyOption_list');
                    var firstDeposit = $('<li/>')
                        .append($('<span/>').html($.mage.__('Total order amount')))
                        .append($('<span/>').addClass('oneyOption_price').html(this.getFormattedPrice(optionData.first_deposit)))
                    ;
                    list.append(firstDeposit);
                    var daysForPayment = 30;
                    var scheduleLabel = $.mage.__('In %1 days');
                    for (var j = 0; j < optionData.schedules.length; j++) {
                        var scheduleData = optionData.schedules[j];
                        var schedule = $('<li/>')
                            .append($('<span/>').html(scheduleLabel.replace('%1', daysForPayment)))
                            .append($('<span/>').addClass('oneyOption_price').html(this.getFormattedPrice(scheduleData.amount)))
                        ;
                        list.append(schedule);
                        daysForPayment += 30;
                    }
                    var totalAmount = $('<li/>')
                        .append($('<span/>').html($.mage.__('Total cost')))
                        .append($('<span/>').addClass('oneyOption_price').html(this.getFormattedPrice(optionData.total_amount)))
                    ;
                    list.append(totalAmount);

                    detail.append(list);
                    option.append(detail);
                }

                // Option radio button
                var radio = $('<div/>').addClass('oneyOption_radio').append(
                    $('<div/>').addClass('radio').append(
                        $('<span/>').append(
                            $('<input/>').attr('type', 'radio').attr('name', 'oney_type').val(fullType)
                        )
                    )
                );
                option.append(radio);

                optionsWrapper.append(option);
            }

            tmpDiv.append(optionsWrapper);
            this.getOneyContainer().find('.oneyPayment').html(tmpDiv.html());
        },
        getFormattedPrice: function(price) {
            return priceUtils.formatPrice(price, quote.getPriceFormat());
        },
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
        getPrepaidMentionClass: function() {
            let mentionClass = 'prepaid-card-mention';

            if (/^it/.test(navigator.language)) {
                mentionClass += ' visible';
            }

            return mentionClass;
        },
        getOneyContainer: function() {
            return $('[data-oney-container="' + this.getCode() + '"]');
        },
        // Functions to be defined by each oney payment renderer
        getConfiguration: function() {
            return {};
        },
        getPaymentTypeLabel: function() {
            return '';
        }
    });
});
