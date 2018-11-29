require(
    [
        'prototype',
        'domReady!'
    ],
    function () {
        var environment_mode = $('payment_us_payplug_payments_environmentmode');
        if (environment_mode === null) {
            return;
        }
        var one_click = $('payment_us_payplug_payments_one_click');
        var pwd = $('row_payment_us_payplug_payments_pwd');
        var is_verified = $('payplug_payments_is_verified').value;

        var is_premium = $('payplug_payments_is_premium').value;

        if (pwd !== null) {
            hidePwdField();

            if ($('payment_us_payplug_payments_pwd')) {
                var pwd_field = $('payment_us_payplug_payments_pwd');
                pwd_field.value = '';
                pwd_field.observe('keypress', keypressHandler);
            }

            environment_mode.observe('change', function () {
                hidePwdField();
                if (is_verified == 0 && environment_mode.value == 'live') {
                    pwd.show();
                }
            });

            if (one_click !== null) {
                one_click.observe('change', function () {
                    hidePwdField();
                    if (is_premium == 0 && one_click.value == 1 && environment_mode.value == 'live') {
                        pwd.show();
                    }
                });
            }
        }

        var linkedFields = [
            'payment_us_payplug_payments_email',
            'payment_us_payplug_payments_pwd',
            'payment_us_payplug_payments_environmentmode',
            'payment_us_payplug_payments_payment_page',
            'payment_us_payplug_payments_one_click'
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
            linkedFields.push('payment_us_payplug_payments_account_details');
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
                $('row_payment_us_payplug_payments_pwd').hide();
            }
        }
    }
);
