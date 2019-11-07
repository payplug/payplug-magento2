require([
    'jquery',
    'mage/translate'
], function ($) {
    'use strict';
    
    updateSentByValueLabel(false);
    $('body').on('change', '.payment-link-sent-by select', function(){
        updateSentByValueLabel(true);
    });

    function updateSentByValueLabel(clearValue){
        let sendByValueContainer = $('.payment-link-sent-by-value');
        let selectedSentBy = $('.payment-link-sent-by').find('select').val();
        let label = $.mage.__('Email');
        if (selectedSentBy === 'SMS') {
            label = $.mage.__('Mobile');
        }
        sendByValueContainer.find('label span').html(label);
        if (clearValue) {
            sendByValueContainer.find('input').val('');
        }
    }
});
