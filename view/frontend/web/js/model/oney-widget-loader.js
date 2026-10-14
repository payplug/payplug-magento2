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
         * Load the official Oney widget once and resolve when oneyMerchantApp is available, retrying after a failure.
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

            // Not loaded with RequireJS: it keeps a failed script and require.undef() freezes the checkout loader
            $.ajax({url: loaderUrl, dataType: 'script', cache: true, global: false}).done(function () {
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
            }).fail(function () {
                deferred.reject(new Error('Oney widget loader could not be loaded.'));
            });

            widgetPromise.always(function () {
                clearTimeout(timer);
            });

            widgetPromise.fail(function () {
                widgetPromise = null;
            });

            return widgetPromise;
        }
    };
});
