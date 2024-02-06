/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2021 Extend Inc. (https://www.extend.com/)
 */
define(
    [
        'jquery',
        'Magento_Ui/js/modal/alert',
        'mage/translate'
    ],
    function ($, alert, $t) {
        'use strict';

        var currentBatchesProcessed = 1;
        var totalBatches = 0;
        var shouldAbort = false;
        var synMsg = $("#sync-msg");
        var cancelSync = $("#cancel_sync");
        var resetSync = $("#reset_sync");
        var resetFlagUrl = '';

        function restore(button) {
            button.text('Sync Products');
            button.removeClass("syncing");
            button.attr("disabled", false);
            synMsg.show();
            cancelSync.hide();
            currentBatchesProcessed = 1;
            totalBatches = 0;
            shouldAbort = false;
        }

        async function syncAllProducts(url, button) {
            currentBatchesProcessed = 1;
            totalBatches = 0;
            do {
                var data = await batchBeingProcessed(shouldAbort, url).then(data => {
                    return data;            //success
                }, data => {
                    return {                //fail
                        'totalBatches': 0,
                        'currentBatchesProcessed': 1
                    };
                }).catch(e => {
                    console.log(e);
                });
                currentBatchesProcessed = data.currentBatchesProcessed;
                totalBatches = data.totalBatches;
                $("#sync-time").text(data.msg);
            } while (currentBatchesProcessed <= totalBatches);
            return true;
        }

        function batchBeingProcessed(shouldAbort, url) {
            if (!shouldAbort) {
                return new Promise((resolve, reject) => {
                    $.get({
                        url: url,
                        dataType: 'json',
                        async: true,
                        data: {
                            currentBatchesProcessed: currentBatchesProcessed
                        },
                        success: function (data) {
                            resolve(data)
                        },
                        error: function (data) {
                            reject(data);
                        }
                    })
                })
            } else {
                return new Promise((resolve, reject) => {
                    var data = {
                        'totalBatches': 0,
                        'currentBatchesProcessed': 1
                    };
                    $.get({
                        url: resetFlagUrl,
                        dataType: 'json',
                        success: function (data) {
                            resolve(data);
                        },
                        error: function (data) {
                            reject(data);
                        }
                    })
                })
            }
        }

        $.widget('extend.productSync', {
            options: {
                url: '',
                syncUrls: [],
                resetFlagUrl: '',
                resetSyncDateUrl: '',

            },

            _create: function () {
                this._super();
                this._bind();
            },

            _bind: function () {
                $(this.element).click(this.syncProducts.bind(this));
                var cancelSync = $("#cancel_sync"),
                    self = this;
                $(cancelSync).bind("click", function () {
                    shouldAbort = true;
                    resetFlagUrl = self.options.resetFlagUrl;
                });

                $(resetSync).bind("click", function () {
                    let resetText = resetSync.text();
                    resetSync.text('Processing...');
                    $.get({
                        url: self.options.resetSyncDateUrl,
                        dataType: 'json',
                        success: function (data) {
                            $('#sync-time').html(data.message);
                            resetSync.hide();
                            resetSync.text(resetText);
                        },
                        error: function (data) {
                            resetSync.text(resetText);
                        }
                    })
                });

            },
            syncProducts: async function (event) {
                event.preventDefault();
                var button = $(this.element);
                button.text('Sync in progress...');
                button.addClass("syncing");
                button.attr("disabled", true);

                synMsg.hide();
                cancelSync.show();

                for (let url of this.options.syncUrls) {
                    let storeSync = new Promise(function (resolve) {
                        resolve(syncAllProducts(url, button));
                    });
                    await storeSync;
                }
                ;

                resetSync.show();
                restore(button);
            }
        });

        return $.extend.productSync;
    })
;
