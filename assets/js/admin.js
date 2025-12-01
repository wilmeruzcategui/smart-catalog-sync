/**
 * Smart Catalog Sync - Admin JavaScript
 * Handles AJAX interactions and UI updates
 */

(function($) {
    'use strict';

    const SCS = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#scs-manual-sync').on('click', this.handleManualSync.bind(this));
            $('#scs-test-connection').on('click', this.handleTestConnection.bind(this));
        },

        /**
         * Handle manual sync button click
         */
        handleManualSync: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);

            // Check if webhook URL is filled
            const webhookUrl = $('#webhook_url').val();
            if (!webhookUrl) {
                this.showNotification(
                    'Por favor, configura la URL de destino antes de sincronizar',
                    'error'
                );
                return;
            }

            // Disable button and show loading state
            $button.prop('disabled', true).addClass('loading');

            $.ajax({
                url: scsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'scs_manual_sync',
                    nonce: scsAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification(
                            response.data.message || scsAdmin.strings.syncSuccess,
                            'success'
                        );

                        // Update last sync time
                        this.updateLastSyncTime();
                    } else {
                        this.showNotification(
                            response.data.message || scsAdmin.strings.syncError,
                            'error'
                        );
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotification(
                        scsAdmin.strings.syncError + ': ' + error,
                        'error'
                    );
                },
                complete: () => {
                    // Re-enable button and remove loading state
                    $button.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Handle test connection button click
         */
        handleTestConnection: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const webhookUrl = $('#webhook_url').val();

            if (!webhookUrl) {
                this.showNotification(
                    'Por favor, ingresa una URL de destino',
                    'error'
                );
                return;
            }

            // Validate URL format
            try {
                new URL(webhookUrl);
            } catch (error) {
                this.showNotification(
                    'Por favor, ingresa una URL válida',
                    'error'
                );
                return;
            }

            // Disable button and show loading state
            $button.prop('disabled', true).addClass('loading');

            $.ajax({
                url: scsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'scs_test_connection',
                    nonce: scsAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification(
                            response.data.message || scsAdmin.strings.testSuccess,
                            'success'
                        );
                    } else {
                        this.showNotification(
                            response.data.message || scsAdmin.strings.testError,
                            'error'
                        );
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotification(
                        scsAdmin.strings.testError + ': ' + error,
                        'error'
                    );
                },
                complete: () => {
                    // Re-enable button and remove loading state
                    $button.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Update last sync time display
         */
        updateLastSyncTime: function() {
            // Reload the page to show updated stats
            // In a real-world scenario, you might want to update this via AJAX
            setTimeout(() => {
                location.reload();
            }, 1500);
        },

        /**
         * Show notification toast
         */
        showNotification: function(message, type) {
            const $notification = $('#scs-notification');

            // Remove existing classes
            $notification.removeClass('success error info show');

            // Add new class and show
            $notification
                .addClass(type)
                .text(message)
                .addClass('show');

            // Auto-hide after 5 seconds
            setTimeout(() => {
                $notification.removeClass('show');
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SCS.init();
    });

})(jQuery);
