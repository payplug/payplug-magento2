/**
 * Payplug - https://www.payplug.com/
 * Copyright © Payplug. All rights reserved.
 * See LICENSE for license details.
 */

define([
    'jquery'
], function ($) {
    'use strict';

    var widgetPromise = null,
        loadTimeout = 10000;

    return {
        /**
         * Load the official Oney widget once and resolve when oneyMerchantApp is available.
         *
         * @param {String} loaderUrl
         * @returns {Promise}
         */
        load: function (loaderUrl) {
            var deferred,
                timer;

            if (widgetPromise !== null) {
                return widgetPromise;
            }

            deferred = $.Deferred();
            widgetPromise = deferred.promise();

            timer = setTimeout(function () {
                deferred.reject(new Error('Oney widget loading timed out.'));
            }, loadTimeout);

            require([loaderUrl], function () {
                if (deferred.state() !== 'pending') {
                    return;
                }

                if (typeof window.loadOneyWidget !== 'function') {
                    deferred.reject(new Error('Oney widget loader is not available.'));

                    return;
                }

                window.loadOneyWidget(function () {
                    if (window.oneyMerchantApp) {
                        deferred.resolve(window.oneyMerchantApp);
                    } else {
                        deferred.reject(new Error('Oney widget is not available.'));
                    }
                });
            }, function (error) {
                deferred.reject(error);
            });

            widgetPromise.always(function () {
                clearTimeout(timer);
            });

            return widgetPromise;
        }
    };
});
