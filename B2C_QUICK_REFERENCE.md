# M-Pesa B2C Quick Reference Card

## 🔴 Critical Info
- **MUST have Bulk Disbursement Account** (NOT Paybill/Till)
- Apply here: https://www.safaricom.co.ke/business/sme/m-pesa-payment-solutions

---

## 📋 Required .env Variables

```env
# Test/Sandbox
MPESA_INITIATOR_NAME=testapi
MPESA_INITIATOR_PASSWORD=get_from_safaricom_portal
MPESA_B2C_SHORTCODE=600996
MPESA_B2C_RESULT_URL=https://yourdomain.com/api/v1/mpesa/b2c-result
MPESA_B2C_TIMEOUT_URL=https://yourdomain.com/api/v1/mpesa/b2c-timeout
```

---

## 🎯 Command IDs

| Command | Use Case |
|---------|----------|
| `BusinessPayment` | General B2C payments (default) |
| `SalaryPayment` | Employee salaries |
| `PromotionPayment` | Marketing/promotional payouts |

---

## 📝 Test Request (Postman)

```http
POST {{base_url}}/api/v1/mpesa/test-b2c
Content-Type: application/json

{
  "phone_number": "254712345678",
  "amount": 100,
  "command_id": "BusinessPayment",
  "remarks": "Test payment"
}
```

---

## ✅ Parameters Sent to Safaricom

**From your request:**
- `phone_number` → `PartyB`
- `amount` → `Amount`
- `command_id` → `CommandID`
- `remarks` → `Remarks`

**Auto-added by package:**
- `InitiatorName` (from config)
- `SecurityCredential` (encrypted password from config)
- `PartyA` (B2C shortcode from config)
- `QueueTimeOutURL` (from config)
- `ResultURL` (from config)
- `Occassion` (empty)

---

## 🔐 Password Rules

✅ **Allowed**: `#`, `&`, `%`, `$`  
❌ **NOT allowed**: `(`, `)`, `@` is treated as normal char

**Good**: `Test@Pass#2024`  
**Bad**: `Test(Pass)2024`

---

## 📊 Response Flow

1. **Initial Response** (Immediate)
   ```json
   {
     "ConversationID": "AG_xxx",
     "OriginatorConversationID": "feb5e3f2-xxx",
     "ResponseCode": "0",
     "ResponseDescription": "Accept the service request successfully."
   }
   ```

2. **Result Callback** (After processing - to your ResultURL)
   ```json
   {
     "Result": {
       "ResultCode": 0,
       "TransactionID": "OEI2AK4Q16",
       "ResultParameters": {...}
     }
   }
   ```

---

## 🚨 Common Errors

| Error | Cause | Fix |
|-------|-------|-----|
| Config error | Missing env vars | Check .env file |
| Invalid shortcode | Using Paybill/Till | Get B2C shortcode |
| SecurityCredential failed | Bad password chars | Use allowed chars only |
| Callback unreachable | HTTP or not public | Use HTTPS + public URL |

---

## 🧪 Test Checklist

- [ ] All env variables configured
- [ ] Callback URLs are HTTPS and publicly accessible
- [ ] Test with sandbox credentials first
- [ ] Check logs: `tail -f storage/logs/laravel.log | grep "B2C"`
- [ ] Verify callback received
- [ ] Check transaction in database

---

## 📞 Where to Get Credentials

1. Login to https://developer.safaricom.co.ke
2. Go to your app
3. Click **"Test Credentials"**
4. Find:
   - InitiatorName
   - InitiatorPassword
   - Sandbox shortcode: `600996`

---

## 🔍 Check Configuration

```bash
php artisan tinker
>>> config('mpesa.b2c_shortcode')
>>> config('mpesa.initiator_name')
>>> config('mpesa.b2c_result_url')
```

---

**Full Guide**: See `B2C_SETUP_GUIDE.md`

