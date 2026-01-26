/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

require(
    [
        'prototype',
        'domReady!'
    ],
    function () {
        var planCountValues = $$('input[name="groups[payplug_payments_installment_plan][fields][count][value]"]');
        if (planCountValues.length === 0) {
            return;
        }

        var installmentPlanCountRow = planCountValues[0].closest('tr');
        if (installmentPlanCountRow === null) {
            return;
        }

        var note = installmentPlanCountRow.select('.note')[0].replace('<div class="note"></div>');
        note = installmentPlanCountRow.select('.note')[0];

        planCountValues.each(function(radioButton){
            if (radioButton.checked) {
                changeCountNote(radioButton.value);
            }
            radioButton.observe('change', function(){
                if ($(this).checked) {
                    changeCountNote($(this).value);
                }
            });
        });

        function changeCountNote(value) {
            note.update($('payplug_payments_installment_plan_' + value).innerHTML);
        }
    }
);
