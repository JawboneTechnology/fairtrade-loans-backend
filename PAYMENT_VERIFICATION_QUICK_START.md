# Payment Verification - Quick Start Guide ⚡

## 🚀 Get Started in 3 Minutes

### Step 1: Initiate Payment
```bash
POST /api/v1/mpesa/stk-push
Authorization: Bearer YOUR_TOKEN

{
  "phone_number": "254712345678",
  "amount": 100,
  "account_reference": "ORDER-123",
  "transaction_description": "Payment for order"
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "checkout_request_id": "ws_CO_17112025102245"  👈 SAVE THIS!
  }
}
```

---

### Step 2: User Enters PIN
User receives M-Pesa prompt on their phone and enters PIN.  
**Wait 2-3 seconds before checking status.**

---

### Step 3: Check Payment Status
```bash
POST /api/v1/mpesa/verify-payment
Authorization: Bearer YOUR_TOKEN

{
  "checkout_request_id": "ws_CO_17112025102245"
}
```

---

### Step 4: Handle Response

#### ✅ Success (Payment Complete)
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
**Action**: Show success message ✨

---

#### ⏳ Pending (User hasn't paid yet)
```json
{
  "success": true,
  "payment_complete": false,
  "status": "PENDING"
}
```
**Action**: Wait 3 seconds, check again 🔄

---

#### ❌ Failed (Payment cancelled/failed)
```json
{
  "success": false,
  "payment_complete": false,
  "status": "FAILED",
  "data": {
    "result_description": "Request cancelled by user"
  }
}
```
**Action**: Show error, allow retry 🔁

---

## 💻 Copy-Paste Code Examples

### JavaScript (Fetch API)
```javascript
// 1. Initiate payment
const stkResponse = await fetch('/api/v1/mpesa/stk-push', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    phone_number: '254712345678',
    amount: 100,
    account_reference: 'ORDER-123'
  })
});

const stkData = await stkResponse.json();
const checkoutId = stkData.data.checkout_request_id;

// 2. Poll for status (every 3 seconds, max 20 times)
for (let i = 0; i < 20; i++) {
  await new Promise(resolve => setTimeout(resolve, 3000));
  
  const verifyResponse = await fetch('/api/v1/mpesa/verify-payment', {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer ' + token,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ checkout_request_id: checkoutId })
  });
  
  const verifyData = await verifyResponse.json();
  
  if (verifyData.status === 'SUCCESS') {
    alert('Payment successful! Receipt: ' + verifyData.data.mpesa_receipt_number);
    break;
  } else if (verifyData.status === 'FAILED') {
    alert('Payment failed: ' + verifyData.message);
    break;
  }
}
```

---

### Python (Requests)
```python
import requests
import time

# 1. Initiate payment
stk_response = requests.post(
    'http://localhost:8000/api/v1/mpesa/stk-push',
    headers={'Authorization': f'Bearer {token}'},
    json={
        'phone_number': '254712345678',
        'amount': 100,
        'account_reference': 'ORDER-123'
    }
)

checkout_id = stk_response.json()['data']['checkout_request_id']

# 2. Poll for status
for i in range(20):
    time.sleep(3)
    
    verify_response = requests.post(
        'http://localhost:8000/api/v1/mpesa/verify-payment',
        headers={'Authorization': f'Bearer {token}'},
        json={'checkout_request_id': checkout_id}
    )
    
    data = verify_response.json()
    
    if data['status'] == 'SUCCESS':
        print(f"Payment successful! Receipt: {data['data']['mpesa_receipt_number']}")
        break
    elif data['status'] == 'FAILED':
        print(f"Payment failed: {data['message']}")
        break
```

---

### PHP (Guzzle)
```php
use GuzzleHttp\Client;

$client = new Client(['base_uri' => 'http://localhost:8000']);

// 1. Initiate payment
$stkResponse = $client->post('/api/v1/mpesa/stk-push', [
    'headers' => ['Authorization' => 'Bearer ' . $token],
    'json' => [
        'phone_number' => '254712345678',
        'amount' => 100,
        'account_reference' => 'ORDER-123'
    ]
]);

$checkoutId = json_decode($stkResponse->getBody())->data->checkout_request_id;

// 2. Poll for status
for ($i = 0; $i < 20; $i++) {
    sleep(3);
    
    $verifyResponse = $client->post('/api/v1/mpesa/verify-payment', [
        'headers' => ['Authorization' => 'Bearer ' . $token],
        'json' => ['checkout_request_id' => $checkoutId]
    ]);
    
    $data = json_decode($verifyResponse->getBody());
    
    if ($data->status === 'SUCCESS') {
        echo "Payment successful! Receipt: {$data->data->mpesa_receipt_number}";
        break;
    } elseif ($data->status === 'FAILED') {
        echo "Payment failed: {$data->message}";
        break;
    }
}
```

---

### React Hook
```typescript
import { useState, useEffect } from 'react';

function usePaymentVerification(checkoutRequestId: string) {
  const [status, setStatus] = useState<'PENDING' | 'SUCCESS' | 'FAILED'>('PENDING');
  const [data, setData] = useState<any>(null);
  
  useEffect(() => {
    if (!checkoutRequestId) return;
    
    const pollPayment = async () => {
      for (let i = 0; i < 20; i++) {
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        const response = await fetch('/api/v1/mpesa/verify-payment', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ checkout_request_id: checkoutRequestId })
        });
        
        const result = await response.json();
        
        if (result.status === 'SUCCESS') {
          setStatus('SUCCESS');
          setData(result.data);
          break;
        } else if (result.status === 'FAILED') {
          setStatus('FAILED');
          setData(result.data);
          break;
        }
      }
    };
    
    pollPayment();
  }, [checkoutRequestId]);
  
  return { status, data };
}

// Usage in component
function CheckoutPage() {
  const [checkoutId, setCheckoutId] = useState('');
  const { status, data } = usePaymentVerification(checkoutId);
  
  const handlePayment = async () => {
    const response = await initiateStkPush();
    setCheckoutId(response.data.checkout_request_id);
  };
  
  return (
    <div>
      <button onClick={handlePayment}>Pay Now</button>
      
      {status === 'PENDING' && <p>Waiting for payment...</p>}
      {status === 'SUCCESS' && <p>✅ Payment successful! Receipt: {data.mpesa_receipt_number}</p>}
      {status === 'FAILED' && <p>❌ Payment failed: {data.result_description}</p>}
    </div>
  );
}
```

---

## 🧪 Test with cURL

```bash
# 1. Login to get token
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@test.com","password":"password"}' \
  | jq -r '.token')

# 2. Initiate payment
CHECKOUT_ID=$(curl -s -X POST http://localhost:8000/api/v1/mpesa/stk-push \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"phone_number":"254712345678","amount":10,"account_reference":"TEST"}' \
  | jq -r '.data.checkout_request_id')

echo "Checkout ID: $CHECKOUT_ID"
echo "Enter PIN on phone, then press Enter..."
read

# 3. Verify payment
curl -X POST http://localhost:8000/api/v1/mpesa/verify-payment \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"checkout_request_id\":\"$CHECKOUT_ID\"}" \
  | jq '.'
```

---

## 📱 Postman Collection

### 1. Create Environment Variable
- Variable: `checkout_request_id`
- Value: (auto-set from STK Push response)

### 2. STK Push Request

**Tests Tab** (auto-save checkout_request_id):
```javascript
pm.test("STK Push successful", function() {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.true;
    pm.environment.set("checkout_request_id", jsonData.data.checkout_request_id);
});
```

### 3. Verify Payment Request

Use `{{checkout_request_id}}` in request body:
```json
{
  "checkout_request_id": "{{checkout_request_id}}"
}
```

---

## ⚡ Performance Tips

1. **Initial Delay**: Wait 2-3 seconds before first check
2. **Poll Interval**: 3 seconds (not faster)
3. **Max Attempts**: 20 times (60 seconds total)
4. **Stop Immediately**: Don't continue polling after SUCCESS or FAILED

---

## 🐛 Common Issues

### "Transaction Not Found"
- Check the `checkout_request_id` is correct
- Ensure STK Push was successful
- Check database: `SELECT * FROM mpesa_transactions WHERE checkout_request_id = 'xxx'`

### Always Returns "PENDING"
- Verify callback URL is publicly accessible
- Check firewall isn't blocking M-Pesa callbacks
- Use ngrok for local development: `ngrok http 8000`

### Timeout After 60 Seconds
- User didn't enter PIN or cancelled
- Network issues on user's phone
- Ask user to try again

---

## 📚 Full Documentation

- **Complete Guide**: `PAYMENT_VERIFICATION_ENDPOINT.md`
- **Quick Reference**: `PAYMENT_VERIFICATION_QUICK_REFERENCE.md`
- **Implementation Details**: `PAYMENT_VERIFICATION_IMPLEMENTATION_SUMMARY.md`
- **API Guide**: `MPESA_TEST_API_GUIDE.md` (Section 3)

---

## 🎯 Remember

1. **Save** the `checkout_request_id` from STK Push response
2. **Wait** 2-3 seconds before first check
3. **Poll** every 3 seconds (max 20 times = 60 seconds)
4. **Stop** polling when status is SUCCESS or FAILED
5. **Handle** all three statuses: SUCCESS, PENDING, FAILED

---

## ✅ Checklist

- [ ] User authentication working
- [ ] STK Push endpoint working
- [ ] Callback URL configured correctly
- [ ] Callback URL publicly accessible (use ngrok for local dev)
- [ ] SSL certificate valid (production)
- [ ] Polling logic implemented
- [ ] Success/failure handling in place
- [ ] User feedback (loading/success/error messages)
- [ ] Tested with real M-Pesa account

---

**Need Help?** Check the logs:
```bash
tail -f storage/logs/laravel.log | grep "PAYMENT VERIFICATION"
```

---

**Created**: November 17, 2025  
**Status**: ✅ Ready to Use  
**Difficulty**: ⭐⭐☆☆☆ (Easy)

