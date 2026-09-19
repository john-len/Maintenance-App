# Customer Dashboard Features - Implementation Guide

## Overview
This implementation adds comprehensive vehicle health monitoring and maintenance tracking features to the customer dashboard, including:

- **Customer Name Display**: Shows the logged-in customer's name
- **Motorcycle Information**: Detailed vehicle cards with brand, model, year, plate number
- **Health Score**: Calculated vehicle health score (0-100) with visual indicators
- **Warranty Status**: Warranty tracking with expiration dates and countdown
- **Next Maintenance Schedule**: Maintenance scheduling with overdue alerts

## Files Added/Modified

### New Files Created:
1. **`motorcycle_health_helper.php`** - Core helper functions for health calculations
2. **`add_dashboard_fields.php`** - Database migration script to add required fields
3. **`add_dashboard_fields.sql`** - SQL script for manual database updates
4. **`test_dashboard_features.php`** - Testing script to verify implementation
5. **`check_db_structure.php`** - Database structure checker

### Modified Files:
1. **`dashboard_customer.php`** - Updated to display vehicle dashboard cards

## Installation Steps

### Step 1: Update Database Structure
Run the database migration script to add the required fields to the motorcycles table:

```bash
# Option 1: Run the PHP migration script
php add_dashboard_fields.php

# Option 2: Run the SQL script manually in your MySQL client
mysql -u root -p maintenance_db < add_dashboard_fields.sql
```

### Step 2: Test the Implementation
Run the test script to verify everything is working correctly:

```bash
php test_dashboard_features.php
```

### Step 3: Access the Dashboard
Log in as a customer and visit `dashboard_customer.php` to see the new features.

## Database Schema Changes

The following fields are added to the `motorcycles` table:

| Field | Type | Description |
|-------|------|-------------|
| `warranty_status` | ENUM('active', 'expired', 'none') | Current warranty status |
| `warranty_expiry_date` | DATE | Warranty expiration date |
| `last_maintenance_date` | DATE | Date of last maintenance service |
| `next_maintenance_date` | DATE | Scheduled next maintenance date |
| `maintenance_interval_months` | INT | Months between scheduled maintenance (default: 6) |
| `health_score` | INT | Vehicle health score (0-100, default: 100) |
| `last_service_mileage` | INT | Mileage at last service |

## Features Explained

### Health Score Calculation
The health score is calculated based on:
- **Vehicle Age**: 2 points deducted per year (max 20 points)
- **Mileage**: Points deducted based on mileage ranges
  - >50,000 km: -15 points
  - >30,000 km: -10 points  
  - >20,000 km: -5 points
- **Maintenance History**: 
  - 2+ services in past year: +5 points (bonus)
  - No recent service: -10 points (penalty)
- **Overdue Maintenance**: -1 point per 10 days overdue (max 15 points)

**Score Categories:**
- 85-100: Excellent (Green)
- 70-84: Good (Blue)
- 50-69: Fair (Orange)
- 0-49: Poor (Red)

### Warranty Status
- **Active**: Shows days remaining until expiration
- **Expired**: Shows expiration date and overdue status
- **None**: No warranty coverage

### Maintenance Schedule
- Calculates next maintenance date based on:
  - Explicitly set `next_maintenance_date`
  - `last_maintenance_date` + `maintenance_interval_months`
  - `purchase_date` + `maintenance_interval_months` (fallback)
- Shows countdown in days
- Alerts when maintenance is overdue

## Usage Examples

### Adding Warranty Information
```php
// In your motorcycle management code
UPDATE motorcycles 
SET warranty_status = 'active', 
    warranty_expiry_date = '2025-12-31' 
WHERE id = 1;
```

### Setting Maintenance Schedule
```php
// After completing a service
UPDATE motorcycles 
SET last_maintenance_date = CURDATE(),
    next_maintenance_date = DATE_ADD(CURDATE(), INTERVAL 6 MONTH),
    last_service_mileage = 25000
WHERE id = 1;
```

### Manual Health Score Override
```php
// If you want to set health score manually
UPDATE motorcycles 
SET health_score = 85 
WHERE id = 1;
```

## Customization

### Adjust Health Score Calculation
Edit the `calculateHealthScore()` function in `motorcycle_health_helper.php` to customize the scoring algorithm.

### Change Maintenance Intervals
Update the `maintenance_interval_months` field per motorcycle or change the default in the database schema.

### Modify Visual Styles
The CSS styles in `dashboard_customer.php` can be customized to match your branding.

## Troubleshooting

### Dashboard shows "No Vehicles Registered"
- Ensure the customer has motorcycles registered in the database
- Check that motorcycle status is 'active' (not 'archived')

### Health score shows 0 or unexpected values
- Verify that the new database fields were added successfully
- Run `check_db_structure.php` to verify database structure
- Check PHP error logs for calculation errors

### Warranty status not displaying correctly
- Ensure `warranty_status` and `warranty_expiry_date` fields are populated
- Verify date format is YYYY-MM-DD

### Maintenance schedule not calculating
- Check that `last_maintenance_date` or `purchase_date` is set
- Verify `maintenance_interval_months` has a value (default is 6)

## Security Considerations
- All user inputs are properly sanitized using `htmlspecialchars()`
- Database queries use prepared statements to prevent SQL injection
- Session validation ensures only customers can access their own data

## Future Enhancements
Potential improvements for future versions:
- Automated health score updates based on service history
- Email notifications for upcoming maintenance
- Warranty renewal reminders
- Historical health score tracking
- Comparison with similar vehicles
- Maintenance cost tracking

## Support
If you encounter any issues:
1. Run `test_dashboard_features.php` to diagnose problems
2. Check the database structure with `check_db_structure.php`
3. Review PHP error logs
4. Verify all files are in the correct directory

## Credits
This implementation extends the existing AutoCare Pro motorcycle maintenance system with advanced dashboard features for customer vehicle monitoring.