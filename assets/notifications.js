/**
 * AutoCare Pro notification helpers
 * Uses SweetAlert2 for action-driven modals and Toastify for passive reminders.
 */
(function () {
    'use strict';

    // Initialize any queued PHP notifications on DOM ready.
    document.addEventListener('DOMContentLoaded', function () {
        if (window.__appNotifications && Array.isArray(window.__appNotifications)) {
            window.__appNotifications.forEach(function (n) {
                showNotification(n.type, n.title, n.message);
            });
        }
    });

    /**
     * Show a notification. Errors and titled-only messages use SweetAlert2;
     * everything else uses a Toastify toast.
     */
    function showNotification(type, title, message) {
        var types = ['success', 'error', 'warning', 'info'];
        var normalizedType = types.indexOf(type) !== -1 ? type : 'info';

        if (type === 'error' || (title && !message)) {
            Swal.fire({
                icon: normalizedType,
                title: title,
                text: message || '',
                confirmButtonColor: '#f59e0b',
                timer: type === 'error' ? null : 3000
            });
        } else {
            var text = message ? title + '\n' + message : title;
            Toastify({
                text: text,
                duration: 4000,
                gravity: 'top',
                position: 'right',
                close: true,
                className: 'app-toast app-toast--' + normalizedType,
                style: {
                    background: toastColor(normalizedType)
                }
            }).showToast();
        }
    }

    function toastColor(type) {
        var colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };
        return colors[type] || colors.info;
    }

    // Global helpers for inline JS use.
    window.notifySuccess = function (title, message) { showNotification('success', title, message); };
    window.notifyError = function (title, message) { showNotification('error', title, message); };
    window.notifyWarning = function (title, message) { showNotification('warning', title, message); };
    window.notifyInfo = function (title, message) { showNotification('info', title, message); };

    // Confirmation helper for destructive actions (delete, cancel, etc.).
    window.confirmAction = function (callback, options) {
        options = options || {};
        Swal.fire({
            title: options.title || 'Are you sure?',
            text: options.text || 'This action cannot be undone.',
            icon: options.icon || 'warning',
            showCancelButton: true,
            confirmButtonColor: options.confirmColor || '#ef4444',
            cancelButtonColor: options.cancelColor || '#6b7280',
            confirmButtonText: options.confirmText || 'Yes',
            cancelButtonText: options.cancelText || 'Cancel'
        }).then(function (result) {
            if (result.isConfirmed && typeof callback === 'function') {
                callback();
            }
        });
    };
})();
