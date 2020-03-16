require([
    'jquery',
    'mage/translate',
    'domReady!'
], function ($) {
    'use strict';

    $('body').find('.oney-shipping-mapping-type').each(function(){
        displayNoCarrierMessage($(this));
    });

    $('body').on('change', '.oney-shipping-mapping-type', function(){
        displayNoCarrierMessage($(this));
    });

    function displayNoCarrierMessage(select) {
        let value = select.val();
        let divClass = 'tooltip-oney-shipping-mapping';
        select.siblings('div.' + divClass).remove();

        if (value === '' || value === 'carrier') {
            return;
        }

        let tooltip = $('<div/>').addClass('tooltip').addClass(divClass);
        let spanHelp = $('<span/>').addClass('help');
        spanHelp.append($('<span/>'));
        tooltip.append(spanHelp);
        tooltip.append($('<div/>').addClass('tooltip-content').html($.mage.__('This shipping type does not allow payment with Oney.')));
        tooltip.insertAfter(select);
    }
});
