# Testing Employee Notifications

This guide explains how to test all employee notification types that have been implemented.

## Quick Test (All Notification Types)

Run this command to test all notification types at once:

```bash
php artisan tinker --execute="
\$user = App\Models\User::first();
\$service = app(App\Services\NotificationService::class);

\$types = [
    'loan_application_submitted' => ['loan_id' => 'test-1', 'loan_number' => 'LOAN-TEST-001', 'amount' => '50,000.00', 'loan_type' => 'Test', 'guarantors' => 'Test', 'action_url' => config('app.url') . '/loans/test'],
    'loan_approved' => ['loan_id' => 'test-2', 'loan_number' => 'LOAN-TEST-002', 'amount' => '50,000.00', 'approved_amount' => '45,000.00', 'remarks' => 'Test', 'action_url' => config('app.url') . '/loans/test'],
    'loan_rejected' => ['loan_id' => 'test-3', 'loan_number' => 'LOAN-TEST-003', 'amount' => '50,000.00', 'remarks' => 'Test', 'action_url' => config('app.url') . '/loans/test'],
    'loan_paid' => ['loan_id' => 'test-4', 'loan_number' => 'LOAN-TEST-004', 'amount' => '50,000.00', 'action_url' => config('app.url') . '/loans/test'],
    'loan_canceled' => ['loan_id' => 'test-5', 'loan_number' => 'LOAN-TEST-005', 'amount' => '50,000.00', 'action_url' => config('app.url') . '/loans/test'],
    'grant_approved' => ['grant_id' => 'test-6', 'grant_number' => 'GRANT-TEST-001', 'amount' => '25,000.00', 'admin_notes' => 'Test', 'action_url' => config('app.url') . '/grants/test'],
    'grant_rejected' => ['grant_id' => 'test-7', 'grant_number' => 'GRANT-TEST-002', 'amount' => '25,000.00', 'remarks' => 'Test', 'action_url' => config('app.url') . '/grants/test'],
    'payment_received' => ['transaction_id' => 'TXN-TEST-001', 'loan_id' => 'test-8', 'loan_number' => 'LOAN-TEST-006', 'amount' => '5,000.00', 'new_balance' => '45,000.00', 'payment_method' => 'M-Pesa', 'action_url' => config('app.url') . '/loans/test'],
    'deduction_processed' => ['deduction_id' => 'test-9', 'loan_id' => 'test-10', 'loan_number' => 'LOAN-TEST-007', 'amount' => '3,000.00', 'new_balance' => '42,000.00', 'deduction_type' => 'Salary', 'action_url' => config('app.url') . '/loans/test']
];

foreach (\$types as \$type => \$data) {
    \$service->create(\$user, \$type, \$data);
}

echo 'All notifications created! Unread count: ' . \$service->getUnreadCount(\$user);
"
```

## Test Individual Notification Types

### 1. Test Loan Application Submitted

```bash
php artisan tinker --execute="
\$user = App\Models\User::first();
\$service = app(App\Services\NotificationService::class);
\$service->create(\$user, 'loan_application_submitted', [
    'loan_id' => 'test-1',
    'loan_number' => 'LOAN-TEST-001',
    'amount' => '50,000.00',
    'loan_type' => 'Test Loan Type',
    'guarantors' => 'Test Guarantor',
    'action_url' => config('app.url') . '/loans/test'
]);
echo 'Notification created!';
"
```

### 2. Test Loan Approved

```bash
php artisan tinker --execute="
\$user = App\Models\User::first();
\$loan = App\Models\Loan::where('employee_id', \$user->id)->first();
if (\$loan) {
    App\Events\LoanApproved::dispatch(\$loan, 45000.00, 'Test approval');
    echo 'LoanApproved event dispatched!';
} else {
    \$service = app(App\Services\NotificationService::class);
    \$service->create(\$user, 'loan_approved', [
        'loan_id' => 'test',
        'loan_number' => 'LOAN-TEST',
        'amount' => '50,000.00',
        'approved_amount' => '45,000.00',
        'remarks' => 'Test',
        'action_url' => config('app.url') . '/loans/test'
    ]);
    echo 'Notification created!';
}
"
```

### 3. Test Loan Rejected

```bash
php artisan tinker --execute="
\$user = App\Models\User::first();
\$loan = App\Models\Loan::where('employee_id', \$user->id)->first();
if (\$loan) {
    App\Events\LoanRejected::dispatch(\$loan, 'Test rejection');
    echo 'LoanRejected event dispatched!';
} else {
    \$service = app(App\Services\NotificationService::class);
    \$service->create(\$user, 'loan_rejected', [
        'loan_id' => 'test',
        'loan_number' => 'LOAN-TEST',
        'amount' => '50,000.00',
        'remarks' => 'Test rejection',
        'action_url' => config('app.url') . '/loans/test'
    ]);
    echo 'Notification created!';
}
"
```

### 4. Test Grant Approved

```bash
php artisan tinker --execute="
\$user = App\Models\User::first();
\$grant = App\Models\Grant::where('user_id', \$user->id)->first();
if (\$grant) {
    App\Events\GrantApproved::dispatch(\$grant, 'Test approval');
    echo 'GrantApproved event dispatched!';
} else {
    \$service = app(App\Services\NotificationService::class);
    \$service->create(\$user, 'grant_approved', [
        'grant_id' => 'test',
        'grant_number' => 'GRANT-TEST',
        'amount' => '25,000.00',
        'admin_notes' => 'Test',
        'action_url' => config('app.url') . '/grants/test'
    ]);
    echo 'Notification created!';
}
"
```

### 5. Test Payment Received

```bash
php artisan tinker --execute="
\$user = App\Models\User::first();
\$loan = App\Models\Loan::where('employee_id', \$user->id)->first();
\$transaction = App\Models\MpesaTransaction::first();
if (\$loan && \$transaction) {
    App\Events\PaymentSuccessful::dispatch(\$transaction, \$loan, \$user, 45000.00, 'M-Pesa');
    echo 'PaymentSuccessful event dispatched!';
} else {
    \$service = app(App\Services\NotificationService::class);
    \$service->create(\$user, 'payment_received', [
        'transaction_id' => 'TXN-TEST',
        'loan_id' => 'test',
        'loan_number' => 'LOAN-TEST',
        'amount' => '5,000.00',
        'new_balance' => '45,000.00',
        'payment_method' => 'M-Pesa',
        'action_url' => config('app.url') . '/loans/test'
    ]);
    echo 'Notification created!';
}
"
```

## Test via API Endpoints

### Get All Notifications
```bash
GET /api/v1/notifications
Authorization: Bearer {token}
```

### Get Unread Notifications
```bash
GET /api/v1/notifications/unread
Authorization: Bearer {token}
```

### Get Unread Count
```bash
GET /api/v1/notifications/unread-count
Authorization: Bearer {token}
```

### Test Notification Endpoint
```bash
POST /api/v1/notifications/test
Authorization: Bearer {token}
```

## Testing Real Events

To test with actual events (which will trigger both SMS and database notifications):

1. **Loan Approval**: Approve a loan via the admin panel or API
2. **Loan Rejection**: Reject a loan via the admin panel or API
3. **Grant Approval**: Approve a grant via the admin panel or API
4. **Payment**: Process a payment for a loan
5. **Deduction**: Process a deduction for a loan

## Verify WebSocket Broadcasting

1. Connect your React app to the WebSocket channel: `private-notifications.{userId}`
2. Listen for the event: `.notification.new`
3. When a notification is created, you should receive it in real-time

## Check Notification Templates

Make sure notification templates are seeded:

```bash
php artisan db:seed --class=NotificationSeeder
```

## Expected Results

After running tests, you should see:
- ✅ Notifications created in the `notifications` table
- ✅ Notifications broadcasted via WebSocket (if configured)
- ✅ Notifications visible via API endpoints
- ✅ Unread count updated correctly

## Troubleshooting

1. **Notifications not appearing**: Check if templates are seeded
2. **WebSocket not working**: Verify Reverb/Pusher configuration
3. **Events not triggering**: Check EventServiceProvider for listener registration
4. **Database errors**: Ensure migrations are run and tables exist

