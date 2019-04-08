require(
    [
        'prototype',
        'domReady!'
    ],
    function () {
        var installmentPlanCountRow = $('row_payment_us_payplug_payments_installment_plan_count');
        if (installmentPlanCountRow === null) {
            return;
        }

        var note = installmentPlanCountRow.select('.note')[0].replace('<div class="note"></div>');
        var note = installmentPlanCountRow.select('.note')[0];

        $$('input[name="groups[payplug_payments_installment_plan][fields][count][value]"]').each(function(radioButton){
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
