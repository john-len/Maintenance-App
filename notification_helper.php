<?php
/**
 * Notification helper for AutoCare Pro
 * Bridges PHP flash messages to the frontend using SweetAlert2 (modals) and Toastify (toasts).
 *
 * Usage:
 *   require 'notification_helper.php';
 *   set_flash_notification('success', 'Service Saved', 'The oil change record has been saved.');
 *
 * Then call render_notifications() once at the bottom of the page (or let the footer do it).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Queue a notification that will be shown on the next page load.
 *
 * @param string $type    success | error | warning | info
 * @param string $title   Short title/heading
 * @param string $message Optional longer message
 */
function set_flash_notification($type, $title, $message = '') {
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    $_SESSION['notifications'][] = [
        'type'    => $type,
        'title'   => $title,
        'message' => $message
    ];
}

/**
 * Get queued notifications and clear the queue.
 *
 * @return array
 */
function get_notifications() {
    if (empty($_SESSION['notifications']) || !is_array($_SESSION['notifications'])) {
        return [];
    }
    $notifications = $_SESSION['notifications'];
    unset($_SESSION['notifications']);
    return $notifications;
}

/**
 * Check if there are notifications waiting to be rendered.
 *
 * @return bool
 */
function has_notifications() {
    return !empty($_SESSION['notifications']);
}

/**
 * Render queued notifications as a JS array on the page.
 * The assets/notifications.js file will pick this up and display them.
 */
function render_notifications() {
    $notifications = get_notifications();
    if (empty($notifications)) {
        return;
    }

    $json = json_encode($notifications, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    echo "<script>window.__appNotifications = " . $json . ";</script>\n";
}
