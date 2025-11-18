# Payment Verification Endpoint

## Overview
This endpoint verifies if an STK Push payment has been completed successfully after the M-Pesa callback has been received. It's designed to be used by the frontend to check payment status after initiating an STK Push.

---

## Endpoint Details

**URL**: `/api/v1/mpesa/verify-payment`  
**Method**: `POST`  
**Authentication**: Required (Bearer Token)  
**Content-Type**: `application/json`

---

## Use Case Flow

### Step 1: Initiate STK Push
```http
POST /api/v1/mpesa/stk-push
{
  "phone_number": "254712345678",
  "amount": 100,
  "account_reference": "LOAN-001",
  "transaction_description": "Loan payment"
}
```

**Response**:
```json
{
  "success": true,
  "message": "STK Push initiated successfully",
  "data": {
    "transaction_id": "6704b991-3b9d-4ac4-8b54-b64ef5cc653e",
    "checkout_request_id": "ws_CO_17112025102245",
    "merchant_request_id": "29115-34620561-1"
  }
}
```

### Step 2: User Enters PIN on Phone
User receives M-Pesa prompt on their phone and enters PIN.

### Step 3: M-Pesa Callback (Automatic)
M-Pesa sends callback to your server with payment result.  
**This happens automatically in the background**.

### Step 4: Verify Payment Status
Frontend polls or checks payment status using the `checkout_request_id`:

```http
POST /api/v1/mpesa/verify-payment
{
  "checkout_request_id": "ws_CO_17112025102245"
}
```

---

## Request Format

### Request Body

```json
{
  "checkout_request_id": "ws_CO_17112025102245"
}
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `checkout_request_id` | string | Yes | The checkout request ID received from STK Push initiation |

---

## Response Formats

### 1. ✅ Payment Successful (200 OK)

```json
{
  "success": true,
  "payment_complete": true,
  "message": "Payment completed successfully",
  "status": "SUCCESS",
  "data": {
    "transaction_id": "6704b991-3b9d-4ac4-8b54-b64ef5cc653e",
    "amount_paid": 100.0,
    "mpesa_receipt_number": "OEI2AK4Q16",
    "transaction_date": "2025-11-17 10:30:45",
    "phone_number": "254712345678",
    "account_reference": "LOAN-001",
    "result_description": "The service request is processed successfully."
  }
}
```

---

### 2. ⏳ Payment Pending (200 OK)

```json
{
  "success": true,
  "payment_complete": false,
  "message": "Payment is still pending. Please check your phone for M-Pesa prompt.",
  "status": "PENDING",
  "data": {
    "transaction_id": "6704b991-3b9d-4ac4-8b54-b64ef5cc653e",
    "amount": 100.0,
    "phone_number": "254712345678",
    "account_reference": "LOAN-001"
  }
}
```

**Recommendation**: Continue polling every 2-3 seconds for up to 60 seconds.

---

### 3. ❌ Payment Failed (400 Bad Request)

```json
{
  "success": false,
  "payment_complete": false,
  "message": "Payment failed or was cancelled",
  "status": "FAILED",
  "data": {
    "transaction_id": "6704b991-3b9d-4ac4-8b54-b64ef5cc653e",
    "result_code": "1032",
    "result_description": "Request cancelled by user",
    "amount": 100.0
  }
}
```

**Common Failure Reasons**:
- User cancelled the request
- User entered wrong PIN
- Insufficient balance
- Request timeout

---

### 4. 🔍 Transaction Not Found (404 Not Found)

```json
{
  "success": false,
  "payment_complete": false,
  "message": "Transaction not found. Please try again.",
  "status": "NOT_FOUND"
}
```

**Possible Causes**:
- Invalid checkout_request_id
- Transaction hasn't been created yet
- Database issue

---

### 5. 💥 Server Error (500 Internal Server Error)

```json
{
  "success": false,
  "payment_complete": false,
  "message": "Error verifying payment status",
  "status": "ERROR",
  "error": "Database connection failed"
}
```

---

## Status Codes Explained

| Status | Meaning | Action |
|--------|---------|--------|
| `SUCCESS` | ✅ Payment completed | Show success message, update UI |
| `PENDING` | ⏳ Waiting for user PIN | Keep polling, show waiting message |
| `FAILED` | ❌ Payment failed | Show error, allow retry |
| `NOT_FOUND` | 🔍 Transaction not in DB | Check checkout_request_id |
| `ERROR` | 💥 Server error | Show generic error, try again later |

---

## Frontend Implementation

### Example: React/TypeScript

```typescript
interface VerifyPaymentRequest {
  checkout_request_id: string;
}

interface VerifyPaymentResponse {
  success: boolean;
  payment_complete: boolean;
  message: string;
  status: 'SUCCESS' | 'PENDING' | 'FAILED' | 'NOT_FOUND' | 'ERROR';
  data?: {
    transaction_id?: string;
    amount_paid?: number;
    mpesa_receipt_number?: string;
    transaction_date?: string;
    phone_number?: string;
    account_reference?: string;
    result_description?: string;
    amount?: number;
    result_code?: string;
  };
}

async function verifyPayment(
  checkoutRequestId: string
): Promise<VerifyPaymentResponse> {
  const response = await fetch('/api/v1/mpesa/verify-payment', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${getAuthToken()}`,
      'Accept': 'application/json'
    },
    body: JSON.stringify({ checkout_request_id: checkoutRequestId })
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message);
  }

  return await response.json();
}

// Usage with polling
async function pollPaymentStatus(
  checkoutRequestId: string,
  maxAttempts: number = 20,
  interval: number = 3000
): Promise<VerifyPaymentResponse> {
  for (let i = 0; i < maxAttempts; i++) {
    try {
      const result = await verifyPayment(checkoutRequestId);
      
      if (result.status === 'SUCCESS') {
        // Payment successful!
        return result;
      } else if (result.status === 'FAILED') {
        // Payment failed
        throw new Error(result.message);
      } else if (result.status === 'PENDING') {
        // Still pending, continue polling
        await new Promise(resolve => setTimeout(resolve, interval));
        continue;
      } else {
        // NOT_FOUND or ERROR
        throw new Error(result.message);
      }
    } catch (error) {
      if (i === maxAttempts - 1) {
        throw error; // Last attempt failed
      }
      await new Promise(resolve => setTimeout(resolve, interval));
    }
  }
  
  throw new Error('Payment verification timeout');
}

// Example usage
try {
  // Step 1: Initiate STK Push
  const stkResponse = await initiateStkPush({
    phone_number: '254712345678',
    amount: 100,
    account_reference: 'LOAN-001',
    transaction_description: 'Loan payment'
  });
  
  const checkoutRequestId = stkResponse.data.checkout_request_id;
  
  // Step 2: Show waiting message
  showMessage('Please check your phone for M-Pesa prompt...');
  
  // Step 3: Poll for payment status
  const result = await pollPaymentStatus(checkoutRequestId);
  
  // Step 4: Show success message
  showSuccess(`Payment of KES ${result.data.amount_paid} completed!`);
  showReceipt(result.data.mpesa_receipt_number);
  
} catch (error) {
  showError(error.message);
}
```

---

### Example: JavaScript (Vanilla)

```javascript
// Initiate STK Push and poll for result
async function processPayment(phoneNumber, amount, accountReference) {
  try {
    // Step 1: Initiate STK Push
    const stkResponse = await fetch('/api/v1/mpesa/stk-push', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + getToken()
      },
      body: JSON.stringify({
        phone_number: phoneNumber,
        amount: amount,
        account_reference: accountReference,
        transaction_description: 'Payment'
      })
    });

    const stkData = await stkResponse.json();
    
    if (!stkData.success) {
      throw new Error(stkData.message);
    }

    const checkoutRequestId = stkData.data.checkout_request_id;
    
    // Step 2: Show waiting message
    updateUI('Please check your phone for M-Pesa prompt...');
    
    // Step 3: Poll for payment status (every 3 seconds, max 20 attempts = 60 seconds)
    let attempts = 0;
    const maxAttempts = 20;
    
    const pollInterval = setInterval(async () => {
      attempts++;
      
      try {
        const verifyResponse = await fetch('/api/v1/mpesa/verify-payment', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + getToken()
          },
          body: JSON.stringify({ checkout_request_id: checkoutRequestId })
        });

        const verifyData = await verifyResponse.json();
        
        if (verifyData.status === 'SUCCESS') {
          clearInterval(pollInterval);
          showSuccess(
            'Payment successful!',
            `Amount: KES ${verifyData.data.amount_paid}`,
            `Receipt: ${verifyData.data.mpesa_receipt_number}`
          );
        } else if (verifyData.status === 'FAILED') {
          clearInterval(pollInterval);
          showError('Payment failed: ' + verifyData.message);
        } else if (verifyData.status === 'PENDING') {
          updateUI(`Waiting for payment... (${attempts}/${maxAttempts})`);
        } else {
          clearInterval(pollInterval);
          showError(verifyData.message);
        }
        
        // Stop polling after max attempts
        if (attempts >= maxAttempts) {
          clearInterval(pollInterval);
          showError('Payment verification timeout. Please check transaction history.');
        }
        
      } catch (error) {
        clearInterval(pollInterval);
        showError('Error verifying payment: ' + error.message);
      }
    }, 3000); // Poll every 3 seconds
    
  } catch (error) {
    showError('Error initiating payment: ' + error.message);
  }
}
```

---

## Postman Testing

### 1. Initiate STK Push
```http
POST {{base_url}}/api/v1/mpesa/stk-push
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "phone_number": "254712345678",
  "amount": 10,
  "account_reference": "TEST-001",
  "transaction_description": "Test payment"
}
```

**Save** the `checkout_request_id` from the response.

---

### 2. Verify Payment (Wait 5-10 seconds after STK Push)
```http
POST {{base_url}}/api/v1/mpesa/verify-payment
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "checkout_request_id": "ws_CO_17112025102245"
}
```

---

## Best Practices

### 1. **Polling Strategy**
```javascript
// Recommended polling parameters
const POLLING_INTERVAL = 3000;  // 3 seconds
const MAX_ATTEMPTS = 20;         // 60 seconds total
const INITIAL_DELAY = 2000;      // Wait 2s before first poll
```

### 2. **Error Handling**
- Always handle network errors
- Show user-friendly error messages
- Provide retry option for failed payments
- Log errors for debugging

### 3. **User Experience**
- Show loading spinner during polling
- Display clear status messages
- Update UI in real-time
- Provide transaction receipt on success
- Allow users to check transaction history

### 4. **Performance**
- Stop polling immediately on SUCCESS or FAILED
- Don't poll indefinitely (set max attempts)
- Use exponential backoff for retries on errors
- Cache the checkout_request_id

### 5. **Security**
- Always use HTTPS
- Include authentication token
- Validate checkout_request_id format
- Don't expose sensitive data in logs

---

## Comparison: verify-payment vs query-status

| Feature | `verify-payment` | `query-status` |
|---------|------------------|----------------|
| **Data Source** | Local database | M-Pesa API |
| **Speed** | Fast (instant) | Slower (API call) |
| **Use Case** | After STK Push callback received | Direct M-Pesa status check |
| **Reliability** | Depends on callback | Direct from source |
| **Cost** | Free | May have API limits |
| **Best For** | Normal flow | Troubleshooting |

**Recommendation**: Use `verify-payment` for normal operation. Use `query-status` only when debugging or if callbacks are delayed.

---

## Troubleshooting

### Problem: Always Getting "PENDING"
**Causes**:
- M-Pesa callback not reaching your server
- Callback URL not configured correctly
- Firewall blocking callbacks
- SSL certificate issues

**Solutions**:
1. Check callback URL in config
2. Verify URL is publicly accessible
3. Check firewall rules
4. Ensure valid SSL certificate
5. Check logs: `tail -f storage/logs/laravel.log | grep "STK PUSH CALLBACK"`

---

### Problem: "Transaction Not Found"
**Causes**:
- Wrong checkout_request_id
- Transaction creation failed
- Database issue

**Solutions**:
1. Verify checkout_request_id from STK Push response
2. Check database: `select * from mpesa_transactions where checkout_request_id = 'xxx'`
3. Check logs for errors during STK Push initiation

---

### Problem: Timeout (Still PENDING after 60s)
**Causes**:
- User hasn't entered PIN
- Network issues on user's phone
- M-Pesa service delay

**Solutions**:
1. Ask user to check their phone
2. Verify phone number is correct
3. Try `query-status` endpoint to check directly with M-Pesa
4. Check transaction history later

---

## Database Schema

The endpoint queries the `mpesa_transactions` table:

```sql
CREATE TABLE mpesa_transactions (
  transaction_id VARCHAR(36) PRIMARY KEY,
  checkout_request_id VARCHAR(255),
  merchant_request_id VARCHAR(255),
  mpesa_receipt_number VARCHAR(255),
  phone_number VARCHAR(20),
  amount DECIMAL(10, 2),
  account_reference VARCHAR(255),
  transaction_description TEXT,
  transaction_type ENUM('STK_PUSH', 'C2B', 'B2C'),
  status ENUM('PENDING', 'SUCCESS', 'FAILED'),
  result_code VARCHAR(10),
  result_desc TEXT,
  transaction_date TIMESTAMP,
  callback_data JSON,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

---

## Logs

### Example: Successful Verification
```
[2025-11-17 10:30:45] local.INFO: === PAYMENT VERIFICATION REQUESTED ===
[2025-11-17 10:30:45] local.INFO:
{
    "checkout_request_id": "ws_CO_17112025102245"
}
[2025-11-17 10:30:45] local.INFO: === PAYMENT VERIFICATION RESULT ===
[2025-11-17 10:30:45] local.INFO:
{
    "transaction_id": "6704b991-3b9d-4ac4-8b54-b64ef5cc653e",
    "checkout_request_id": "ws_CO_17112025102245",
    "status": "SUCCESS",
    "payment_complete": true,
    "amount": 100,
    "mpesa_receipt": "OEI2AK4Q16"
}
```

---

## Security Considerations

1. **Authentication Required**: Always verify user authentication
2. **Rate Limiting**: Implement rate limiting to prevent abuse
3. **Input Validation**: Validate checkout_request_id format
4. **Logging**: Log all verification attempts (already implemented)
5. **CORS**: Configure CORS properly for frontend access

---

## Related Endpoints

- **Initiate STK Push**: `POST /api/v1/mpesa/stk-push`
- **Query Status (M-Pesa API)**: `POST /api/v1/mpesa/query-status`
- **Get Transactions**: `GET /api/v1/mpesa/transactions`
- **STK Callback** (Internal): `POST /api/v1/mpesa/stk/callback`

---

**Created**: November 17, 2025  
**Status**: ✅ Production Ready  
**Version**: 1.0

