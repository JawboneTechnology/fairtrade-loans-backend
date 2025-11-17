# M-Pesa B2C (Business to Customer) Payment Setup Guide

## Overview
B2C (Business to Customer) payments allow you to send money from your business to customers' M-Pesa accounts. This is used for disbursements like salaries, refunds, or payouts.

---

## 🔴 CRITICAL: Bulk Disbursement Account Required

**IMPORTANT**: B2C payments **CANNOT** be done from a regular Paybill or Buy Goods (Till) account.

You **MUST** apply for a **Bulk Disbursement Account** from Safaricom:
- **Application Link**: https://www.safaricom.co.ke/business/sme/m-pesa-payment-solutions
- Processing time: Usually 2-3 weeks
- You'll receive a special **B2C Shortcode** after approval

---

## 📋 Required Configuration

### 1. Environment Variables (.env)

Add these to your `.env` file:

```env
# B2C Specific Configuration
MPESA_INITIATOR_NAME=testapi
MPESA_INITIATOR_PASSWORD=your_initiator_password_here
MPESA_B2C_SHORTCODE=your_b2c_shortcode_here

# B2C Callback URLs (must be publicly accessible HTTPS URLs)
MPESA_B2C_RESULT_URL=https://yourdomain.com/api/v1/mpesa/b2c-result
MPESA_B2C_TIMEOUT_URL=https://yourdomain.com/api/v1/mpesa/b2c-timeout
```

### 2. Test Credentials (Sandbox)

For sandbox testing, use these credentials:
- **InitiatorName**: `testapi` (available on Safaricom test credentials page)
- **InitiatorPassword**: Get from Safaricom test credentials page
- **B2C Shortcode**: `600996` (sandbox shortcode)

**Where to find them**:
1. Login to https://developer.safaricom.co.ke
2. Go to your app
3. Click "Test Credentials"
4. Find "Initiator Name" and "Initiator Password"

---

## 🔐 Security Credential (Password Encryption)

### What is SecurityCredential?
The SecurityCredential is your **InitiatorPassword encrypted** using Safaricom's public key certificate.

### How it works:
1. You provide plain text password in `.env`: `MPESA_INITIATOR_PASSWORD=YourPassword123`
2. The `iankumu/mpesa` package **automatically encrypts it** using the public key certificate
3. The encrypted password is sent as `SecurityCredential` in the API request

### Password Requirements:
- ✅ Allowed special characters: `#`, `&`, `%`, `$`
- ❌ NOT allowed: `(`, `)`, `@` (treated as normal character)
- Example good password: `Test@Pass#2024`
- Example bad password: `Test(Pass)2024`

---

## 📡 API Implementation

### How Our Implementation Works

```php
// Call B2C API - Method signature: b2c($phonenumber, $command_id, $amount, $remarks)
$response = Mpesa::b2c(
    '254712345678',           // Phone number
    'BusinessPayment',        // Command ID
    1000,                     // Amount
    'Salary payment'          // Remarks
);
```

### Parameters Automatically Sent by Package:

The `iankumu/mpesa` package automatically includes these from your config:

| Parameter | Source | Description |
|-----------|--------|-------------|
| `InitiatorName` | `mpesa.initiator_name` | API operator username |
| `SecurityCredential` | `mpesa.initiator_password` | Auto-encrypted password |
| `PartyA` | `mpesa.b2c_shortcode` | Your B2C shortcode |
| `PartyB` | Function parameter | Customer phone number |
| `CommandID` | Function parameter | Payment type |
| `Amount` | Function parameter | Amount to send |
| `Remarks` | Function parameter | Payment description |
| `QueueTimeOutURL` | `mpesa.b2c_timeout_url` | Timeout callback |
| `ResultURL` | `mpesa.b2c_result_url` | Result callback |
| `Occassion` | Empty string | Optional field (note: Safaricom's typo) |

---

## 🎯 Command IDs

Choose the appropriate CommandID for your payment type:

| CommandID | Use Case | Description |
|-----------|----------|-------------|
| `BusinessPayment` | General payments | Default for business-to-customer payments |
| `SalaryPayment` | Salary disbursement | Specifically for employee salaries |
| `PromotionPayment` | Promotional payments | For marketing/promotional payouts |

---

## 📝 API Request Flow

### 1. Your Request
```json
POST /api/v1/mpesa/test-b2c
{
  "phone_number": "254712345678",
  "amount": 100,
  "command_id": "BusinessPayment",
  "remarks": "Test payment",
  "occasion": "Testing"
}
```

### 2. What Gets Sent to Safaricom
```json
POST https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest
{
  "InitiatorName": "testapi",
  "SecurityCredential": "EsJocK7+NjqZPC3I3EO+TbvS+xVb9TymWwaKABoaZr/Z/n0UysSs...",
  "CommandID": "BusinessPayment",
  "Amount": 100,
  "PartyA": "600996",
  "PartyB": "254712345678",
  "Remarks": "Test payment",
  "QueueTimeOutURL": "https://yourdomain.com/api/v1/mpesa/b2c-timeout",
  "ResultURL": "https://yourdomain.com/api/v1/mpesa/b2c-result",
  "Occassion": ""
}
```

### 3. Safaricom's Acknowledgment
```json
{
  "ConversationID": "AG_20231217_00004f7e3b9f9e3c9b1e",
  "OriginatorConversationID": "feb5e3f2-fbbc-4745-844c-ee37b546f627",
  "ResponseCode": "0",
  "ResponseDescription": "Accept the service request successfully."
}
```

### 4. Result Callback (Sent to your ResultURL)
```json
{
  "Result": {
    "ResultType": 0,
    "ResultCode": 0,
    "ResultDesc": "The service request is processed successfully.",
    "OriginatorConversationID": "feb5e3f2-fbbc-4745-844c-ee37b546f627",
    "ConversationID": "AG_20231217_00004f7e3b9f9e3c9b1e",
    "TransactionID": "OEI2AK4Q16",
    "ResultParameters": {
      "ResultParameter": [
        {"Key": "TransactionReceipt", "Value": "OEI2AK4Q16"},
        {"Key": "TransactionAmount", "Value": 100},
        {"Key": "B2CWorkingAccountAvailableFunds", "Value": 50000},
        {"Key": "TransactionCompletedDateTime", "Value": "17.12.2023 14:30:00"},
        {"Key": "ReceiverPartyPublicName", "Value": "254712345678 - John Doe"},
        {"Key": "B2CRecipientIsRegisteredCustomer", "Value": "Y"}
      ]
    }
  }
}
```

---

## 🧪 Testing

### 1. Verify Configuration
```bash
# Check if all B2C configs are set
php artisan tinker
>>> config('mpesa.initiator_name')
>>> config('mpesa.initiator_password')
>>> config('mpesa.b2c_shortcode')
>>> config('mpesa.b2c_result_url')
>>> config('mpesa.b2c_timeout_url')
```

### 2. Test B2C Payment

**Postman Request:**
```http
POST {{base_url}}/api/v1/mpesa/test-b2c
Content-Type: application/json

{
  "phone_number": "254712345678",
  "amount": 100,
  "command_id": "BusinessPayment",
  "remarks": "Test B2C Payment",
  "occasion": "Testing"
}
```

**Expected Response:**
```json
{
  "success": true,
  "message": "B2C payment initiated successfully",
  "data": {
    "transaction_id": "6704b991-3b9d-4ac4-8b54-b64ef5cc653e",
    "conversation_id": "AG_20231217_00004f7e3b9f9e3c9b1e",
    "originator_conversation_id": "feb5e3f2-fbbc-4745-844c-ee37b546f627",
    "response_code": "0",
    "response_description": "Accept the service request successfully."
  },
  "environment": "sandbox"
}
```

### 3. Configuration Validation

Our updated implementation includes automatic validation:

```php
// If config is missing, you'll get:
{
  "success": false,
  "message": "B2C configuration error",
  "error": [
    "MPESA_INITIATOR_NAME not configured",
    "MPESA_INITIATOR_PASSWORD not configured",
    "MPESA_B2C_SHORTCODE not configured (Bulk Disbursement Account required)"
  ]
}
```

---

## 📂 Callback Handlers

### Result Callback (`b2cResult`)
Location: `app/Http/Controllers/Api/V1/MpesaController.php`

```php
public function b2cResult(Request $request): JsonResponse
{
    Log::info('=== B2C RESULT RECEIVED ===');
    Log::info(PHP_EOL . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    try {
        $this->processB2CResult($request->all());
    } catch (\Exception $e) {
        Log::error('=== ERROR PROCESSING B2C RESULT ===');
        Log::error('Error: ' . $e->getMessage());
    }

    return response()->json([
        'ResultCode' => 0,
        'ResultDesc' => 'Success'
    ]);
}
```

### Timeout Callback (`b2cTimeout`)
Handles cases where the transaction times out.

---

## 🚀 Production Deployment

### Requirements Checklist:

- [ ] **Bulk Disbursement Account** approved and active
- [ ] **B2C Shortcode** received from Safaricom
- [ ] **InitiatorName** and **InitiatorPassword** obtained
- [ ] **Public HTTPS callback URLs** configured
- [ ] Callback URLs registered with Safaricom
- [ ] SSL certificate valid
- [ ] Environment set to `production` in `.env`

### Production .env:
```env
MPESA_ENVIRONMENT=production
MPESA_B2C_SHORTCODE=your_production_shortcode
MPESA_INITIATOR_NAME=your_production_initiator
MPESA_INITIATOR_PASSWORD=your_production_password
MPESA_B2C_RESULT_URL=https://yourdomain.com/api/v1/mpesa/b2c-result
MPESA_B2C_TIMEOUT_URL=https://yourdomain.com/api/v1/mpesa/b2c-timeout
```

---

## ⚠️ Common Issues

### 1. "Too few arguments to function b2c()"
**Cause**: Calling `Mpesa::b2c()` with an array instead of individual parameters
**Solution**: ✅ Fixed in updated implementation

### 2. "SecurityCredential validation failed"
**Cause**: Password contains invalid special characters
**Solution**: Use only `#`, `&`, `%`, `$`

### 3. "Invalid shortcode"
**Cause**: Using Paybill/Till shortcode instead of B2C shortcode
**Solution**: Apply for Bulk Disbursement Account

### 4. "Callback URL unreachable"
**Cause**: URLs not publicly accessible or HTTP instead of HTTPS
**Solution**: Use ngrok for testing, valid SSL for production

### 5. "Insufficient balance"
**Cause**: B2C account balance is low
**Solution**: Top up your B2C account

---

## 📊 Monitoring

### Check Logs:
```bash
tail -f storage/logs/laravel.log | grep "B2C"
```

### Log Output Example:
```
[2025-11-17 10:30:00] local.INFO: === B2C PAYMENT INITIATED ===
[2025-11-17 10:30:00] local.INFO:
{
    "environment": "sandbox",
    "transaction_id": "6704b991-3b9d-4ac4-8b54-b64ef5cc653e",
    "phone_number": "254712345678",
    "amount": 100,
    "command_id": "BusinessPayment",
    "response": {
        "ConversationID": "AG_20231217_00004f7e3b9f9e3c9b1e",
        "OriginatorConversationID": "feb5e3f2-fbbc-4745-844c-ee37b546f627",
        "ResponseCode": "0",
        "ResponseDescription": "Accept the service request successfully."
    },
    "http_status": 200
}
```

---

## 📚 Additional Resources

- **Safaricom Developer Portal**: https://developer.safaricom.co.ke
- **B2C API Documentation**: https://developer.safaricom.co.ke/Documentation
- **Apply for Bulk Disbursement**: https://www.safaricom.co.ke/business/sme/m-pesa-payment-solutions
- **Package Documentation**: https://github.com/iankumu/mpesa

---

## ✅ What Was Fixed

1. ✅ Corrected method call from array to individual parameters
2. ✅ Added configuration validation before API call
3. ✅ Enhanced response handling and logging
4. ✅ Added transaction record creation and update
5. ✅ Improved error handling with detailed messages
6. ✅ Added comprehensive documentation in code comments

---

**Last Updated**: November 17, 2025
**Implementation**: ✅ Complete and production-ready

