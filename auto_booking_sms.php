<?php
/**
 * Legacy booking SMS cron — now runs the full notification scheduler.
 * Run with Windows Task Scheduler or CLI:
 * D:\xampp\php\php.exe -f D:\xampp\htdocs\Advance_Database-System\auto_booking_sms.php
 */

require __DIR__ . '/cron_sms_notifications.php';
