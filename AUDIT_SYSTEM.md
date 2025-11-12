# Loan Management API - Audit Trail System

## Overview

The audit trail system provides comprehensive tracking of loan notifications and system activities for administrator monitoring and reference. This system includes:

1. **Loan Notifications Tracking** - Records all SMS/email notifications sent for loans
2. **System Activities Tracking** - Records command executions and system operations
3. **Admin Dashboard Endpoints** - API endpoints for viewing audit data

## Database Tables

### loan_notifications
Tracks all loan-related notifications (SMS, email) with details about delivery status, amounts, and failure reasons.

### system_activities
Tracks system commands, automated tasks, and their execution results including success/failure counts and error details.

## API Endpoints

All audit endpoints are protected and require authentication. They are prefixed with `/api/v1/admin/audit/`.

### 1. Get Loan Notifications
```
GET /api/v1/admin/audit/loan-notifications
```

**Query Parameters:**
- `date_from` (optional) - Start date filter (YYYY-MM-DD)
- `date_to` (optional) - End date filter (YYYY-MM-DD)
- `status` (optional) - Filter by status (sent, failed, pending)
- `notification_type` (optional) - Filter by type (installment_reminder, overdue_notification, payment_confirmation, etc.)
- `channel` (optional) - Filter by channel (sms, email)
- `per_page` (optional) - Number of results per page (default: 50)

**Response:**
```json
{
  "success": true,
  "message": "Loan notifications retrieved successfully",
  "data": {
    "notifications": {
      "data": [...],
      "pagination": {...}
    },
    "statistics": {
      "total_notifications": 150,
      "sent_notifications": 140,
      "failed_notifications": 10,
      "success_rate": 93.33
    }
  }
}
```

### 2. Get System Activities
```
GET /api/v1/admin/audit/system-activities
```

**Query Parameters:**
- `date_from` (optional) - Start date filter
- `date_to` (optional) - End date filter
- `activity_type` (optional) - Filter by activity type
- `status` (optional) - Filter by status (started, completed, failed)
- `command_name` (optional) - Filter by command name
- `per_page` (optional) - Number of results per page

### 3. Get Recent Activities
```
GET /api/v1/admin/audit/recent-activities?limit=10
```

Returns recent notifications and system activities for dashboard display.

### 4. Get Notification Statistics
```
GET /api/v1/admin/audit/notification-stats
```

**Query Parameters:**
- `date_from` (optional) - Start date (default: 30 days ago)
- `date_to` (optional) - End date (default: today)

Returns detailed statistics grouped by date, notification type, channel, and status.

### 5. Get System Metrics
```
GET /api/v1/admin/audit/system-metrics
```

**Query Parameters:**
- `date_from` (optional) - Start date (default: 7 days ago)
- `date_to` (optional) - End date (default: today)

Returns daily performance metrics for both notifications and system activities.

## Command Integration

The audit system automatically tracks the following commands:

### Loan Installment Notifications
```bash
php artisan loans:notify-installments
php artisan loans:notify-installments --overdue
```

These commands now automatically:
1. Create system activity records
2. Track notification success/failure counts
3. Record detailed error information
4. Create individual loan notification records

## Usage Examples

### Check Recent Notification Activity
```bash
curl -X GET "https://your-api.com/api/v1/admin/audit/recent-activities?limit=20" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Get Failed Notifications from Last Week
```bash
curl -X GET "https://your-api.com/api/v1/admin/audit/loan-notifications?status=failed&date_from=2024-01-01" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Check System Command Performance
```bash
curl -X GET "https://your-api.com/api/v1/admin/audit/system-activities?command_name=loans:notify-installments" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Get Weekly Performance Metrics
```bash
curl -X GET "https://your-api.com/api/v1/admin/audit/system-metrics?date_from=2024-01-01&date_to=2024-01-07" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Model Methods

### LoanNotification Model
```php
// Get recent notifications
LoanNotification::getRecentNotifications(10);

// Get statistics for a specific date
LoanNotification::getStatistics(today());

// Mark notification as sent/failed
$notification->markAsSent();
$notification->markAsFailed('SMS delivery failed');
```

### SystemActivity Model
```php
// Log a new activity
SystemActivity::logActivity('command_executed', 'Running installment reminders', 'command', 'loans:notify-installments');

// Mark activity as completed
$activity->markAsCompleted(['notifications_sent' => 25]);

// Mark activity as failed
$activity->markAsFailed('Database connection error');

// Update counts
$activity->updateCounts(20, 5); // 20 success, 5 failures
```

## Production Monitoring

### Key Metrics to Monitor
1. **Notification Success Rate** - Should be >95%
2. **Command Execution Time** - Monitor for performance issues
3. **Failed Notifications** - Investigate patterns in failures
4. **System Activity Frequency** - Ensure commands are running as scheduled

### Alerting Thresholds
- Notification success rate below 90%
- Command execution time above 5 minutes
- More than 10 consecutive failed notifications
- No command execution for expected intervals

## Troubleshooting

### Common Issues
1. **High SMS Failure Rate** - Check Africa's Talking API credentials and balance
2. **Missing Notifications** - Verify queue workers are running
3. **Slow Command Execution** - Check database performance and indexes
4. **Missing System Activities** - Ensure commands are using the audit trail integration

### Debugging Steps
1. Check recent system activities for error details
2. Review failed loan notifications for patterns
3. Verify queue worker status: `supervisorctl status`
4. Check Laravel logs: `tail -f storage/logs/laravel.log`
5. Monitor database performance for slow queries

## Security Considerations

- All audit endpoints require authentication
- Sensitive data (phone numbers) are logged for debugging but should be handled according to privacy policies
- Consider data retention policies for audit logs
- Regular backup of audit data is recommended

## Performance Optimization

- Database indexes are created on frequently queried columns
- Pagination is implemented for large datasets
- Consider archiving old audit data (>1 year) to separate tables
- Monitor database growth and implement log rotation if needed