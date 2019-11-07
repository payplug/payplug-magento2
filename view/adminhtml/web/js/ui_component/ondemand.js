define([
    'Magento_Ui/js/form/element/select',
    'jquery',
    'uiRegistry',
    'mage/translate'
], function (select, $, uiRegistry) {
    'use strict';

    return select.extend({
        onUpdate: function (value) {
            this.updateSentByValueLabel(value, true);

            this._super();
        },
        setInitialValue: function () {
            this.updateSentByValueLabel(this.getInitialValue(), false);

            return this._super();
        },
        updateSentByValueLabel: function(sentByValue, clearValue) {
            let sendByValueContainer = $('.payment-link-sent-by-value');
            let label = $.mage.__('Email');
            if (sentByValue === 'SMS') {
                label = $.mage.__('Mobile');
            }
            uiRegistry.get("new_payment_link_form.areas.form.form.sent_by_value").label = label;
            sendByValueContainer.find('label span').html(label);
            if (clearValue) {
                sendByValueContainer.find('input').val('');
            }
        }
    });
});
