# M-Pesa Callback Logs - Readable Format Update

## Overview

All M-Pesa callback methods now use formatted JSON logging for better readability.

---

## 🔧 What Changed

### Before (Hard to Read)
```
[2025-11-16 10:30:45] local.INFO: STK Push Callback received: array('Body' => array('stkCallback' => array('MerchantRequestID' => '12345', 'CheckoutRequestID' => 'ws_CO_123', 'ResultCode' => 0, 'ResultDesc' => 'Success', 'CallbackMetadata' => array('Item' => array(...)))))
```

### After (Easy to Read)
```
[2025-11-16 10:30:45] local.INFO: === STK PUSH CALLBACK RECEIVED ===
[2025-11-16 10:30:45] local.INFO: 
{
    "Body": {
        "stkCallback": {
            "MerchantRequestID": "12345-67890-1",
            "CheckoutRequestID": "ws_CO_15062023143000000001",
            "ResultCode": 0,
            "ResultDesc": "The service request is processed successfully.",
            "CallbackMetadata": {
                "Item": [
                    {
                        "Name": "Amount",
                        "Value": 100
                    },
                    {
                        "Name": "MpesaReceiptNumber",
                        "Value": "OEI2AK4Q16"
                    },
                    {
                        "Name": "PhoneNumber",
                        "Value": 254712345678
                    }
                ]
            }
        }
    }
}
```

---

## ✅ Updated Methods

All callback methods in `MpesaController.php` now have readable logging:

### 1. STK Push Callback
```php
public function stkCallback(Request $request): JsonResponse
{
    $callbackData = $request->all();
    
    Log::info('=== STK PUSH CALLBACK RECEIVED ===');
    Log::info(PHP_EOL . json_encode($callbackData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    // ... processing
}
```

**Log Output:**
```
=== STK PUSH CALLBACK RECEIVED ===
{
    "Body": {
        "stkCallback": {
            "MerchantRequestID": "...",
            "CheckoutRequestID": "...",
            "ResultCode": 0,
            "ResultDesc": "Success"
        }
    }
}
```

---

### 2. C2B Validation
```php
public function c2bValidation(Request $request): JsonResponse
{
    Log::info('=== C2B VALIDATION RECEIVED ===');
    Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    // ... processing
}
```

**Log Output:**
```
=== C2B VALIDATION RECEIVED ===
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

---

### 3. C2B Confirmation
```php
public function c2bConfirmation(Request $request): JsonResponse
{
    Log::info('=== C2B CONFIRMATION RECEIVED ===');
    Log::info(PHP_EOL . json_encode($callbackData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    // ... processing
    
    if ($result['success']) {
        Log::info('=== C2B PAYMENT PROCESSED SUCCESSFULLY ===');
        Log::info(json_encode([
            'transaction_id' => $result['transaction_id'],
            'loan_id' => $result['loan_id']
        ], JSON_PRETTY_PRINT));
    }
}
```

**Log Output:**
```
=== C2B CONFIRMATION RECEIVED ===
{
    "TransactionType": "Pay Bill",
    "TransID": "OEI2AK4Q16",
    ...
}
=== C2B PAYMENT PROCESSED SUCCESSFULLY ===
{
    "transaction_id": "550e8400-e29b-41d4-a716-446655440000",
    "loan_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

---

### 4. B2C Result & Timeout
```php
public function b2cResult(Request $request): JsonResponse
{
    Log::info('=== B2C RESULT RECEIVED ===');
    Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    // ... processing
}

public function b2cTimeout(Request $request): JsonResponse
{
    Log::info('=== B2C TIMEOUT RECEIVED ===');
    Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    // ... processing
}
```

---

### 5. Other Callbacks

All other callback methods follow the same pattern:
- ✅ Transaction Status Result/Timeout
- ✅ Balance Result/Timeout
- ✅ Reversal Result/Timeout
- ✅ B2B Result/Timeout

---

## 🎯 Key Features

### 1. Clear Section Headers
```
=== STK PUSH CALLBACK RECEIVED ===
=== C2B VALIDATION RECEIVED ===
=== B2C RESULT RECEIVED ===
```

Makes it easy to search and filter logs:
```bash
grep "===" storage/logs/laravel.log
grep "STK PUSH" storage/logs/laravel.log
```

### 2. Pretty-Printed JSON
```php
json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
```

**Features:**
- ✅ Multi-line formatting
- ✅ Proper indentation
- ✅ No escaped forward slashes
- ✅ Easy to read nested structures

### 3. Newline Prefix
```php
Log::info(PHP_EOL . json_encode(...))
```

Ensures the JSON starts on a new line for better readability.

### 4. Enhanced Error Logging
```php
catch (\Exception $e) {
    Log::error('=== ERROR PROCESSING STK CALLBACK ===');
    Log::error('Error: ' . $e->getMessage());
    Log::error('Stack trace: ' . $e->getTraceAsString());
}
```

**Error Output:**
```
=== ERROR PROCESSING STK CALLBACK ===
Error: Invalid transaction data
Stack trace:
#0 /path/to/file.php(123): Method->call()
#1 /path/to/file.php(456): OtherMethod->call()
...
```

---

## 📊 Log Reading Tips

### View Real-Time Logs
```bash
tail -f storage/logs/laravel.log
```

### Filter by Callback Type
```bash
# STK Push callbacks
grep -A 20 "STK PUSH CALLBACK" storage/logs/laravel.log

# C2B callbacks
grep -A 20 "C2B CONFIRMATION" storage/logs/laravel.log

# B2C callbacks
grep -A 20 "B2C RESULT" storage/logs/laravel.log
```

### View Only Headers
```bash
grep "===" storage/logs/laravel.log
```

### Extract JSON Data
```bash
# Get STK Push callback JSON
grep -A 50 "STK PUSH CALLBACK" storage/logs/laravel.log | grep -A 40 "{"
```

### View Today's Callbacks
```bash
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log | grep "==="
```

### Count Callbacks by Type
```bash
grep -c "STK PUSH CALLBACK" storage/logs/laravel.log
grep -c "C2B CONFIRMATION" storage/logs/laravel.log
grep -c "B2C RESULT" storage/logs/laravel.log
```

---

## 🔍 Example: Complete STK Push Flow

### 1. Request Initiated
```
=== STK PUSH TEST STARTED ===
STK Push Request Data
{
    "phone_number": "254712345678",
    "amount": 100,
    "account_reference": "TEST-001",
    "transaction_description": "Test Payment"
}
```

### 2. STK Push Response
```
STK Push Response
{
    "MerchantRequestID": "12345-67890-1",
    "CheckoutRequestID": "ws_CO_15062023143000000001",
    "ResponseCode": "0",
    "ResponseDescription": "Success"
}
```

### 3. Callback Received
```
=== STK PUSH CALLBACK RECEIVED ===
{
    "Body": {
        "stkCallback": {
            "MerchantRequestID": "12345-67890-1",
            "CheckoutRequestID": "ws_CO_15062023143000000001",
            "ResultCode": 0,
            "ResultDesc": "The service request is processed successfully.",
            "CallbackMetadata": {
                "Item": [
                    {
                        "Name": "Amount",
                        "Value": 100
                    },
                    {
                        "Name": "MpesaReceiptNumber",
                        "Value": "OEI2AK4Q16"
                    }
                ]
            }
        }
    }
}
```

### 4. Processing Complete
```
STK Push payment processed successfully
{
    "transaction_id": "550e8400-e29b-41d4-a716-446655440000",
    "mpesa_receipt": "OEI2AK4Q16",
    "amount": 100
}
```

---

## 💡 Benefits

| Benefit | Description |
|---------|-------------|
| 🔍 **Easy Debugging** | Quickly identify and read callback data |
| 📊 **Better Monitoring** | Clear section headers for filtering |
| 🐛 **Error Tracking** | Stack traces included for exceptions |
| 📝 **Documentation** | Logs serve as API documentation |
| ⚡ **Fast Searching** | Use grep to find specific callbacks |
| 👥 **Team Collaboration** | Anyone can read and understand logs |

---

## 🎨 Log Format Constants

All logs use these constants:

```php
// JSON formatting flags
JSON_PRETTY_PRINT          // Multi-line with indentation
JSON_UNESCAPED_SLASHES    // Don't escape forward slashes

// Newline
PHP_EOL                    // Platform-appropriate newline
```

---

## ✅ Files Modified

| File | Changes |
|------|---------|
| `app/Http/Controllers/Api/V1/MpesaController.php` | ✅ All callback methods updated |

**Methods Updated:**
1. ✅ `stkCallback()` - STK Push callback
2. ✅ `c2bValidation()` - C2B validation
3. ✅ `c2bConfirmation()` - C2B confirmation
4. ✅ `b2cResult()` - B2C result
5. ✅ `b2cTimeout()` - B2C timeout
6. ✅ `statusResult()` - Transaction status result
7. ✅ `statusTimeout()` - Transaction status timeout
8. ✅ `balanceResult()` - Balance query result
9. ✅ `balanceTimeout()` - Balance query timeout
10. ✅ `reversalResult()` - Reversal result
11. ✅ `reversalTimeout()` - Reversal timeout
12. ✅ `b2bResult()` - B2B result
13. ✅ `b2bTimeout()` - B2B timeout

---

## 🚀 Testing

Test the new log format:

```bash
# Terminal 1: Watch logs in real-time
tail -f storage/logs/laravel.log

# Terminal 2: Trigger STK Push
curl -X POST http://localhost:8000/api/v1/mpesa/test-stk-push \
  -H "Content-Type: application/json" \
  -d '{
    "phone_number": "254712345678",
    "amount": 1,
    "account_reference": "TEST-001"
  }'
```

**You should see beautifully formatted logs!** 📊

---

## 📚 Additional Resources

### Laravel Logging Documentation
- [Laravel Logging](https://laravel.com/docs/logging)
- [Monolog Documentation](https://github.com/Seldaek/monolog)

### Log Analysis Tools
```bash
# Linux/Mac log tools
tail, grep, less, awk, sed

# Log viewers
- Laravel Log Viewer (package)
- LogViewer (web-based)
```

---

**Updated:** November 16, 2025
**Version:** 1.0.5
**Status:** ✅ Complete - All callbacks now have readable logs!

