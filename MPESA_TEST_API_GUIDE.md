# M-Pesa Test API Guide

This guide provides comprehensive documentation for testing M-Pesa integrations using the test controller endpoints. All endpoints include detailed logging to help you debug and monitor transactions.

## Base URL

```
http://your-domain.com/api/v1/mpesa
```

For local development:
```
http://localhost:8000/api/v1/mpesa
```

---

## Table of Contents

1. [Access Token Test](#1-access-token-test)
2. [STK Push Test](#2-stk-push-test)
3. [B2C Payment Test](#3-b2c-payment-test)
4. [C2B Registration Test](#4-c2b-registration-test)
5. [C2B Validation Test](#5-c2b-validation-test)
6. [C2B Confirmation Test](#6-c2b-confirmation-test)
7. [B2C Result Callback Test](#7-b2c-result-callback-test)
8. [B2C Timeout Callback Test](#8-b2c-timeout-callback-test)
9. [Get Test Transactions](#9-get-test-transactions)
10. [Logging](#10-logging)

---

## 1. Access Token Test

Test M-Pesa access token generation to verify your credentials are configured correctly.

### Endpoint
```
GET /api/v1/mpesa/test-token
```

### Headers
```
Content-Type: application/json
Accept: application/json
```

### Request Body
None (GET request)

### Example Postman Request
```bash
curl --location 'http://localhost:8000/api/v1/mpesa/test-token' \
--header 'Accept: application/json'
```

### Success Response (200)
```json
{
  "success": true,
  "message": "Access token test completed",
  "token_info": {
    "token_generated": true,
    "environment": "sandbox",
    "token_length": 200,
    "expires_in": 3599,
    "http_code": 200
  },
  "timestamp": "2023-06-15 14:30:00"
}
```

### Error Response (500)
```json
{
  "success": false,
  "message": "Access token test failed",
  "error": "M-Pesa consumer key or secret not configured"
}
```

### Logs Generated
- M-PESA ACCESS TOKEN TEST STARTED
- M-Pesa Configuration Check
- M-Pesa Token Generation Response
- M-PESA ACCESS TOKEN TEST COMPLETED

---

## 2. STK Push Test

Test Lipa Na M-Pesa Online (STK Push) functionality. This sends a payment prompt to the customer's phone.

### Endpoint
```
POST /api/v1/mpesa/test-stk-push
```

### Headers
```
Content-Type: application/json
Accept: application/json
```

### Request Body
```json
{
  "phone_number": "254712345678",
  "amount": 100,
  "account_reference": "TEST-001",
  "transaction_description": "Test STK Push Payment"
}
```

### Field Descriptions
- `phone_number` (required): Customer's phone number in format 254XXXXXXXXX
- `amount` (required): Payment amount (minimum: 1)
- `account_reference` (optional): Reference for the transaction (max 20 chars, defaults to TEST-timestamp)
- `transaction_description` (optional): Description of the transaction (max 100 chars)

**Note**: The system automatically sets `payment_method` to `'APP'` for test STK Push requests. Valid values in the database are: `'APP'` or `'PAYBILL'`.

### Example Postman Request
```bash
curl --location 'http://localhost:8000/api/v1/mpesa/test-stk-push' \
--header 'Content-Type: application/json' \
--header 'Accept: application/json' \
--data '{
  "phone_number": "254712345678",
  "amount": 100,
  "account_reference": "TEST-001",
  "transaction_description": "Test STK Push Payment"
}'
```

### Success Response (200)
```json
{
  "success": true,
  "message": "STK Push initiated successfully",
  "data": {
    "transaction_id": "TXN123456",
    "checkout_request_id": "ws_CO_15062023143000000001",
    "merchant_request_id": "12345-67890-1",
    "environment": "sandbox"
  },
  "timestamp": "2023-06-15 14:30:00",
  "note": "Check your phone for M-Pesa prompt. Callback will be logged automatically."
}
```

### Validation Error Response (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "phone_number": [
      "The phone number must match the format 254XXXXXXXXX."
    ],
    "amount": [
      "The amount must be at least 1."
    ]
  }
}
```

### Logs Generated
- STK PUSH TEST STARTED
- STK Push Request Data
- STK Push initiated successfully (from MpesaService)
- STK PUSH TEST COMPLETED

---

## 3. B2C Payment Test

Test Business to Customer (B2C) payments. This sends money from your business to a customer's M-Pesa account.

### Endpoint
```
POST /api/v1/mpesa/test-b2c
```

### Headers
```
Content-Type: application/json
Accept: application/json
```

### Request Body
```json
{
  "phone_number": "254712345678",
  "amount": 100,
  "command_id": "BusinessPayment",
  "remarks": "Test B2C Payment",
  "occasion": "Testing"
}
```

### Field Descriptions
- `phone_number` (required): Customer's phone number in format 254XXXXXXXXX
- `amount` (required): Payment amount (minimum: 10)
- `command_id` (optional): Type of B2C payment. Options:
  - `BusinessPayment` (default): Normal business to customer payment
  - `SalaryPayment`: Salary disbursement
  - `PromotionPayment`: Promotional payment
- `remarks` (optional): Payment remarks (max 100 chars)
- `occasion` (optional): Payment occasion/reason (max 100 chars)

### Example Postman Request
```bash
curl --location 'http://localhost:8000/api/v1/mpesa/test-b2c' \
--header 'Content-Type: application/json' \
--header 'Accept: application/json' \
--data '{
  "phone_number": "254712345678",
  "amount": 100,
  "command_id": "BusinessPayment",
  "remarks": "Test B2C Payment",
  "occasion": "Testing"
}'
```

### Success Response (200)
```json
{
  "success": true,
  "message": "B2C payment initiated successfully",
  "data": {
    "ConversationID": "AG_20230615_00004f7e3b9f9e3c9b1e",
    "OriginatorConversationID": "10816-7910404-1",
    "ResponseCode": "0",
    "ResponseDescription": "Accept the service request successfully."
  },
  "timestamp": "2023-06-15 14:30:00",
  "note": "Customer will receive funds shortly. Result callback will be logged."
}
```

### Error Response (500)
```json
{
  "success": false,
  "message": "B2C payment failed",
  "error": "Insufficient balance in B2C account"
}
```

### Logs Generated
- B2C PAYMENT TEST STARTED
- B2C Request Data
- B2C payment initiated (from MpesaService)
- B2C PAYMENT TEST COMPLETED

---

## 4. C2B Registration Test

Test the registration of C2B validation and confirmation URLs with Safaricom.

### Endpoint
```
GET /api/v1/mpesa/test-c2b
```

### Headers
```
Content-Type: application/json
Accept: application/json
```

### Request Body
None (GET request)

### Example Postman Request
```bash
curl --location 'http://localhost:8000/api/v1/mpesa/test-c2b' \
--header 'Accept: application/json'
```

### Success Response (200)
```json
{
  "success": true,
  "message": "C2B registration completed",
  "token_test": {
    "token_generated": true,
    "environment": "sandbox",
    "token_length": 200,
    "expires_in": 3599,
    "http_code": 200
  },
  "registration_result": {
    "success": true,
    "data": {
      "OriginatorCoversationID": "10816-7910404-1",
      "ResponseCode": "0",
      "ResponseDescription": "Success"
    },
    "environment": "sandbox"
  },
  "timestamp": "2023-06-15 14:30:00",
  "note": "After successful registration, customers can send payments to the paybill number."
}
```

### Logs Generated
- C2B REGISTRATION TEST STARTED
- C2B Registration Config
- C2B registration response (from MpesaService)
- C2B REGISTRATION TEST COMPLETED

---

## 5. C2B Validation Test

Test the C2B validation callback. This simulates what Safaricom sends to your validation URL when a customer makes a paybill payment.

### Endpoint
```
POST /api/v1/mpesa/test-c2b-validation
```

### Headers
```
Content-Type: application/json
Accept: application/json
```

### Request Body
```json
{
  "TransactionType": "Pay Bill",
  "TransID": "OEI2AK4Q16",
  "TransTime": "20230615143000",
  "TransAmount": "100.00",
  "BusinessShortCode": "174379",
  "BillRefNumber": "LOAN-001",
  "InvoiceNumber": "",
  "OrgAccountBalance": "10000.00",
  "ThirdPartyTransID": "",
  "MSISDN": "254712345678",
  "FirstName": "John",
  "MiddleName": "Doe",
  "LastName": "Smith"
}
```

### Field Descriptions
- `TransactionType`: Type of transaction (usually "Pay Bill")
- `TransID`: M-Pesa transaction ID
- `TransTime`: Transaction time (format: YYYYMMDDHHmmss)
- `TransAmount`: Transaction amount
- `BusinessShortCode`: Your paybill/till number
- `BillRefNumber`: Account reference (loan number or employee ID in your system)
- `MSISDN`: Customer's phone number
- `FirstName`, `MiddleName`, `LastName`: Customer's names

### Example Postman Request
```bash
curl --location 'http://localhost:8000/api/v1/mpesa/test-c2b-validation' \
--header 'Content-Type: application/json' \
--header 'Accept: application/json' \
--data '{
  "TransactionType": "Pay Bill",
  "TransID": "OEI2AK4Q16",
  "TransTime": "20230615143000",
  "TransAmount": "100.00",
  "BusinessShortCode": "174379",
  "BillRefNumber": "LOAN-001",
  "InvoiceNumber": "",
  "OrgAccountBalance": "10000.00",
  "ThirdPartyTransID": "",
  "MSISDN": "254712345678",
  "FirstName": "John",
  "MiddleName": "Doe",
  "LastName": "Smith"
}'
```

### Success Response (200) - Valid Payment
```json
{
  "ResultCode": 0,
  "ResultDesc": "Accepted",
  "validation_details": {
    "valid": true,
    "loan_id": 123,
    "loan_number": "LOAN-001",
    "loan_balance": 5000.00,
    "employee_id": 456
  }
}
```

### Rejection Response (200) - Invalid Payment
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

### Logs Generated
- C2B VALIDATION TEST STARTED
- C2B Validation Result
- C2B VALIDATION TEST COMPLETED

---

## 6. C2B Confirmation Test

Test the C2B confirmation callback. This simulates what Safaricom sends to your confirmation URL after a successful paybill payment.

### Endpoint
```
POST /api/v1/mpesa/test-c2b-confirmation
```

### Headers
```
Content-Type: application/json
Accept: application/json
```

### Request Body
```json
{
  "TransactionType": "Pay Bill",
  "TransID": "OEI2AK4Q16",
  "TransTime": "20230615143000",
  "TransAmount": "100.00",
  "BusinessShortCode": "174379",
  "BillRefNumber": "LOAN-001",
  "InvoiceNumber": "",
  "OrgAccountBalance": "10000.00",
  "ThirdPartyTransID": "",
  "MSISDN": "254712345678",
  "FirstName": "John",
  "MiddleName": "Doe",
  "LastName": "Smith"
}
```

### Example Postman Request
```bash
curl --location 'http://localhost:8000/api/v1/mpesa/test-c2b-confirmation' \
--header 'Content-Type: application/json' \
--header 'Accept: application/json' \
--data '{
  "TransactionType": "Pay Bill",
  "TransID": "OEI2AK4Q16",
  "TransTime": "20230615143000",
  "TransAmount": "100.00",
  "BusinessShortCode": "174379",
  "BillRefNumber": "LOAN-001",
  "InvoiceNumber": "",
  "OrgAccountBalance": "10000.00",
  "ThirdPartyTransID": "",
  "MSISDN": "254712345678",
  "FirstName": "John",
  "MiddleName": "Doe",
  "LastName": "Smith"
}'
```

### Success Response (200)
```json
{
  "ResultCode": 0,
  "ResultDesc": "Accepted",
  "processing_result": {
    "success": true,
    "message": "Payment processed successfully",
    "transaction_id": "TXN123456",
    "loan_id": 123
  }
}
```

### Logs Generated
- C2B CONFIRMATION TEST STARTED
- C2B Confirmation Result
- Paybill payment processed successfully (from MpesaService)
- Loan payment processed successfully (from MpesaService)
- C2B CONFIRMATION TEST COMPLETED

---

## 7. B2C Result Callback Test

Test the B2C result callback. This simulates what Safaricom sends to your B2C result URL after a B2C payment is processed.

### Endpoint
```
POST /api/v1/mpesa/test-b2c-result
```

### Headers
```
Content-Type: application/json
Accept: application/json
```

### Request Body
```json
{
  "Result": {
    "ResultType": 0,
    "ResultCode": 0,
    "ResultDesc": "The service request is processed successfully.",
    "OriginatorConversationID": "10816-7910404-1",
    "ConversationID": "AG_20230615_00004f7e3b9f9e3c9b1e",
    "TransactionID": "OEI2AK4Q16",
    "ResultParameters": {
      "ResultParameter": [
        {
          "Key": "TransactionReceipt",
          "Value": "OEI2AK4Q16"
        },
        {
          "Key": "TransactionAmount",
          "Value": 100
        },
        {
          "Key": "B2CWorkingAccountAvailableFunds",
          "Value": 50000
        },
        {
          "Key": "B2CUtilityAccountAvailableFunds",
          "Value": 10000
        },
        {
          "Key": "TransactionCompletedDateTime",
          "Value": "15.06.2023 14:30:00"
        },
        {
          "Key": "ReceiverPartyPublicName",
          "Value": "254712345678 - John Doe"
        },
        {
          "Key": "B2CChargesPaidAccountAvailableFunds",
          "Value": 0
        },
        {
          "Key": "B2CRecipientIsRegisteredCustomer",
          "Value": "Y"
        }
      ]
    },
    "ReferenceData": {
      "ReferenceItem": {
        "Key": "QueueTimeoutURL",
        "Value": "https://internalsandbox.safaricom.co.ke/mpesa/b2cresults/v1/submit"
      }
    }
  }
}
```

### Example Postman Request
```bash
curl --location 'http://localhost:8000/api/v1/mpesa/test-b2c-result' \
--header 'Content-Type: application/json' \
--header 'Accept: application/json' \
--data '{
  "Result": {
    "ResultType": 0,
    "ResultCode": 0,
    "ResultDesc": "The service request is processed successfully.",
    "OriginatorConversationID": "10816-7910404-1",
    "ConversationID": "AG_20230615_00004f7e3b9f9e3c9b1e",
    "TransactionID": "OEI2AK4Q16",
    "ResultParameters": {
      "ResultParameter": [
        {"Key": "TransactionReceipt", "Value": "OEI2AK4Q16"},
        {"Key": "TransactionAmount", "Value": 100},
        {"Key": "B2CWorkingAccountAvailableFunds", "Value": 50000},
        {"Key": "B2CUtilityAccountAvailableFunds", "Value": 10000},
        {"Key": "TransactionCompletedDateTime", "Value": "15.06.2023 14:30:00"},
        {"Key": "ReceiverPartyPublicName", "Value": "254712345678 - John Doe"},
        {"Key": "B2CChargesPaidAccountAvailableFunds", "Value": 0},
        {"Key": "B2CRecipientIsRegisteredCustomer", "Value": "Y"}
      ]
    }
  }
}'
```

### Success Response (200)
```json
{
  "ResultCode": 0,
  "ResultDesc": "Accepted",
  "note": "B2C result received and logged successfully"
}
```

### Logs Generated
- B2C RESULT CALLBACK TEST STARTED
- B2C Result Details
- B2C RESULT CALLBACK TEST COMPLETED

---

## 8. B2C Timeout Callback Test

Test the B2C timeout callback. This simulates what Safaricom sends when a B2C payment request times out.

### Endpoint
```
POST /api/v1/mpesa/test-b2c-timeout
```

### Headers
```
Content-Type: application/json
Accept: application/json
```

### Request Body
```json
{
  "Result": {
    "ResultType": 0,
    "ResultCode": 1,
    "ResultDesc": "The service request has timed out.",
    "OriginatorConversationID": "10816-7910404-1",
    "ConversationID": "AG_20230615_00004f7e3b9f9e3c9b1e",
    "TransactionID": ""
  }
}
```

### Example Postman Request
```bash
curl --location 'http://localhost:8000/api/v1/mpesa/test-b2c-timeout' \
--header 'Content-Type: application/json' \
--header 'Accept: application/json' \
--data '{
  "Result": {
    "ResultType": 0,
    "ResultCode": 1,
    "ResultDesc": "The service request has timed out.",
    "OriginatorConversationID": "10816-7910404-1",
    "ConversationID": "AG_20230615_00004f7e3b9f9e3c9b1e",
    "TransactionID": ""
  }
}'
```

### Success Response (200)
```json
{
  "ResultCode": 0,
  "ResultDesc": "Accepted"
}
```

### Logs Generated
- B2C TIMEOUT CALLBACK TEST STARTED
- B2C Timeout Details
- B2C TIMEOUT CALLBACK TEST COMPLETED

---

## 9. Get Test Transactions

Retrieve the 20 most recent M-Pesa transactions for debugging purposes.

### Endpoint
```
GET /api/v1/mpesa/test-transactions
```

### Headers
```
Content-Type: application/json
Accept: application/json
```

### Request Body
None (GET request)

### Example Postman Request
```bash
curl --location 'http://localhost:8000/api/v1/mpesa/test-transactions' \
--header 'Accept: application/json'
```

### Success Response (200)
```json
{
  "success": true,
  "message": "Recent transactions retrieved",
  "count": 20,
  "transactions": [
    {
      "id": 1,
      "transaction_id": "TXN123456",
      "phone_number": "254712345678",
      "amount": 100,
      "account_reference": "TEST-001",
      "transaction_description": "Test Payment",
      "transaction_type": "STK_PUSH",
      "status": "SUCCESS",
      "mpesa_receipt_number": "OEI2AK4Q16",
      "checkout_request_id": "ws_CO_15062023143000000001",
      "merchant_request_id": "12345-67890-1",
      "result_code": 0,
      "result_desc": "The service request is processed successfully.",
      "transaction_date": "2023-06-15 14:30:00",
      "created_at": "2023-06-15 14:29:00",
      "updated_at": "2023-06-15 14:30:15"
    }
  ]
}
```

---

## 10. Logging

All test endpoints include comprehensive logging to help you debug issues. Logs are written to your Laravel log file (typically `storage/logs/laravel.log`).

### Log Format

Logs are formatted with clear markers for easy searching:

```
[timestamp] local.INFO: === STK PUSH TEST STARTED === 
[timestamp] local.INFO: STK Push Request Data 
[timestamp] local.INFO: === STK PUSH TEST COMPLETED ===
```

### Viewing Logs

#### Option 1: Tail the log file (Linux/Mac)
```bash
tail -f storage/logs/laravel.log
```

#### Option 2: Filter specific tests
```bash
grep "=== STK PUSH" storage/logs/laravel.log
```

#### Option 3: View in real-time with color
```bash
tail -f storage/logs/laravel.log | grep --color=always "==="
```

### Log Markers by Endpoint

1. **Access Token Test**
   - `=== M-PESA ACCESS TOKEN TEST STARTED ===`
   - `=== M-PESA ACCESS TOKEN TEST COMPLETED ===`
   - `=== M-PESA ACCESS TOKEN TEST FAILED ===`

2. **STK Push Test**
   - `=== STK PUSH TEST STARTED ===`
   - `=== STK PUSH TEST COMPLETED ===`
   - `=== STK PUSH TEST FAILED ===`

3. **B2C Payment Test**
   - `=== B2C PAYMENT TEST STARTED ===`
   - `=== B2C PAYMENT TEST COMPLETED ===`
   - `=== B2C PAYMENT TEST FAILED ===`

4. **C2B Registration Test**
   - `=== C2B REGISTRATION TEST STARTED ===`
   - `=== C2B REGISTRATION TEST COMPLETED ===`
   - `=== C2B REGISTRATION TEST FAILED ===`

5. **C2B Validation Test**
   - `=== C2B VALIDATION TEST STARTED ===`
   - `=== C2B VALIDATION TEST COMPLETED ===`
   - `=== C2B VALIDATION TEST FAILED ===`

6. **C2B Confirmation Test**
   - `=== C2B CONFIRMATION TEST STARTED ===`
   - `=== C2B CONFIRMATION TEST COMPLETED ===`
   - `=== C2B CONFIRMATION TEST FAILED ===`

7. **B2C Result Callback Test**
   - `=== B2C RESULT CALLBACK TEST STARTED ===`
   - `=== B2C RESULT CALLBACK TEST COMPLETED ===`
   - `=== B2C RESULT CALLBACK TEST FAILED ===`

8. **B2C Timeout Callback Test**
   - `=== B2C TIMEOUT CALLBACK TEST STARTED ===`
   - `=== B2C TIMEOUT CALLBACK TEST COMPLETED ===`
   - `=== B2C TIMEOUT CALLBACK TEST FAILED ===`

---

## Testing Workflow

### 1. Initial Setup Test
```
1. Test Access Token → Verify credentials are correct
2. Test C2B Registration → Register callback URLs with Safaricom
```

### 2. Test STK Push Flow
```
1. Test STK Push → Initiates payment request
2. Check logs for checkout request ID
3. Check phone for M-Pesa prompt
4. Accept payment on phone
5. Wait for automatic callback (logged)
6. View transaction in test-transactions endpoint
```

### 3. Test C2B Flow
```
1. Test C2B Validation → Verify validation logic works
2. Test C2B Confirmation → Verify payment processing works
3. Check logs for transaction creation
4. View transaction in test-transactions endpoint
```

### 4. Test B2C Flow
```
1. Test B2C Payment → Initiates disbursement
2. Check logs for conversation ID
3. Test B2C Result → Simulate successful result callback
4. Test B2C Timeout → Simulate timeout callback
5. View transaction in test-transactions endpoint
```

---

## Common Issues and Solutions

### Issue 1: Access Token Generation Fails
**Solution**: Check your `.env` file has correct M-Pesa credentials:
```env
MPESA_ENVIRONMENT=sandbox
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
```

### Issue 2: STK Push Prompt Not Received
**Solutions**:
- Verify phone number format is 254XXXXXXXXX
- Check if amount is within limits (minimum 1)
- Verify callback URL is publicly accessible
- Check sandbox/production environment matches your app registration

### Issue 3: C2B Registration Fails
**Solutions**:
- Ensure validation and confirmation URLs are publicly accessible
- Check shortcode is correctly configured
- Verify environment (sandbox vs production) matches your app

### Issue 4: B2C Payment Fails
**Solutions**:
- Ensure sufficient balance in B2C account
- Verify initiator name and security credential are correct
- Check B2C shortcode is properly configured
- Verify result and timeout URLs are publicly accessible

---

## Environment Configuration

Required `.env` variables for M-Pesa testing:

```env
# M-Pesa Environment (sandbox or production)
MPESA_ENVIRONMENT=sandbox

# Consumer Key and Secret (from Safaricom Daraja Portal)
MPESA_CONSUMER_KEY=your_consumer_key_here
MPESA_CONSUMER_SECRET=your_consumer_secret_here

# STK Push Configuration
MPESA_BUSINESS_SHORTCODE=174379
SAFARICOM_PASSKEY=your_passkey_here

# B2C Configuration
MPESA_B2C_SHORTCODE=your_b2c_shortcode
MPESA_INITIATOR_NAME=testapi
MPESA_INITIATOR_PASSWORD=your_initiator_password

# Callback URLs (must be publicly accessible)
MPESA_CALLBACK_URL=https://your-domain.com/api/v1/mpesa/stk/callback
MPESA_C2B_VALIDATION_URL=https://your-domain.com/api/v1/c2b/validation
MPESA_C2B_CONFIRMATION_URL=https://your-domain.com/api/v1/c2b/confirmation
MPESA_B2C_RESULT_URL=https://your-domain.com/api/v1/mpesa/b2c/result
MPESA_B2C_TIMEOUT_URL=https://your-domain.com/api/v1/mpesa/b2c/timeout
```

---

## Security Notes

⚠️ **IMPORTANT**: These test endpoints should be removed or restricted in production:

1. Add authentication middleware to test routes
2. Restrict access by IP address
3. Remove test routes entirely before deploying to production
4. Use environment-based route registration

Example production-safe route configuration:
```php
// In routes/api.php
if (config('app.env') !== 'production') {
    Route::prefix('mpesa')->group(function () {
        // Test routes here
    });
}
```

---

## Support

For issues or questions:
1. Check the logs in `storage/logs/laravel.log`
2. Verify your M-Pesa configuration in `.env`
3. Consult Safaricom Daraja API documentation
4. Contact your development team

---

## Postman Collection

You can import these endpoints into Postman by creating a new collection and adding each endpoint manually, or use the provided examples above to test directly.

### Quick Import Format (JSON)
Save this as `mpesa-test-collection.json` and import into Postman:

```json
{
  "info": {
    "name": "M-Pesa Test API",
    "description": "Test endpoints for M-Pesa integrations",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "1. Test Access Token",
      "request": {
        "method": "GET",
        "header": [],
        "url": {
          "raw": "{{base_url}}/api/v1/mpesa/test-token",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "mpesa", "test-token"]
        }
      }
    },
    {
      "name": "2. Test STK Push",
      "request": {
        "method": "POST",
        "header": [{"key": "Content-Type", "value": "application/json"}],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"phone_number\": \"254712345678\",\n  \"amount\": 100,\n  \"account_reference\": \"TEST-001\",\n  \"transaction_description\": \"Test STK Push Payment\"\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/v1/mpesa/test-stk-push",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "mpesa", "test-stk-push"]
        }
      }
    },
    {
      "name": "3. Test B2C Payment",
      "request": {
        "method": "POST",
        "header": [{"key": "Content-Type", "value": "application/json"}],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"phone_number\": \"254712345678\",\n  \"amount\": 100,\n  \"command_id\": \"BusinessPayment\",\n  \"remarks\": \"Test B2C Payment\",\n  \"occasion\": \"Testing\"\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/v1/mpesa/test-b2c",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "mpesa", "test-b2c"]
        }
      }
    },
    {
      "name": "4. Test C2B Registration",
      "request": {
        "method": "GET",
        "header": [],
        "url": {
          "raw": "{{base_url}}/api/v1/mpesa/test-c2b",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "mpesa", "test-c2b"]
        }
      }
    },
    {
      "name": "5. Test C2B Validation",
      "request": {
        "method": "POST",
        "header": [{"key": "Content-Type", "value": "application/json"}],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"TransactionType\": \"Pay Bill\",\n  \"TransID\": \"OEI2AK4Q16\",\n  \"TransTime\": \"20230615143000\",\n  \"TransAmount\": \"100.00\",\n  \"BusinessShortCode\": \"174379\",\n  \"BillRefNumber\": \"LOAN-001\",\n  \"InvoiceNumber\": \"\",\n  \"OrgAccountBalance\": \"10000.00\",\n  \"ThirdPartyTransID\": \"\",\n  \"MSISDN\": \"254712345678\",\n  \"FirstName\": \"John\",\n  \"MiddleName\": \"Doe\",\n  \"LastName\": \"Smith\"\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/v1/mpesa/test-c2b-validation",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "mpesa", "test-c2b-validation"]
        }
      }
    },
    {
      "name": "6. Test C2B Confirmation",
      "request": {
        "method": "POST",
        "header": [{"key": "Content-Type", "value": "application/json"}],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"TransactionType\": \"Pay Bill\",\n  \"TransID\": \"OEI2AK4Q16\",\n  \"TransTime\": \"20230615143000\",\n  \"TransAmount\": \"100.00\",\n  \"BusinessShortCode\": \"174379\",\n  \"BillRefNumber\": \"LOAN-001\",\n  \"InvoiceNumber\": \"\",\n  \"OrgAccountBalance\": \"10000.00\",\n  \"ThirdPartyTransID\": \"\",\n  \"MSISDN\": \"254712345678\",\n  \"FirstName\": \"John\",\n  \"MiddleName\": \"Doe\",\n  \"LastName\": \"Smith\"\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/v1/mpesa/test-c2b-confirmation",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "mpesa", "test-c2b-confirmation"]
        }
      }
    },
    {
      "name": "7. Test B2C Result",
      "request": {
        "method": "POST",
        "header": [{"key": "Content-Type", "value": "application/json"}],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"Result\": {\n    \"ResultType\": 0,\n    \"ResultCode\": 0,\n    \"ResultDesc\": \"The service request is processed successfully.\",\n    \"OriginatorConversationID\": \"10816-7910404-1\",\n    \"ConversationID\": \"AG_20230615_00004f7e3b9f9e3c9b1e\",\n    \"TransactionID\": \"OEI2AK4Q16\"\n  }\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/v1/mpesa/test-b2c-result",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "mpesa", "test-b2c-result"]
        }
      }
    },
    {
      "name": "8. Test B2C Timeout",
      "request": {
        "method": "POST",
        "header": [{"key": "Content-Type", "value": "application/json"}],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"Result\": {\n    \"ResultType\": 0,\n    \"ResultCode\": 1,\n    \"ResultDesc\": \"The service request has timed out.\",\n    \"OriginatorConversationID\": \"10816-7910404-1\",\n    \"ConversationID\": \"AG_20230615_00004f7e3b9f9e3c9b1e\",\n    \"TransactionID\": \"\"\n  }\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/v1/mpesa/test-b2c-timeout",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "mpesa", "test-b2c-timeout"]
        }
      }
    },
    {
      "name": "9. Get Test Transactions",
      "request": {
        "method": "GET",
        "header": [],
        "url": {
          "raw": "{{base_url}}/api/v1/mpesa/test-transactions",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "mpesa", "test-transactions"]
        }
      }
    }
  ],
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost:8000",
      "type": "string"
    }
  ]
}
```

---

**Last Updated**: November 16, 2025
**Version**: 1.0.0

