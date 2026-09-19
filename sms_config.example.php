<?php
// sms_config.example.php
// Copy this file to sms_config.php and fill in your own values.
// sms_config.php is gitignored — never commit your real API key.

// Your IPROG SMS API token
define('SMS_API_KEY', 'YOUR_API_TOKEN_HERE');

// SMS provider (0 = default; 1, 2 available in IPROG)
define('IPROG_SMS_PROVIDER', 0);

// Sender name / brand. IPROG may require a registered sender name for some flows.
define('SMS_SENDER_NAME', 'MOTOFIX');

// Enable or disable actual SMS sending
define('SMS_ENABLED', true);

// Debug logging to PHP error log (never logs the API token)
define('SMS_DEBUG', true);

// IPROG SMS endpoints (from https://www.iprogsms.com/api/v1/documentation)
define('SMS_API_URL', 'https://www.iprogsms.com/api/v1/sms_messages');
define('SMS_STATUS_URL', 'https://www.iprogsms.com/api/v1/sms_messages/status');
define('SMS_CREDITS_URL', 'https://www.iprogsms.com/api/v1/account/sms_credits');

// Notification thresholds
define('MAINTENANCE_REMINDER_DAYS', 7);
define('MAINTENANCE_REMINDER_KM', 200);
define('APPOINTMENT_REMINDER_DAYS', 1);
define('WARRANTY_REMINDER_DAYS_30', 30);
define('WARRANTY_REMINDER_DAYS_7', 7);
