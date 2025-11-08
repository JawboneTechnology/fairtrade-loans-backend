# M-Pesa Integration Documentation

This project includes a comprehensive M-Pesa integration for handling mobile money payments in Kenya.

## Setup

### 1. Environment Variables

Copy the M-Pesa configuration from `.env.mpesa.example` to your `.env` file and update with your actual credentials:

```bash
# Get credentials from Safaricom Developer Portal
MPESA_CONSUMER_KEY=your_actual_consumer_key
MPESA_CONSUMER_SECRET=your_actual_consumer_secret
```

### 2. Get M-Pesa Credentials

1. Visit [Safaricom Developer Portal](https://developer.safaricom.co.ke/)
2. Create an account and login
3. Create a new app
4. Get your Consumer Key and Consumer Secret
5. For production, get your business shortcode and passkey

### 3. Database Migration

The M-Pesa transactions table has been created. It includes:

-   Transaction tracking
-   Status management
-   User and loan associations
-   Callback data storage

## Payment Methods

This system supports two methods for M-Pesa loan payments:

### Method 1: App-Based Payments (STK Push)

Users initiate payments directly from the mobile app using their loan ID and amount.

### Method 2: Paybill Payments

Users make payments via M-Pesa paybill using their phone, entering the paybill number and account reference (loan number or employee ID).

## API Endpoints

### Authenticated Endpoints

#### Method 1: Initiate Loan Payment via App

```
POST /api/v1/mpesa/loan-payment
```

**Request Body:**

```json
{
    "phone_number": "254712345678",
    "amount": 5000.0,
    "loan_id": "uuid-of-loan"
}
```

**Response:**

```json
{
    "success": true,
    "message": "STK Push initiated successfully",
    "data": {
        "transaction_id": "uuid",
        "checkout_request_id": "ws_CO_12345",
        "merchant_request_id": "12345"
    }
}
```

#### General STK Push Payment

```
POST /api/v1/mpesa/stk-push
```

**Request Body:**

```json
{
    "phone_number": "254712345678",
    "amount": 100.0,
    "account_reference": "LOAN123",
    "transaction_description": "Loan payment",
    "loan_id": "uuid-of-loan" // Optional
}
```

**Response:**

```json
{
    "success": true,
    "message": "STK Push initiated successfully",
    "data": {
        "transaction_id": "uuid",
        "checkout_request_id": "ws_CO_12345",
        "merchant_request_id": "12345"
    }
}
```

#### Get Loan Payment Information (for Paybill users)

```
GET /api/v1/mpesa/loan-info/{loan_identifier}
```

Where `loan_identifier` can be:

-   Loan number (e.g., "FTL00001/2025")
-   Employee ID (e.g., "EMP001")

**Response:**

```json
{
    "success": true,
    "message": "Loan information retrieved successfully",
    "data": {
        "loan_id": "uuid",
        "loan_number": "FTL00001/2025",
        "loan_amount": 100000.0,
        "loan_balance": 75000.0,
        "monthly_installment": 8500.0,
        "next_due_date": "2025-11-15",
        "borrower_name": "John Doe",
        "employee_id": "EMP001",
        "phone_number": "254712345678",
        "paybill_number": "174379",
        "account_reference_options": {
            "loan_number": "FTL00001/2025",
            "employee_id": "EMP001"
        }
    }
}
```

#### Get User Transactions

```
GET /api/v1/mpesa/transactions?limit=20
```

#### Query Transaction Status

```
POST /api/v1/mpesa/query-status
```

**Request Body:**

```json
{
    "checkout_request_id": "ws_CO_12345"
}
```

### Callback Endpoints (No authentication required)

These endpoints are called by Safaricom:

-   `POST /api/v1/mpesa/stk/callback` - STK Push callback
-   `POST /api/v1/mpesa/c2b/validation` - C2B validation
-   `POST /api/v1/mpesa/c2b/confirmation` - C2B confirmation
-   `POST /api/v1/mpesa/b2c/result` - B2C result
-   `POST /api/v1/mpesa/b2c/timeout` - B2C timeout
-   And more...

## Usage Examples

### Method 1: App-Based Loan Payment

#### 1. Initiate Loan Payment via App

```php
// In your controller or service
$response = $mpesaService->initiateStkPush([
    'phone_number' => '254712345678',
    'amount' => 5000.00,
    'account_reference' => $loan->loan_number,
    'transaction_description' => "Loan payment for {$loan->loan_number}",
    'user_id' => auth()->id(),
    'loan_id' => $loan->id,
    'payment_method' => 'APP'
]);
```

### Method 2: Paybill Payment Process

#### 1. Customer Uses M-Pesa Paybill

**Step 1**: Customer opens M-Pesa menu on their phone  
**Step 2**: Selects "Lipa na M-Pesa" → "Pay Bill"  
**Step 3**: Enters:

-   **Business Number**: 174379 (your paybill number)
-   **Account Number**: Their loan number (e.g., "FTL00001/2025") OR employee ID (e.g., "EMP001")
-   **Amount**: Payment amount

**Step 4**: Enters M-Pesa PIN to complete payment

#### 2. System Processing (Automatic)

```php
// The system automatically:
// 1. Validates the account number (loan number or employee ID)
// 2. Confirms the loan exists and is active
// 3. Processes the payment if validation passes
// 4. Updates the loan balance
// 5. Creates transaction records
```

#### 3. Get Loan Information for Customers

```php
// Customers can get their loan info for paybill payments
$loanInfo = $mpesaService->getLoanPaymentInfo('FTL00001/2025');
// OR
$loanInfo = $mpesaService->getLoanPaymentInfo('EMP001');
```

### 2. Check Transaction Status

```php
$transaction = MpesaTransaction::where('checkout_request_id', $checkoutId)->first();

if ($transaction->isSuccessful()) {
    // Payment completed
} elseif ($transaction->isPending()) {
    // Still waiting for payment
} elseif ($transaction->isFailed()) {
    // Payment failed
}
```

### 3. Get User's Payment History

```php
$transactions = $mpesaService->getUserTransactions(auth()->id(), 50);
```

## Transaction Flows

### Method 1: App-Based Payment Flow

1. **User Action**: User opens app, selects loan, enters amount and phone number
2. **Initiate Payment**: App calls `/api/v1/mpesa/loan-payment` endpoint
3. **Validation**: System validates loan ownership and payment amount
4. **STK Push**: System sends STK push to customer's phone
5. **Customer Action**: Customer enters M-Pesa PIN to authorize payment
6. **Callback**: M-Pesa sends result to `/api/v1/mpesa/stk/callback`
7. **Processing**: System updates transaction status and loan balance
8. **Notification**: User receives payment confirmation in app

### Method 2: Paybill Payment Flow

1. **Customer Action**: Customer uses M-Pesa paybill on their phone
2. **Input Details**: Enters paybill number, account reference (loan number/employee ID), and amount
3. **Validation**: M-Pesa calls `/api/v1/mpesa/c2b/validation` for validation
4. **System Validation**: System validates loan number/employee ID and amount
5. **Authorization**: If valid, customer enters M-Pesa PIN
6. **Confirmation**: M-Pesa calls `/api/v1/mpesa/c2b/confirmation` with payment details
7. **Processing**: System processes payment and updates loan balance
8. **Completion**: Payment is recorded and customer receives M-Pesa receipt

### Payment Method Comparison

| Feature               | App-Based (Method 1)    | Paybill (Method 2)             |
| --------------------- | ----------------------- | ------------------------------ |
| **User Experience**   | Requires app login      | Direct M-Pesa menu             |
| **Loan Selection**    | Visual loan selection   | Manual entry of loan number    |
| **Amount Validation** | Pre-validated in app    | Validated during payment       |
| **Convenience**       | High (guided process)   | Medium (manual entry)          |
| **Accessibility**     | Requires smartphone app | Works on any phone with M-Pesa |
| **Error Handling**    | Pre-payment validation  | Payment-time validation        |

## Transaction Statuses

-   `PENDING`: Transaction initiated, waiting for customer action
-   `SUCCESS`: Payment completed successfully
-   `FAILED`: Payment failed or was cancelled
-   `CANCELLED`: Transaction was cancelled

## SMS Notifications

After successful payment processing, the system automatically sends SMS notifications to users with payment confirmation and updated loan balance.

### SMS Notification Features

-   **Automatic Triggers**: SMS sent after successful payment processing
-   **Event-Driven**: Uses Laravel events and listeners for async processing
-   **Robust Delivery**: Queue-based SMS sending with retry mechanism
-   **Payment Method Awareness**: Different messages for app vs paybill payments
-   **Comprehensive Details**: Includes payment amount, loan balance, receipt number, and timestamp

### SMS Message Format

```
Dear [Customer Name], your payment of KES [Amount] for loan [Loan Number] has been received successfully.
Receipt: [Receipt Number]. New loan balance: KES [New Balance].
Payment processed on [Date/Time] via [Payment Method]. Thank you for choosing our services!
```

### SMS Notification Flow

1. **Payment Processing**: Successful payment updates loan balance
2. **Event Trigger**: `PaymentSuccessful` event is fired
3. **Listener Execution**: `SendPaymentSuccessNotification` listener handles event
4. **SMS Dispatch**: Message sent via Africa's Talking SMS service
5. **Logging**: All SMS activities logged for audit

### Testing SMS Notifications

```
POST /api/v1/mpesa/test-notification
```

**Request Body:**

```json
{
    "transaction_id": "your-transaction-uuid",
    "test_mode": true // Returns SMS content without sending
}
```

**Response (Test Mode):**

```json
{
    "success": true,
    "message": "Test mode - SMS content generated",
    "data": {
        "recipient": "254712345678",
        "sms_content": "Dear John Doe, your payment of KES 5,000.00...",
        "payment_amount": 5000.0,
        "loan_balance": 45000.0
    }
}
```

## Security Notes

1. **Callback URLs**: Ensure your callback URLs are accessible from the internet
2. **Validation**: Always validate callback data from M-Pesa
3. **Logging**: All transactions are logged for audit purposes
4. **Idempotency**: Handle duplicate callbacks gracefully

## Paybill Setup Instructions

### For Customers (How to make paybill payments)

**Via M-Pesa App:**

1. Open M-Pesa app
2. Tap "Lipa na M-Pesa"
3. Select "Pay Bill"
4. Enter Business Number: **174379** (replace with your production paybill)
5. Account Number: Enter your **Loan Number** (e.g., FTL00001/2025) OR **Employee ID** (e.g., EMP001)
6. Amount: Enter payment amount
7. Enter M-Pesa PIN to complete

**Via Phone (\*334#):**

1. Dial *334# or *384\*488#
2. Select "Pay Bill" option
3. Enter Business Number: **174379**
4. Enter Account Number: **Loan Number** or **Employee ID**
5. Enter Amount and M-Pesa PIN

### For Admin Setup

1. **Register C2B URLs** (Production only):

    ```php
    // Register validation and confirmation URLs with Safaricom
    $mpesaService->registerC2BUrls();
    ```

2. **Configure Paybill Settings**:

    - Ensure `MPESA_BUSINESS_SHORTCODE` is set to your paybill number
    - Set C2B callback URLs in environment variables
    - Test validation and confirmation endpoints

3. **Customer Communication**:
    - Share paybill number with customers
    - Inform them they can use either loan number or employee ID
    - Provide customer service for payment issues

## Production Deployment

### Pre-Production Checklist

-   [ ] Update environment to `production`
-   [ ] Use production shortcodes and passkeys
-   [ ] Ensure callback URLs use HTTPS
-   [ ] Test both payment methods thoroughly in sandbox
-   [ ] Register C2B URLs with Safaricom (for paybill)
-   [ ] Set up monitoring and logging
-   [ ] Train customer service team on both payment methods

### Go-Live Steps

1. **Switch Environment**: Change `MPESA_ENVIRONMENT=production`
2. **Update Credentials**: Use production consumer key/secret
3. **Configure URLs**: Ensure all callback URLs use HTTPS
4. **Register Paybill**: Register C2B URLs with Safaricom for Method 2
5. **Setup SMS Service**: Configure Africa's Talking credentials for notifications
6. **Setup Queues**: Ensure queue workers are running for SMS processing
7. **Monitor**: Watch logs for both payment flows and SMS delivery
8. **Customer Support**: Provide guidance for both payment methods

## SMS Configuration

### Environment Variables

Ensure these are set in your `.env` file:

```bash
# Africa's Talking SMS Configuration
AFRICASTALKING_USERNAME=your_username
AFRICASTALKING_API_KEY=your_api_key

# Queue Configuration
QUEUE_CONNECTION=database  # or redis/rabbitmq for production
```

### Queue Workers

Start queue workers to process SMS jobs:

```bash
# Development
php artisan queue:work

# Production (with supervisor)
php artisan queue:work --queue=default,sms --tries=3 --timeout=60
```

### SMS Monitoring

Monitor SMS delivery via logs:

```bash
# Filter SMS-related logs
tail -f storage/logs/laravel.log | grep -i "sms\|payment.*success"

# Check queue status
php artisan queue:monitor
```

## Troubleshooting

### Common Issues

1. **"Invalid Access Token"**: Check consumer key/secret
2. **"Invalid shortcode"**: Verify business shortcode
3. **"Callback not received"**: Check callback URL accessibility
4. **"Transaction not found"**: Verify checkout request ID

### Logs

Check Laravel logs for M-Pesa related entries:

```bash
tail -f storage/logs/laravel.log | grep -i mpesa
```

## Support

For M-Pesa API support, contact Safaricom Developer Support or check their documentation at [https://developer.safaricom.co.ke/docs](https://developer.safaricom.co.ke/docs).
