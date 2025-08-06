define([
    'Magento_Customer/js/customer-data'
], function (customerData) {
    'use strict';

    return function () {
        customerData.initStorage();
        customerData.invalidate(['cart']);
        customerData.reload(['cart'], true);
    };
});
