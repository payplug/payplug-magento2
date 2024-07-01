define([
    'jquery',
    'mage/url'
], function ($, urlBuilder) {
    'use strict';

    $.widget('payplug.oneyPopin', {
        hasChanged: true,
        popin: '.oneyPopin',
        triggerButton: $('.oneyCta'),

        /**
         * @private
         */
        _bind: function () {
            const self = this;
            const body = $('body');

            body.on('click', '.oneyCta_button', function (event) {
                self.togglePopin(event);
            });
    
            body.on('click', '.oneyPopin_close', function () {
                self.hidePopin();
            });

            body.on('input', '[name="qty"]', function () {
                self._updateOneyWrapper();
            });
    
            body.on('change', '.super-attribute-select', function () {
                self._updateOneyWrapper();
            });

            body.on('click', '.oneyPopin_navigation li', function () {
                self._selectOption($(this));
            });
    
            body.on('click', function (event) {
                const clicked = $(event.target);
                
                if (!clicked.hasClass('oneyPopin') 
                    && clicked.closest('.oneyPopin').length === 0
                    && self.triggerButton.hasClass('oneyCta-open')) {
                    self.hidePopin();
                }
            });
        },

        /**
         * @private
         */
        _create: function () {
            this._bind();
        },

        /**
         * Get form data
         * @return {Object}
         * @private
         */
        _getFormData: function () {
            let formData = {
                form_key: $('[name="form_key"]').val()
            };
    
            if ($('.oney-product').length === 0) {
                return formData;
            }
    
            const product = $('[name="product"]');

            if (product.length !== 0) {
                formData.product = product.val();
                formData.qty = $('[name="qty"]').val();
                let productOptions = [];
                let configurableAttributes = $('.super-attribute-select');

                if (configurableAttributes.length > 0) {
                    configurableAttributes.each(function (index, value) {
                        let configurableAttribute = $(value);

                        productOptions.push({
                            attribute: configurableAttribute.data('attr-name'),
                            value: configurableAttribute.val()
                        });
                    });
                }

                formData.product_options = productOptions;
            }
    
            return formData;
        },

        /**
         * Update Oney wrapper
         * @private
         */
        _updateOneyWrapper: function () {
            const self = this;
            let formData = this._getFormData();
            formData.wrapper = true;
    
            this._getPaymentSimulations(formData).done(function (response) {
                if (response.success) {
                    $('.oney-wrapper').html(response.html);
                    self.hasChanged = true;
                }
            });
        },

        /**
         * Update Oney simulation
         * @private
         */
        _updateOneySimulation: function () {
            const self = this;
            const popin = $(this.popin);
            const formData = this._getFormData();

            popin
                .addClass('loading')
                .loader({
                    icon: require.toUrl('images/loader-1.gif'),
                })
                .loader('show');

            this._getPaymentSimulations(formData).done(function (response) {
                if (response.success) {
                    self._setDefaultPaymentOption();
                    self.hasChanged = false;

                    popin
                        .removeClass('loading')
                        .html(response.html);
                }
            });
        },

        /**
         * Get payment simulations
         * @return {Promise}
         * @private
         */
        _getPaymentSimulations: function (formData) {
            return $.ajax({
                url: urlBuilder.build('payplug_payments/oney/simulation'),
                type: 'POST',
                data: formData
            });
        },

        /**
         * Set default payment option
         * @private
         */
        _setDefaultPaymentOption: function (){
            const options = $('.oneyPopin').find('.oneyPopin_navigation li');

            if (options.length > 0) {
                this._selectOption($(options[0]));
            }
        },

        /**
         * Select option
         * @param {jQuery} option
         * @private
         */
        _selectOption: function (option) {
            if (option.hasClass('selected')) {
                return;
            }

            const type = option.data('type');

            $('.oneyPopin_navigation li').removeClass('selected');
            $('.oneyPopin_navigation li[data-type=' + type + ']').closest('li').addClass('selected');
    
            $('.oneyPopin_option').removeClass('oneyPopin_option-show');
            $('.oneyPopin_option[data-type=' + type + ']').addClass('oneyPopin_option-show');
        },

        /**
         * Toggle popin
         * @param {Event} event
         */
        togglePopin: function (event) {
            event.preventDefault();
            event.stopPropagation();

            if (this.triggerButton.hasClass('oneyCta-open')) {
                this.hidePopin();
                return;
            }

            this.showPopin();
        },

        /**
         * Hide popin
         */
        hidePopin: function () {
            const popin = $(this.popin);
            popin.removeClass('oneyPopin-open');
            this.triggerButton.removeClass('oneyCta-open');
        },

        /**
         * Show popin
         */
        showPopin: function () {
            const popin = $(this.popin);
            popin.addClass('oneyPopin-open');
            this.triggerButton.addClass('oneyCta-open');

            if (this.hasChanged) {
                this._updateOneySimulation();
            }
        }
    });

    return $.payplug.oneyPopin;
});
