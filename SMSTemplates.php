<?php
// Centralized SMS message templates for AutoCare Pro

class SMSTemplates {

    private static function replace($template, $values) {
        foreach ($values as $key => $value) {
            $template = str_replace("{" . $key . "}", $value, $template);
        }
        return $template;
    }

    public static function maintenanceReminderDate($brand, $model, $date) {
        $template = "AutoCare Pro: Reminder: Your {brand} {model} is scheduled for preventive maintenance on {date}. Please schedule your service with Mindanao Eversure Corporation.";
        return self::replace($template, ['brand' => $brand, 'model' => $model, 'date' => $date]);
    }

    public static function maintenanceReminderKm($brand, $model, $interval, $currentMileage) {
        $template = "AutoCare Pro: Your {brand} {model} is approaching its {interval} KM preventive maintenance. Current recorded mileage: {current_mileage} KM. Please schedule your service.";
        return self::replace($template, [
            'brand' => $brand,
            'model' => $model,
            'interval' => $interval,
            'current_mileage' => $currentMileage
        ]);
    }

    public static function overdueMaintenanceDate($brand, $model) {
        $template = "AutoCare Pro: Your {brand} {model} is overdue for preventive maintenance. Please schedule your service with Mindanao Eversure Corporation to keep your motorcycle properly maintained.";
        return self::replace($template, ['brand' => $brand, 'model' => $model]);
    }

    public static function overdueMaintenanceKm($brand, $model, $interval, $currentMileage) {
        $template = "AutoCare Pro: Your {brand} {model} is overdue for its {interval} KM preventive maintenance. Current recorded mileage: {current_mileage} KM. Please schedule your service.";
        return self::replace($template, [
            'brand' => $brand,
            'model' => $model,
            'interval' => $interval,
            'current_mileage' => $currentMileage
        ]);
    }

    public static function appointmentConfirmation($bookingId, $date, $time, $service) {
        $template = "AutoCare Pro: Your motorcycle service appointment has been confirmed for {date} at {time}. Service: {service}. Please arrive on time. - Mindanao Eversure Corporation";
        return self::replace($template, [
            'booking_id' => $bookingId,
            'date' => $date,
            'time' => $time,
            'service' => $service
        ]);
    }

    public static function appointmentReminder($date, $time, $service) {
        $template = "AutoCare Pro: Reminder: Your motorcycle service appointment is tomorrow, {date} at {time}. Service: {service}. We look forward to serving you. - Mindanao Eversure Corporation";
        return self::replace($template, [
            'date' => $date,
            'time' => $time,
            'service' => $service
        ]);
    }

    public static function warrantyExpiration($brand, $model, $date) {
        $template = "AutoCare Pro: Reminder: The warranty for your {brand} {model} will expire on {date}. Please contact Mindanao Eversure Corporation for applicable warranty concerns before the expiration date.";
        return self::replace($template, [
            'brand' => $brand,
            'model' => $model,
            'date' => $date
        ]);
    }
}
