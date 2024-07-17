require([
    'jquery',
    'mage/url'
], function ($, urlBuilder) {
    'use strict';

    if ($('.oney-wrapper').length === 0) {
        return;
    }

    let hasChanged = true;
    initOptions();

    let body = $('body');
    body.on('click', '.oneyCta_button', function(e){
        togglePopin(e);
    });
    body.on('click', '.oneyPopin_close', function(e){
        hidePopin();
    });
    body.on('click', '.oneyPopin_navigation li', function (e) {
        selectOption($(this));
    });
    body.on('click', function (event) {
        var $clicked = $(event.target);
        if ((!$clicked.hasClass('oneyPopin') && $clicked.closest('.oneyPopin').length === 0) && $('.oneyCta').hasClass('oneyCta-open')) {
            hidePopin();
        }
    });

    if ($('.oney-product').length > 0) {
        body.on('input', '[name="qty"]', function(e){
            updateOneyWrapper();
        });
        body.on('change', '.super-attribute-select', function(e){
            updateOneyWrapper();
        });
    }

    function getFormData(){
        let formData = {
            'form_key': $('[name="form_key"]').val(),
        }

        if ($('.oney-product').length === 0) {
            return formData;
        }

        let product = $('[name="product"]');
        if (product.length !== 0) {
            formData.product = product.val();
            formData.qty = $('[name="qty"]').val();
            let productOptions = [];
            let configurableAttributes = $('.super-attribute-select');
            if (configurableAttributes.length > 0) {
                configurableAttributes.each(function (index, value) {
                    let configurableAttribute = $(value);
                    productOptions.push({
                        'attribute': configurableAttribute.data('attr-name'),
                        'value': configurableAttribute.val()
                    });
                });
            }
            formData.product_options = productOptions;
        }

        return formData;
    }

    function updateOneyWrapper(){
        let formData = getFormData();
        formData.wrapper = true;

        $.ajax({
            url: urlBuilder.build('payplug_payments/oney/simulation'),
            type: "POST",
            data: formData
        }).done(function (response) {
            if (response.success) {
                $('.oney-wrapper').html(response.html);
                hasChanged = true;
            }
        });
    }

    function updateOneySimulation(){
        let popin = $('.oneyPopin');
        popin.addClass('loading');
        popin.loader({
            icon: require.toUrl('images/loader-1.gif')
        }).loader('show');

        $.ajax({
            url: urlBuilder.build('payplug_payments/oney/simulation'),
            type: "POST",
            data: getFormData()
        }).done(function (response) {
            if (response.success) {
                popin.removeClass('loading').html(response.html);
                initOptions();
                hasChanged = false;
            }
        });
    }
    function initOptions(){
        let options = $('.oneyPopin').find('.oneyPopin_navigation li');
        if (options.length > 0) {
            selectOption($(options[0]));
        }
    }
    function selectOption(li) {
        if (li.hasClass('selected')) {
            return;
        }
        let type = li.data('type');
        $('.oneyPopin_navigation li').removeClass('selected');
        $('.oneyPopin_navigation li[data-type=' + type + ']').closest('li').addClass('selected');

        $('.oneyPopin_option').removeClass('oneyPopin_option-show');
        $('.oneyPopin_option[data-type=' + type + ']').addClass('oneyPopin_option-show');
    }
    function togglePopin(e){
        e.preventDefault();
        e.stopPropagation();
        if ($('.oneyCta').hasClass('oneyCta-open')) {
            hidePopin();
            return;
        }
        showPopin();
    }
    function hidePopin(){
        let popin = $('.oneyPopin');
        popin.addClass('oneyPopin-show');
        popin.removeClass('oneyPopin-open');

        setTimeout(function () {
            $('.oneyCta').removeClass('oneyCta-open');
        }, 400);
    }
    function showPopin(){
        let popin = $('.oneyPopin');
        $('.oneyCta').addClass('oneyCta-open');
        popin.addClass('oneyPopin-open');

        setTimeout(function () {
            popin.addClass('oneyPopin-show');
        }, 0);
        if (hasChanged) {
            updateOneySimulation();
        }
    }
});
