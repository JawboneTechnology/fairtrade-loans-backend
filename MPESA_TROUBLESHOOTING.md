# M-Pesa Test API - Troubleshooting Guide

## Common Errors and Solutions

### 1. ❌ ArgumentCountError: Too Few Arguments to stkpush()

**Error Message:**
```
Too few arguments to function Iankumu\Mpesa\Mpesa::stkpush(), 
1 passed and at least 3 expected
```

**Cause:**
The `iankumu/mpesa` package's `stkpush()` method requires individual parameters, not an array.

**Solution:**
✅ **FIXED** in version 1.0.2

The package method signature is:
```php
public function stkpush($phonenumber, $amount, $account_number, $callbackurl = null)
```

**Correct Usage:**
```php
// ✅ Correct
$response = Mpesa::stkpush(
    $phoneNumber,      // string: '254712345678'
    $amount,           // int: 100
    $accountReference, // string: 'LOAN-001'
    $callbackUrl       // string: 'https://...'
);

// ❌ Wrong (this causes the error)
$response = Mpesa::stkPush([
    'phone' => $phoneNumber,
    'amount' => $amount,
    'reference' => $accountReference,
    'callback' => $callbackUrl
]);
```

**Files Fixed:**
- `app/Services/MpesaService.php`
- `app/Jobs/ProcessStkPushJob.php`

---

### 2. ❌ Payment Method Data Truncation Error

**Error Message:**
```
SQLSTATE[01000]: Warning: 1265 Data truncated for column 'payment_method' at row 1
```

**Cause:**
The `payment_method` column in `mpesa_transactions` table is an ENUM with only two allowed values: `'APP'` and `'PAYBILL'`. Any other value will cause this error.

**Solution:**
✅ **FIXED** in version 1.0.1 - The test controller now uses `'APP'` instead of `'TEST_APP'`

**Valid Payment Method Values:**
- `'APP'` - For payments initiated through the app (STK Push)
- `'PAYBILL'` - For payments made directly via M-Pesa paybill

**Database Schema:**
```php
$table->enum('payment_method', ['APP', 'PAYBILL'])->default('APP');
```

---

### 2. ❌ Phone Number Validation Error

**Error Message:**
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

**Cause:**
Phone number is not in the correct Kenyan format.

**Solution:**
✅ Use the format: `254XXXXXXXXX` (254 followed by 9 digits)

**Examples:**
- ✅ Correct: `254712345678`
- ✅ Correct: `254722334455`
- ❌ Wrong: `0712345678` (missing country code)
- ❌ Wrong: `+254712345678` (don't include +)
- ❌ Wrong: `254 712 345678` (no spaces)

---

### 3. ❌ Access Token Generation Failed

**Error Message:**
```json
{
  "success": false,
  "message": "Access token test failed",
  "error": "M-Pesa consumer key or secret not configured"
}
```

**Cause:**
M-Pesa credentials are missing or incorrect in `.env` file.

**Solution:**
✅ Check your `.env` file has these variables:

```env
MPESA_ENVIRONMENT=sandbox
MPESA_CONSUMER_KEY=your_consumer_key_here
MPESA_CONSUMER_SECRET=your_consumer_secret_here
```

**Steps to Fix:**
1. Get credentials from [Safaricom Daraja Portal](https://developer.safaricom.co.ke/)
2. Add them to `.env` file
3. Clear config cache: `php artisan config:clear`
4. Test again

---

### 4. ❌ Amount Validation Error

**Error Message:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "amount": [
      "The amount must be at least 1."
    ]
  }
}
```

**Cause:**
Amount is too low or invalid.

**Solution:**
✅ **STK Push**: Minimum amount is `1`
✅ **B2C Payment**: Minimum amount is `10`

**Examples:**
- ✅ Correct: `100`
- ✅ Correct: `1.50`
- ❌ Wrong: `0`
- ❌ Wrong: `-100`

---

### 5. ❌ C2B Validation Rejected

**Error Message:**
```json
{
  "ResultCode": 1,
  "ResultDesc": "Invalid loan number or employee ID",
  "validation_details": {
    "valid": false,
    "reason": "Invalid loan number or employee ID"
  }
}
```

**Cause:**
The `BillRefNumber` provided doesn't match any loan number or employee ID in the system.

**Solution:**
✅ Use a valid loan number from your database

**How to Find Valid Loan Numbers:**
```sql
SELECT loan_number FROM loans WHERE loan_status = 'approved' AND loan_balance > 0 LIMIT 10;
```

Or use employee ID:
```sql
SELECT employee_id FROM users LIMIT 10;
```

---

### 6. ❌ STK Push - No Phone Prompt

**Problem:**
STK Push returns success but customer doesn't receive prompt on phone.

**Possible Causes & Solutions:**

1. **Wrong Phone Number**
   - ✅ Verify format: `254XXXXXXXXX`
   - ✅ Test with your own phone first

2. **Wrong Environment**
   - ✅ Sandbox only works with test credentials
   - ✅ Check: `MPESA_ENVIRONMENT` in `.env`

3. **Callback URL Not Accessible**
   - ✅ Callback URL must be publicly accessible
   - ✅ Use ngrok for local testing: `ngrok http 8000`
   - ✅ Update callback URL in `.env`

4. **Invalid Credentials**
   - ✅ Verify consumer key and secret
   - ✅ Check they match the environment (sandbox/production)

---

### 7. ❌ B2C Payment Insufficient Balance

**Error Message:**
```json
{
  "success": false,
  "message": "B2C payment failed",
  "error": "Insufficient balance in B2C account"
}
```

**Cause:**
Your B2C account doesn't have enough balance.

**Solution:**
✅ **Sandbox**: Request test balance increase from Safaricom
✅ **Production**: Fund your B2C account

**Contact:**
- Safaricom Developer Support: [developer.safaricom.co.ke](https://developer.safaricom.co.ke/)

---

### 8. ❌ C2B Registration Failed

**Error Message:**
```json
{
  "success": false,
  "message": "C2B registration test failed",
  "error": "..."
}
```

**Possible Causes & Solutions:**

1. **URLs Not Accessible**
   - ✅ Validation and confirmation URLs must be publicly accessible
   - ✅ Use ngrok for local testing
   - ✅ Update URLs in `.env`:
   ```env
   MPESA_C2B_VALIDATION_URL=https://your-public-url.com/api/v1/c2b/validation
   MPESA_C2B_CONFIRMATION_URL=https://your-public-url.com/api/v1/c2b/confirmation
   ```

2. **Wrong Shortcode**
   - ✅ Verify shortcode matches your app
   - ✅ Check `MPESA_BUSINESS_SHORTCODE` in `.env`

3. **Environment Mismatch**
   - ✅ Sandbox shortcode for sandbox environment
   - ✅ Production shortcode for production environment

---

### 9. ❌ Callback Not Received

**Problem:**
STK Push or C2B payment succeeded but callback wasn't processed.

**Debugging Steps:**

1. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Verify Callback URL:**
   - Must be publicly accessible (not localhost)
   - Must use HTTPS in production
   - Must return 200 status code

3. **Test with ngrok (Local Development):**
   ```bash
   # Start ngrok
   ngrok http 8000
   
   # Copy the HTTPS URL
   # Update .env
   MPESA_CALLBACK_URL=https://your-ngrok-url.ngrok.io/api/v1/mpesa/stk/callback
   
   # Clear config
   php artisan config:clear
   ```

4. **Check Route Registration:**
   ```bash
   php artisan route:list | grep callback
   ```

---

### 10. ❌ Transaction Not Found in Database

**Problem:**
STK Push request succeeded but transaction not in database.

**Debugging Steps:**

1. **Check for Errors in Logs:**
   ```bash
   grep "ERROR" storage/logs/laravel.log
   ```

2. **Verify Database Connection:**
   ```bash
   php artisan tinker
   >>> DB::connection()->getPdo();
   ```

3. **Check Transaction:**
   ```bash
   php artisan tinker
   >>> \App\Models\MpesaTransaction::latest()->first();
   ```

4. **Verify Table Exists:**
   ```sql
   SHOW TABLES LIKE 'mpesa_transactions';
   ```

---

## Quick Diagnostic Commands

### Check M-Pesa Configuration
```bash
php artisan tinker
>>> config('mpesa.environment')
>>> config('mpesa.mpesa_consumer_key')
>>> config('mpesa.shortcode')
```

### View Recent Transactions
```bash
php artisan tinker
>>> \App\Models\MpesaTransaction::latest()->take(5)->get()
```

### View Recent Logs
```bash
tail -50 storage/logs/laravel.log
```

### Test Database Connection
```bash
php artisan tinker
>>> DB::select('SELECT 1')
```

### Clear All Caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## Testing Checklist

Before reporting an issue, verify:

- [ ] Laravel server is running
- [ ] Database is accessible
- [ ] `.env` file has all M-Pesa credentials
- [ ] Phone number format is correct (254XXXXXXXXX)
- [ ] Amount meets minimum requirements
- [ ] Callback URLs are publicly accessible (use ngrok for local)
- [ ] Logs are being monitored
- [ ] Config cache is cleared
- [ ] Using correct environment (sandbox/production)
- [ ] Credentials match the environment

---

## Getting More Help

### View Detailed Logs
```bash
# Real-time monitoring
tail -f storage/logs/laravel.log

# Filter by test name
grep "STK PUSH" storage/logs/laravel.log
grep "B2C" storage/logs/laravel.log
grep "C2B" storage/logs/laravel.log

# View errors only
grep "ERROR" storage/logs/laravel.log

# Today's logs
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log
```

### Enable Debug Mode
```env
# In .env
APP_DEBUG=true
LOG_LEVEL=debug
```

### Test Individual Components

1. **Test Access Token:**
   ```
   GET /api/v1/mpesa/test-token
   ```

2. **Check Recent Transactions:**
   ```
   GET /api/v1/mpesa/test-transactions
   ```

3. **Verify Routes:**
   ```bash
   php artisan route:list | grep mpesa
   ```

---

## Support Resources

- **Internal Docs**: `MPESA_TEST_API_GUIDE.md`
- **Quick Start**: `MPESA_QUICK_START.md`
- **Safaricom Daraja**: [developer.safaricom.co.ke](https://developer.safaricom.co.ke/)
- **Laravel Logs**: `storage/logs/laravel.log`

---

## Version History

### v1.0.2 (Current)
- ✅ Fixed ArgumentCountError in stkpush() method
- ✅ Corrected method call from array parameter to individual parameters
- ✅ Fixed in `MpesaService.php` and `ProcessStkPushJob.php`
- ✅ Added proper response handling for HTTP Client Response
- ✅ Enhanced logging for STK Push responses

### v1.0.1
- ✅ Fixed payment_method data truncation error
- ✅ Changed `'TEST_APP'` to `'APP'`
- ✅ Added this troubleshooting guide

### v1.0.0
- Initial release with all test endpoints

---

**Last Updated**: November 16, 2025

