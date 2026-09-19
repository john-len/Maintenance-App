<?php
/**
 * AutoCare Pro — Automated SMS Notification Scheduler
 * Run via CLI or Windows Task Scheduler:
 * D:\xampp\php\php.exe -f D:\xampp\htdocs\Advance_Database-System\cron_sms_notifications.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only.');
}

require_once 'db.php';
require_once 'sms_config.php';
require_once 'sms_helper.php';
require_once 'SMSTemplates.php';

$sms = new SMSHelper();

$summary = [
    'started' => date('Y-m-d H:i:s'),
    'maintenance_reminders' => 0,
    'overdue_maintenance' => 0,
    'appointment_confirmations' => 0,
    'appointment_reminders' => 0,
    'warranty_reminders' => 0,
    'sent' => 0,
    'failed' => 0,
    'skipped' => 0,
    'completed' => date('Y-m-d H:i:s')
];

function getServiceNames($pdo, $serviceIdsJson) {
    $ids = json_decode($serviceIdsJson, true);
    if (empty($ids) || !is_array($ids)) return 'Service Appointment';
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT service_name FROM services WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $names ? implode(', ', $names) : 'Service Appointment';
}

function recordResult(&$summary, $result) {
    $code = $result['error_code'] ?? '';
    if ($code === 'DUPLICATE' || $code === 'DISABLED') {
        $summary['skipped']++;
    } elseif ($result['success'] && empty($code)) {
        $summary['sent']++;
    } else {
        $summary['failed']++;
    }
}

// =========================================================================
// 1. MAINTENANCE DATE REMINDERS / OVERDUE
// =========================================================================
$stmt = $pdo->query("
    SELECT
        m.id AS motorcycle_id,
        m.brand,
        m.model,
        m.current_mileage,
        m.last_service_mileage,
        m.maintenance_interval_km,
        m.maintenance_interval_months,
        m.last_maintenance_date,
        m.next_maintenance_date,
        m.purchase_date,
        u.id AS user_id,
        u.phone
    FROM motorcycles m
    JOIN users u ON m.user_id = u.id
    WHERE m.status = 'active'
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (empty($row['phone'])) continue;

    $lastService = $row['last_maintenance_date'] ?: $row['purchase_date'];
    $intervalMonths = intval($row['maintenance_interval_months']) ?: 6;
    $nextDate = !empty($row['next_maintenance_date'])
        ? $row['next_maintenance_date']
        : date('Y-m-d', strtotime($lastService . ' +' . $intervalMonths . ' months'));

    $diff = (new DateTime($nextDate))->diff(new DateTime())->format('%r%a');
    $daysUntil = intval($diff);

    if ($daysUntil < 0) {
        $key = 'OVERDUE_MAINTENANCE_' . $row['motorcycle_id'] . '_' . $nextDate;
        $msg = SMSTemplates::overdueMaintenanceDate($row['brand'], $row['model']);
        $result = $sms->sendSMS($row['phone'], $msg, 'OVERDUE_MAINTENANCE', [
            'user_id' => $row['user_id'],
            'customer_id' => $row['user_id'],
            'motorcycle_id' => $row['motorcycle_id'],
            'notification_key' => $key,
            'reference_id' => $nextDate
        ]);
        $summary['overdue_maintenance']++;
        recordResult($summary, $result);
        continue;
    }

    if ($daysUntil <= MAINTENANCE_REMINDER_DAYS) {
        $key = 'MAINTENANCE_REMINDER_DATE_' . $row['motorcycle_id'] . '_' . $nextDate;
        $msg = SMSTemplates::maintenanceReminderDate(
            $row['brand'],
            $row['model'],
            date('M j, Y', strtotime($nextDate))
        );
        $result = $sms->sendSMS($row['phone'], $msg, 'MAINTENANCE_REMINDER', [
            'user_id' => $row['user_id'],
            'customer_id' => $row['user_id'],
            'motorcycle_id' => $row['motorcycle_id'],
            'notification_key' => $key,
            'reference_id' => $nextDate
        ]);
        $summary['maintenance_reminders']++;
        recordResult($summary, $result);
    }
}

// =========================================================================
// 2. MILEAGE-BASED MAINTENANCE REMINDERS / OVERDUE
// =========================================================================
$stmt = $pdo->query("
    SELECT
        m.id AS motorcycle_id,
        m.brand,
        m.model,
        m.current_mileage,
        m.last_service_mileage,
        m.maintenance_interval_km,
        u.id AS user_id,
        u.phone
    FROM motorcycles m
    JOIN users u ON m.user_id = u.id
    WHERE m.status = 'active'
      AND m.maintenance_interval_km IS NOT NULL
      AND m.maintenance_interval_km > 0
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (empty($row['phone'])) continue;

    $lastKm = floatval($row['last_service_mileage'] ?? 0);
    $dueKm = $lastKm + floatval($row['maintenance_interval_km']);
    $milesRemaining = $dueKm - floatval($row['current_mileage']);

    if ($milesRemaining < 0) {
        $key = 'OVERDUE_MAINTENANCE_KM_' . $row['motorcycle_id'] . '_' . $dueKm;
        $msg = SMSTemplates::overdueMaintenanceKm(
            $row['brand'],
            $row['model'],
            number_format($row['maintenance_interval_km']),
            number_format($row['current_mileage'])
        );
        $result = $sms->sendSMS($row['phone'], $msg, 'OVERDUE_MAINTENANCE', [
            'user_id' => $row['user_id'],
            'customer_id' => $row['user_id'],
            'motorcycle_id' => $row['motorcycle_id'],
            'notification_key' => $key,
            'reference_id' => $dueKm
        ]);
        $summary['overdue_maintenance']++;
        recordResult($summary, $result);
        continue;
    }

    if ($milesRemaining <= MAINTENANCE_REMINDER_KM) {
        $key = 'MAINTENANCE_REMINDER_KM_' . $row['motorcycle_id'] . '_' . $dueKm;
        $msg = SMSTemplates::maintenanceReminderKm(
            $row['brand'],
            $row['model'],
            number_format($row['maintenance_interval_km']),
            number_format($row['current_mileage'])
        );
        $result = $sms->sendSMS($row['phone'], $msg, 'MAINTENANCE_REMINDER', [
            'user_id' => $row['user_id'],
            'customer_id' => $row['user_id'],
            'motorcycle_id' => $row['motorcycle_id'],
            'notification_key' => $key,
            'reference_id' => $dueKm
        ]);
        $summary['maintenance_reminders']++;
        recordResult($summary, $result);
    }
}

// =========================================================================
// 3. APPOINTMENT CONFIRMATIONS (fallback for accepted bookings not yet confirmed)
// =========================================================================
$stmt = $pdo->query("
    SELECT
        b.id AS booking_id,
        b.user_id,
        b.vehicle_id,
        b.service_ids,
        b.schedule_date,
        b.schedule_start_time,
        u.phone
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.status = 'accepted'
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (empty($row['phone'])) continue;

    $key = 'APPOINTMENT_CONFIRMATION_' . $row['booking_id'];
    if ($sms->isAlreadySent($key)) continue;

    $services = getServiceNames($pdo, $row['service_ids']);
    $msg = SMSTemplates::appointmentConfirmation(
        $row['booking_id'],
        date('M j, Y', strtotime($row['schedule_date'])),
        date('g:i A', strtotime($row['schedule_start_time'])),
        $services
    );
    $result = $sms->sendSMS($row['phone'], $msg, 'APPOINTMENT_CONFIRMATION', [
        'user_id' => $row['user_id'],
        'customer_id' => $row['user_id'],
        'booking_id' => $row['booking_id'],
        'motorcycle_id' => $row['vehicle_id'] ?? null,
        'notification_key' => $key,
        'reference_id' => $row['booking_id']
    ]);
    $summary['appointment_confirmations']++;
    recordResult($summary, $result);
}

// =========================================================================
// 4. APPOINTMENT REMINDERS (1 day before)
// =========================================================================
$reminderDate = date('Y-m-d', strtotime('+' . APPOINTMENT_REMINDER_DAYS . ' days'));

$stmt = $pdo->prepare("
    SELECT
        b.id AS booking_id,
        b.user_id,
        b.service_ids,
        b.schedule_date,
        b.schedule_start_time,
        u.phone
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.status = 'accepted'
      AND b.schedule_date = ?
");
$stmt->execute([$reminderDate]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (empty($row['phone'])) continue;

    $key = 'APPOINTMENT_REMINDER_' . $row['booking_id'];
    $services = getServiceNames($pdo, $row['service_ids']);
    $msg = SMSTemplates::appointmentReminder(
        date('M j, Y', strtotime($row['schedule_date'])),
        date('g:i A', strtotime($row['schedule_start_time'])),
        $services
    );
    $result = $sms->sendSMS($row['phone'], $msg, 'APPOINTMENT_REMINDER', [
        'user_id' => $row['user_id'],
        'customer_id' => $row['user_id'],
        'booking_id' => $row['booking_id'],
        'notification_key' => $key,
        'reference_id' => $row['booking_id']
    ]);
    $summary['appointment_reminders']++;
    recordResult($summary, $result);
}

// =========================================================================
// 5. WARRANTY EXPIRATION (30 and 7 days before)
// =========================================================================
foreach ([
    ['days' => WARRANTY_REMINDER_DAYS_30, 'label' => 'WARRANTY_30'],
    ['days' => WARRANTY_REMINDER_DAYS_7, 'label' => 'WARRANTY_7']
] as $threshold) {
    $targetDate = date('Y-m-d', strtotime('+' . $threshold['days'] . ' days'));

    $stmt = $pdo->prepare("
        SELECT
            w.id AS warranty_id,
            w.warranty_end,
            u.id AS user_id,
            u.phone,
            m.id AS motorcycle_id,
            m.brand,
            m.model
        FROM warranties w
        JOIN users u ON w.customer_id = u.id
        JOIN motorcycles m ON w.motorcycle_id = m.id
        WHERE w.status = 'active'
          AND w.warranty_end = ?
    ");
    $stmt->execute([$targetDate]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (empty($row['phone'])) continue;

        $key = $threshold['label'] . '_' . $row['warranty_id'];
        $msg = SMSTemplates::warrantyExpiration(
            $row['brand'],
            $row['model'],
            date('M j, Y', strtotime($row['warranty_end']))
        );
        $result = $sms->sendSMS($row['phone'], $msg, 'WARRANTY_EXPIRATION', [
            'user_id' => $row['user_id'],
            'customer_id' => $row['user_id'],
            'motorcycle_id' => $row['motorcycle_id'],
            'warranty_id' => $row['warranty_id'],
            'notification_key' => $key,
            'reference_id' => $row['warranty_end']
        ]);
        $summary['warranty_reminders']++;
        recordResult($summary, $result);
    }
}

// =========================================================================
// SUMMARY
// =========================================================================
$summary['completed'] = date('Y-m-d H:i:s');

$log = "\n=== SMS Scheduler Run ===\n";
$log .= "Started:  " . $summary['started'] . "\n";
$log .= "Ended:    " . $summary['completed'] . "\n";
$log .= "Maintenance reminders: " . $summary['maintenance_reminders'] . "\n";
$log .= "Overdue maintenance:   " . $summary['overdue_maintenance'] . "\n";
$log .= "Appointment conf.:     " . $summary['appointment_confirmations'] . "\n";
$log .= "Appointment reminders: " . $summary['appointment_reminders'] . "\n";
$log .= "Warranty reminders:    " . $summary['warranty_reminders'] . "\n";
$log .= "Sent:    " . $summary['sent'] . "\n";
$log .= "Failed:  " . $summary['failed'] . "\n";
$log .= "Skipped: " . $summary['skipped'] . "\n";
$log .= "=========================\n";

echo $log;

if (SMS_DEBUG) {
    error_log($log);
}
