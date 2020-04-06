require([
    'jquery',
    'mage/url'
], function ($, urlBuilder) {
    'use strict';

    if ($('.oneyPopin').length === 0) {
        return;
    }

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
            updateProductSimulation();
        });
        body.on('change', '.super-attribute-select', function(e){
            updateProductSimulation();
        });
    }

    function updateProductSimulation(){
        let product = $('[name="product"]');
        if (product.length === 0) {
            return;
        }
        let productOptions = [];
        let configurableAttributes = $('.super-attribute-select');
        if (configurableAttributes.length > 0) {
            configurableAttributes.each(function(index, value){
                let configurableAttribute = $(value);
                productOptions.push({
                    'attribute': configurableAttribute.data('attr-name'),
                    'value': configurableAttribute.val()
                });
            });
        }
        let productData = {
            'product': product.val(),
            'qty': $('[name="qty"]').val(),
            'form_key': $('[name="form_key"]').val(),
            'product_options': productOptions
        };
        $.ajax({
            url: urlBuilder.build('payplug_payments/oney/simulation'),
            type: "POST",
            data: productData
        }).done(function (response) {
            if (response.success) {
                $('.oney-product').html(response.html);
                initOptions();
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
    }
});
