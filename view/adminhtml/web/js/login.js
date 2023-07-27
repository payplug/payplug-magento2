require(
    [
        'prototype',
        'domReady!'
    ],
    function () {
        var environment_mode = $('payplug_payments_general_environmentmode');
        if (environment_mode === null) {
            return;
        }
        var pwd = $('row_payplug_payments_general_pwd');
        var is_verified = $('payplug_payments_is_verified').value;

        if (pwd !== null) {
            hidePwdField();

            if ($('payplug_payments_general_pwd')) {
                var pwd_field = $('payplug_payments_general_pwd');
                pwd_field.value = '';
                pwd_field.observe('keypress', keypressHandler);
            }

            environment_mode.observe('change', function () {
                hidePwdField();
                if (is_verified == 0 && environment_mode.value == 'live') {
                    pwd.show();
                }
            });
        }

        var linkedFields = [
            'payplug_payments_general_email',
            'payplug_payments_general_pwd',
            'payplug_payments_general_environmentmode',
            'payplug_payments_general_page'
        ];

        if ($('payplug_payments_can_override_default') !== null) {
            for (var i=0; i < linkedFields.length; i++) {
                if ($(linkedFields[i] + '_inherit') !== null) {
                    $(linkedFields[i] + '_inherit').observe('change', function (event) {
                        var isChecked = $(this).checked;
                        for (var j = 0; j < linkedFields.length; j++) {
                            // Automatically check / uncheck connexion related fields
                            if ($(linkedFields[j] + '_inherit') !== null && $(linkedFields[j]) !== null) {
                                $(linkedFields[j] + '_inherit').checked = isChecked;
                                $(linkedFields[j]).disabled = isChecked;
                            }
                        }
                    });
                }
            }
        }

        if ($('payplug_payments_prevent_default') !== null) {
            linkedFields.push('payplug_payments_general_account_details');
            for (var i=0; i < linkedFields.length; i++) {
                if ($(linkedFields[i] + '_inherit') !== null) {
                    if ($(linkedFields[i] + '_inherit').checked) {
                        $(linkedFields[i] + '_inherit').click();
                    }
                    $(linkedFields[i] + '_inherit').observe('change', function (event) {
                        // once connected on website level, prevent "Use default" to be checked
                        $(this).checked = false;
                        var name = $(this).readAttribute('id').replace('_inherit', '');
                        if ($(name) !== null) {
                            $(name).disabled = false;
                        }
                    });
                }
            }
        }

        function keypressHandler (event){
            var key = event.which || event.keyCode;
            switch (key) {
                default:
                    break;
                case Event.KEY_RETURN:
                    configForm.submit();
                    break;
            }
        }

        function hidePwdField(){
            if ($('payplug_payments_is_connected').value == 1) {
                $('row_payplug_payments_general_pwd').hide();
            }
        }

        let typeSelect = $('payplug_payments_general_payment_page');
        handlePaymentPageTypeComment();
        typeSelect.observe('change', function () {
            handlePaymentPageTypeComment();
        });

        function handlePaymentPageTypeComment() {
            if (typeSelect.value === 'integrated') {
                typeSelect.siblings('p.note')[0].show();
            } else {
                typeSelect.siblings('p.note')[0].hide();
            }
        }
    }
);
