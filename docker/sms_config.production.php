<?php
// Production SMS config — reads credentials from environment variables.
// Set these in the Render dashboard (or `docker run -e ...`).

define('SMS_API_KEY', getenv('SMS_API_KEY') ?: '');
define('IPROG_SMS_PROVIDER', (int)(getenv('IPROG_SMS_PROVIDER') ?: 0));
define('SMS_SENDER_NAME', getenv('SMS_SENDER_NAME') ?: 'MOTOFIX');
define('SMS_ENABLED', filter_var(getenv('SMS_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('SMS_DEBUG', filter_var(getenv('SMS_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));

define('SMS_API_URL', getenv('SMS_API_URL') ?: 'https://www.iprogsms.com/api/v1/sms_messages');
define('SMS_STATUS_URL', getenv('SMS_STATUS_URL') ?: 'https://www.iprogsms.com/api/v1/sms_messages/status');
define('SMS_CREDITS_URL', getenv('SMS_CREDITS_URL') ?: 'https://www.iprogsms.com/api/v1/account/sms_credits');

define('MAINTENANCE_REMINDER_DAYS', (int)(getenv('MAINTENANCE_REMINDER_DAYS') ?: 7));
define('MAINTENANCE_REMINDER_KM', (int)(getenv('MAINTENANCE_REMINDER_KM') ?: 200));
define('APPOINTMENT_REMINDER_DAYS', (int)(getenv('APPOINTMENT_REMINDER_DAYS') ?: 1));
define('WARRANTY_REMINDER_DAYS_30', (int)(getenv('WARRANTY_REMINDER_DAYS_30') ?: 30));
define('WARRANTY_REMINDER_DAYS_7', (int)(getenv('WARRANTY_REMINDER_DAYS_7') ?: 7));
