# Customer Modules Setup Guide

This guide will help you set up the new customer-facing modules for the motorcycle maintenance system.

## New Modules Added

### 1. Maintenance History Module
- **File**: `customer_maintenance_history.php`
- **Admin File**: `admin_maintenance_history.php`
- **Features**:
  - View previous services with date, mileage, parts replaced, cost, and mechanic remarks
  - Add, edit, and delete maintenance records
  - Filter by motorcycle
  - Statistics dashboard (total spent, highest mileage, services this year)

### 2. Warranty Information Module
- **File**: `customer_warranty_info.php`
- **Admin File**: `admin_warranty.php` (already exists)
- **Features**:
  - View warranty start and end dates
  - Check remaining days and warranty status
  - View warranty claims and their status
  - Coverage details and terms

### 3. Motorcycle Health Score Module
- **File**: `customer_health_score.php`
- **Admin File**: `admin_health_scores.php`
- **Features**:
  - Overall health score (0-100)
  - Condition status (Excellent, Good, Fair, Poor)
  - Health factors analysis (age, mileage, maintenance history)
  - Maintenance recommendations
  - Visual health gauge

### 4. Emergency Service Request Module
- **File**: `customer_emergency_service.php`
- **Admin File**: `admin_emergency_requests.php`
- **Features**:
  - Submit emergency service requests with problem description, issue type, image, location, and contact number
  - Track request status and response updates
  - Priority levels (Low, Medium, High, Urgent)
  - Timeline of responses and updates

## Database Setup

### Step 1: Run the Setup Script

Open your browser and navigate to:
```
http://localhost/Advance_Database-System/setup_customer_modules_tables.php
```

This will create the following tables:
- `maintenance_history` - Stores all maintenance records
- `emergency_service_requests` - Stores emergency service requests
- `motorcycle_health_scores` - Stores historical health score data
- `emergency_request_updates` - Stores updates for emergency requests

### Step 2: Verify Tables

The setup script will confirm successful creation of all tables. If you encounter any errors, check:
- Database connection in `db.php`
- MySQL server is running
- User has sufficient permissions

## Integration Points

### Customer Dashboard Integration

The customer dashboard (`dashboard_customer.php`) has been updated with new action cards:
- **Maintenance History** - Links to `customer_maintenance_history.php`
- **Warranty Info** - Links to `customer_warranty_info.php`
- **Health Score** - Links to `customer_health_score.php`
- **Emergency Service** - Links to `customer_emergency_service.php`

### Admin Sidebar Integration

The admin sidebar (`admin_sidebar_template.php`) has been updated with new menu items:
- **Maintenance History** - Links to `admin_maintenance_history.php`
- **Health Scores** - Links to `admin_health_scores.php`
- **Emergency Requests** - Links to `admin_emergency_requests.php`

## File Structure

```
Advance_Database-System/
├── customer_maintenance_history.php      # Customer maintenance history
├── customer_warranty_info.php            # Customer warranty information
├── customer_health_score.php             # Customer health scores
├── customer_emergency_service.php        # Customer emergency requests
├── admin_maintenance_history.php         # Admin maintenance management
├── admin_health_scores.php               # Admin health score monitoring
├── admin_emergency_requests.php          # Admin emergency request management
├── create_customer_modules_tables.sql     # SQL schema
├── setup_customer_modules_tables.php     # Setup script
└── motorcycle_health_helper.php           # Health calculation functions (existing)
```

## Features Overview

### Maintenance History
- **Customer View**: Personal maintenance records with filtering and statistics
- **Admin View**: All maintenance records with customer/motorcycle filtering
- **Data Points**: Service date, mileage, service type, parts replaced, cost, mechanic remarks, performed by

### Warranty Information
- **Customer View**: Personal warranty status and claims
- **Admin View**: Full warranty management (existing in admin_warranty.php)
- **Data Points**: Warranty type, start/end dates, coverage details, remaining days, status

### Health Score
- **Customer View**: Personal motorcycle health with recommendations
- **Admin View**: All motorcycle health scores for monitoring
- **Calculation Factors**: Age, mileage, maintenance history, overdue maintenance
- **Score Range**: 0-100 (Excellent: 80+, Good: 60-79, Fair: 40-59, Poor: <40)

### Emergency Service
- **Customer View**: Submit requests and track status
- **Admin View**: Manage requests, assign mechanics, add updates
- **Data Points**: Problem description, issue type, image, location, contact number, priority
- **Workflow**: Pending → Assigned → In Progress → Completed/Cancelled

## Security Features

- All modules include session-based role checking
- Customers can only access their own data
- Admins have full access to all records
- Input validation and SQL injection prevention
- File upload validation for emergency images

## Customization Options

### Styling
All modules use consistent styling with the existing design system:
- Color scheme matches existing theme
- Responsive Bootstrap 5 layout
- Modern card-based UI
- Gradient headers and badges

### Health Score Calculation
You can customize the health score algorithm in `motorcycle_health_helper.php`:
- Adjust age deduction rate
- Modify mileage thresholds
- Change maintenance frequency requirements
- Update overdue maintenance penalties

## Troubleshooting

### Tables Not Created
- Check database connection in `db.php`
- Ensure MySQL server is running
- Verify user permissions

### Images Not Uploading
- Create `uploads/emergency_requests/` directory
- Set proper permissions (755)
- Check PHP upload_max_filesize setting

### Health Scores Not Calculating
- Ensure `motorcycle_health_helper.php` is included
- Check that motorcycles have required data (purchase_date, current_mileage)
- Verify maintenance history data exists

## Future Enhancements

Potential improvements for future versions:
- Email notifications for emergency requests
- SMS integration for warranty expiry reminders
- PDF export for maintenance history
- Chart visualization for health score trends
- Mobile app integration
- Service reminders based on health scores

## Support

For issues or questions:
1. Check the error logs in your PHP error log
2. Verify database table structure
3. Test database connection
4. Review file permissions

## Database Schema Reference

### maintenance_history
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- motorcycle_id (INT)
- customer_id (INT)
- service_date (DATE)
- mileage (INT)
- service_type (VARCHAR)
- parts_replaced (TEXT)
- cost (DECIMAL)
- mechanic_remarks (TEXT)
- performed_by (VARCHAR)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

### emergency_service_requests
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- customer_id (INT)
- motorcycle_id (INT)
- problem_description (TEXT)
- motorcycle_issue (VARCHAR)
- image_path (VARCHAR)
- location (VARCHAR)
- contact_number (VARCHAR)
- request_status (ENUM)
- priority (ENUM)
- assigned_mechanic_id (INT)
- response_updates (TEXT)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

### motorcycle_health_scores
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- motorcycle_id (INT)
- health_score (INT)
- condition_status (VARCHAR)
- maintenance_recommendations (TEXT)
- score_date (DATE)
- factors (JSON)
- created_at (TIMESTAMP)
```

### emergency_request_updates
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- request_id (INT)
- update_type (VARCHAR)
- update_message (TEXT)
- updated_by (VARCHAR)
- created_at (TIMESTAMP)
```

---

**Note**: Always backup your database before running setup scripts and test in a development environment first.
