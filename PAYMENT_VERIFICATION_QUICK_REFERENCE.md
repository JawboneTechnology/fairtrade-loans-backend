# Payment Verification - Quick Reference Card

## 🚀 Quick Start

### 1️⃣ Initiate STK Push
```bash
POST /api/v1/mpesa/stk-push
Authorization: Bearer {token}

{
  "phone_number": "254712345678",
  "amount": 100,
  "account_reference": "LOAN-001",
  "transaction_description": "Loan payment"
}
```

**Response**: Save the `checkout_request_id`

---

### 2️⃣ Verify Payment (Poll every 3 seconds)
```bash
POST /api/v1/mpesa/verify-payment
Authorization: Bearer {token}

{
  "checkout_request_id": "ws_CO_17112025102245"
}
```

---

## 📊 Response Status Codes

| Status | Payment Complete | Action |
|--------|------------------|--------|
| `SUCCESS` | ✅ Yes | Show success, stop polling |
| `PENDING` | ⏳ No | Continue polling |
| `FAILED` | ❌ No | Show error, stop polling |
| `NOT_FOUND` | 🔍 N/A | Check ID, retry |
| `ERROR` | 💥 N/A | Show error, retry later |

---

## ✅ Success Response (200)
```json
{
  "success": true,
  "payment_complete": true,
  "status": "SUCCESS",
  "data": {
    "amount_paid": 100.0,
    "mpesa_receipt_number": "OEI2AK4Q16"
  }
}
```

---

## ⏳ Pending Response (200)
```json
{
  "success": true,
  "payment_complete": false,
  "status": "PENDING",
  "message": "Payment is still pending..."
}
```
**→ Continue polling**

---

## ❌ Failed Response (400)
```json
{
  "success": false,
  "payment_complete": false,
  "status": "FAILED",
  "data": {
    "result_code": "1032",
    "result_description": "Request cancelled by user"
  }
}
```

---

## 🔍 Not Found Response (404)
```json
{
  "success": false,
  "payment_complete": false,
  "status": "NOT_FOUND",
  "message": "Transaction not found. Please try again."
}
```

---

## 💡 Polling Best Practices

```javascript
// Recommended settings
const POLLING_CONFIG = {
  interval: 3000,      // 3 seconds between checks
  maxAttempts: 20,     // 60 seconds total (20 × 3s)
  initialDelay: 2000   // Wait 2s before first check
};
```

### Example Polling Logic
```javascript
async function pollPayment(checkoutRequestId) {
  const MAX_ATTEMPTS = 20;
  const INTERVAL = 3000;
  
  for (let i = 0; i < MAX_ATTEMPTS; i++) {
    const result = await verifyPayment(checkoutRequestId);
    
    if (result.status === 'SUCCESS') {
      return result; // ✅ Payment successful
    } else if (result.status === 'FAILED') {
      throw new Error(result.message); // ❌ Payment failed
    }
    
    // ⏳ Still pending, wait and retry
    await sleep(INTERVAL);
  }
  
  throw new Error('Timeout'); // ⏱️ Max attempts reached
}
```

---

## 🧪 cURL Test Commands

### Test 1: Initiate Payment
```bash
curl -X POST https://your-api.com/api/v1/mpesa/stk-push \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "phone_number": "254712345678",
    "amount": 10,
    "account_reference": "TEST-001",
    "transaction_description": "Test payment"
  }'
```

### Test 2: Verify Payment
```bash
curl -X POST https://your-api.com/api/v1/mpesa/verify-payment \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "checkout_request_id": "ws_CO_17112025102245"
  }'
```

---

## 🐛 Quick Debugging

### Check Transaction in Database
```sql
SELECT 
  transaction_id,
  checkout_request_id,
  status,
  amount,
  mpesa_receipt_number,
  result_desc,
  created_at,
  updated_at
FROM mpesa_transactions
WHERE checkout_request_id = 'ws_CO_17112025102245';
```

### Check Logs
```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log | grep "PAYMENT VERIFICATION"

# Search for specific checkout request
grep "ws_CO_17112025102245" storage/logs/laravel.log
```

---

## ⚠️ Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Always PENDING | Callback not received | Check callback URL, firewall |
| Transaction Not Found | Wrong ID or DB issue | Verify ID, check DB |
| 401 Unauthorized | Missing/invalid token | Check Bearer token |
| Timeout after 60s | User hasn't paid | Ask user to check phone |

---

## 📱 User Flow

```
1. User clicks "Pay"
   ↓
2. App calls STK Push API
   ↓
3. App shows "Check your phone..."
   ↓
4. User enters PIN on phone
   ↓
5. M-Pesa sends callback to server (automatic)
   ↓
6. App polls verify-payment every 3s
   ↓
7. Status changes from PENDING to SUCCESS
   ↓
8. App shows success message + receipt
```

---

## 🔐 Security Checklist

- ✅ Authentication required
- ✅ HTTPS only
- ✅ Rate limiting enabled
- ✅ Input validation
- ✅ Logs sanitized (no sensitive data)

---

## 📚 Related Endpoints

| Endpoint | Purpose | Auth |
|----------|---------|------|
| `POST /mpesa/stk-push` | Initiate payment | ✅ Yes |
| `POST /mpesa/verify-payment` | Check status (DB) | ✅ Yes |
| `POST /mpesa/query-status` | Check status (M-Pesa API) | ✅ Yes |
| `GET /mpesa/transactions` | Transaction history | ✅ Yes |
| `POST /mpesa/stk/callback` | M-Pesa callback | ❌ No |

---

## 💰 Cost Comparison

| Method | Cost | Speed | Reliability |
|--------|------|-------|-------------|
| `verify-payment` (DB) | FREE | Fast | Depends on callbacks |
| `query-status` (API) | API limits | Slower | Direct from M-Pesa |

**Recommendation**: Use `verify-payment` for normal flow.

---

## ⏱️ Timing Guidelines

- **Initial Delay**: 2 seconds after STK Push
- **Polling Interval**: 3 seconds
- **Max Duration**: 60 seconds (20 attempts)
- **Success Notification**: Immediate (when status = SUCCESS)

---

## 📝 Example Responses

### ✅ SUCCESSFUL PAYMENT
```json
{
  "success": true,
  "payment_complete": true,
  "status": "SUCCESS",
  "data": {
    "transaction_id": "uuid-here",
    "amount_paid": 100.0,
    "mpesa_receipt_number": "OEI2AK4Q16",
    "transaction_date": "2025-11-17 10:30:45"
  }
}
```

### ⏳ STILL PENDING
```json
{
  "success": true,
  "payment_complete": false,
  "status": "PENDING",
  "message": "Payment is still pending..."
}
```

### ❌ USER CANCELLED
```json
{
  "success": false,
  "payment_complete": false,
  "status": "FAILED",
  "data": {
    "result_code": "1032",
    "result_description": "Request cancelled by user"
  }
}
```

### ❌ INSUFFICIENT BALANCE
```json
{
  "success": false,
  "payment_complete": false,
  "status": "FAILED",
  "data": {
    "result_code": "1",
    "result_description": "The balance is insufficient"
  }
}
```

---

## 🎯 Quick Testing Flow

```bash
# 1. Get auth token
TOKEN=$(curl -X POST https://api.com/api/v1/login \
  -d '{"email":"user@test.com","password":"password"}' | jq -r .token)

# 2. Initiate STK Push
CHECKOUT_ID=$(curl -X POST https://api.com/api/v1/mpesa/stk-push \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"phone_number":"254712345678","amount":10,"account_reference":"TEST"}' \
  | jq -r .data.checkout_request_id)

# 3. Wait 5 seconds
sleep 5

# 4. Verify payment
curl -X POST https://api.com/api/v1/mpesa/verify-payment \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"checkout_request_id\":\"$CHECKOUT_ID\"}"
```

---

**Last Updated**: November 17, 2025  
**Status**: Production Ready ✅  
**For Full Documentation**: See `PAYMENT_VERIFICATION_ENDPOINT.md`

