# M-Pesa Test Controller - Quick Start Guide

## 🚀 Quick Start

This guide provides a quick reference for testing M-Pesa integrations using Postman.

## 📋 Available Test Endpoints

| # | Endpoint | Method | Purpose |
|---|----------|--------|---------|
| 1 | `/api/v1/mpesa/test-token` | GET | Test access token generation |
| 2 | `/api/v1/mpesa/test-stk-push` | POST | Test STK Push (Lipa Na M-Pesa) |
| 3 | `/api/v1/mpesa/test-b2c` | POST | Test B2C payment |
| 4 | `/api/v1/mpesa/test-c2b` | GET | Test C2B registration |
| 5 | `/api/v1/mpesa/test-c2b-validation` | POST | Test C2B validation callback |
| 6 | `/api/v1/mpesa/test-c2b-confirmation` | POST | Test C2B confirmation callback |
| 7 | `/api/v1/mpesa/test-b2c-result` | POST | Test B2C result callback |
| 8 | `/api/v1/mpesa/test-b2c-timeout` | POST | Test B2C timeout callback |
| 9 | `/api/v1/mpesa/test-transactions` | GET | View recent transactions |

## 🔧 Setup

### 1. Configure Environment Variables

Ensure your `.env` file has these variables:

```env
MPESA_ENVIRONMENT=sandbox
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_BUSINESS_SHORTCODE=174379
SAFARICOM_PASSKEY=your_passkey
```

### 2. Start Your Laravel Server

```bash
php artisan serve
```

Your base URL will be: `http://localhost:8000`

## 📱 Test in Postman

### Test 1: Access Token (GET)
```
GET http://localhost:8000/api/v1/mpesa/test-token
```
**No body required**

### Test 2: STK Push (POST)
```
POST http://localhost:8000/api/v1/mpesa/test-stk-push
Content-Type: application/json

{
  "phone_number": "254712345678",
  "amount": 100,
  "account_reference": "TEST-001",
  "transaction_description": "Test Payment"
}
```

### Test 3: B2C Payment (POST)
```
POST http://localhost:8000/api/v1/mpesa/test-b2c
Content-Type: application/json

{
  "phone_number": "254712345678",
  "amount": 100,
  "command_id": "BusinessPayment",
  "remarks": "Test B2C Payment",
  "occasion": "Testing"
}
```

### Test 4: C2B Registration (GET)
```
GET http://localhost:8000/api/v1/mpesa/test-c2b
```
**No body required**

### Test 5: C2B Validation (POST)
```
POST http://localhost:8000/api/v1/mpesa/test-c2b-validation
Content-Type: application/json

{
  "TransactionType": "Pay Bill",
  "TransID": "OEI2AK4Q16",
  "TransTime": "20230615143000",
  "TransAmount": "100.00",
  "BusinessShortCode": "174379",
  "BillRefNumber": "LOAN-001",
  "MSISDN": "254712345678",
  "FirstName": "John",
  "LastName": "Smith"
}
```

### Test 6: View Transactions (GET)
```
GET http://localhost:8000/api/v1/mpesa/test-transactions
```
**No body required**

## 📝 View Logs

All tests generate detailed logs. View them in real-time:

```bash
tail -f storage/logs/laravel.log
```

Or filter for specific tests:

```bash
# STK Push logs
grep "STK PUSH" storage/logs/laravel.log

# B2C logs
grep "B2C" storage/logs/laravel.log

# C2B logs
grep "C2B" storage/logs/laravel.log
```

## 🔍 Log Markers

Each test has clear log markers for easy searching:

- `=== M-PESA ACCESS TOKEN TEST STARTED ===`
- `=== STK PUSH TEST STARTED ===`
- `=== B2C PAYMENT TEST STARTED ===`
- `=== C2B REGISTRATION TEST STARTED ===`
- `=== C2B VALIDATION TEST STARTED ===`
- `=== C2B CONFIRMATION TEST STARTED ===`
- `=== B2C RESULT CALLBACK TEST STARTED ===`
- `=== B2C TIMEOUT CALLBACK TEST STARTED ===`

## ✅ Recommended Testing Sequence

### Step 1: Verify Configuration
1. Test Access Token → Should return success with token info

### Step 2: Test STK Push Flow
1. Test STK Push → Should send prompt to phone
2. Accept on phone
3. Check logs for callback
4. View in test-transactions

### Step 3: Test C2B Flow
1. Test C2B Registration → Register URLs with Safaricom
2. Test C2B Validation → Verify validation logic
3. Test C2B Confirmation → Verify payment processing
4. View in test-transactions

### Step 4: Test B2C Flow
1. Test B2C Payment → Initiate disbursement
2. Test B2C Result → Simulate success callback
3. Test B2C Timeout → Simulate timeout
4. View in test-transactions

## 🎯 Expected Results

### ✅ Success Response Example
```json
{
  "success": true,
  "message": "STK Push initiated successfully",
  "data": {
    "transaction_id": "TXN123456",
    "checkout_request_id": "ws_CO_15062023143000000001"
  },
  "timestamp": "2023-06-15 14:30:00"
}
```

### ❌ Error Response Example
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "phone_number": [
      "The phone number must match the format 254XXXXXXXXX."
    ]
  }
}
```

## 🐛 Common Issues

| Issue | Solution |
|-------|----------|
| Access token fails | Check `.env` credentials |
| STK Push no prompt | Verify phone format (254XXXXXXXXX) |
| C2B registration fails | Ensure URLs are publicly accessible |
| B2C payment fails | Check B2C account balance |

## 📚 Full Documentation

For complete documentation with all request/response examples, see:
- **[MPESA_TEST_API_GUIDE.md](./MPESA_TEST_API_GUIDE.md)** - Comprehensive guide with all details

## ⚠️ Security Note

**These test endpoints should NOT be available in production!**

To restrict them:

```php
// In routes/api.php
if (config('app.env') !== 'production') {
    Route::prefix('mpesa')->group(function () {
        // Test routes here
    });
}
```

## 🆘 Need Help?

1. Check logs: `tail -f storage/logs/laravel.log`
2. Verify `.env` configuration
3. Check full documentation in `MPESA_TEST_API_GUIDE.md`
4. Review Safaricom Daraja API documentation

## 📦 Import to Postman

Import the collection JSON from `MPESA_TEST_API_GUIDE.md` (bottom of file) to get all endpoints pre-configured in Postman.

---

**Quick Tip**: Keep the log file open while testing to see real-time feedback!

```bash
# In one terminal
tail -f storage/logs/laravel.log

# In another terminal
php artisan serve
```

Happy Testing! 🚀

