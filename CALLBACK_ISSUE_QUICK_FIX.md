# STK Push Callbacks Not Working - Quick Fix Guide

## 🔍 Problem

You're successfully initiating STK Push, but **not receiving callbacks from Safaricom**.

### What's Happening:
- ✅ STK Push initiated (ResponseCode: 0)
- ✅ User receives M-Pesa prompt on phone
- ❌ **NO callbacks received** after user enters PIN
- ❌ Transactions stay in `PENDING` status forever

---

## 🎯 Root Cause

**Your `APP_URL` is set to `localhost`**, which Safaricom cannot reach from their servers.

---

## ⚡ Quick Fix (3 Steps)

### Step 1: Install ngrok
```bash
# Mac
brew install ngrok/ngrok/ngrok

# Or download from https://ngrok.com/download
```

### Step 2: Start ngrok
```bash
ngrok http 8000
```

Copy the **HTTPS URL** (looks like: `https://abc123.ngrok.io`)

### Step 3: Update .env and restart
```bash
# Edit .env file
# Change this:
APP_URL=http://localhost:8000

# To this (use YOUR ngrok URL):
APP_URL=https://abc123.ngrok.io

# Clear cache
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

**Done!** Now try STK Push again - you should receive callbacks.

---

## 🧪 Test Your Configuration

### NEW: Diagnostic Endpoint

Run this to check if your callback URL is configured correctly:

```bash
GET http://localhost:8000/api/v1/mpesa/check-callback-config
```

Or with curl:
```bash
curl http://localhost:8000/api/v1/mpesa/check-callback-config | jq '.'
```

### Expected Response (Before Fix):
```json
{
  "success": true,
  "status": "NOT READY",
  "can_receive_callbacks": false,
  "configuration": {
    "app_url": "http://localhost:8000",
    "callback_url": "http://localhost:8000/api/v1/mpesa/stk/callback",
    "is_localhost": true,
    "is_https": false,
    "is_public": false
  },
  "issues": [
    "APP_URL is set to localhost - Safaricom CANNOT reach this URL",
    "You need to use ngrok or deploy to a public server"
  ],
  "recommendations": [
    "For local development: Install ngrok (brew install ngrok)",
    "Run: ngrok http 8000",
    "Update APP_URL in .env to the ngrok HTTPS URL",
    "Run: php artisan config:clear"
  ]
}
```

### Expected Response (After Fix):
```json
{
  "success": true,
  "status": "READY",
  "can_receive_callbacks": true,
  "configuration": {
    "app_url": "https://abc123.ngrok.io",
    "callback_url": "https://abc123.ngrok.io/api/v1/mpesa/stk/callback",
    "is_localhost": false,
    "is_https": true,
    "is_public": true
  },
  "issues": [],
  "recommendations": [
    "Your callback URL is publicly accessible ✓",
    "HTTPS is configured ✓"
  ],
  "next_steps": [
    "1. Your callback configuration looks good!",
    "2. Initiate an STK Push",
    "3. Monitor logs: tail -f storage/logs/laravel.log | grep 'STK PUSH CALLBACK'",
    "4. You should see callback logs when user enters PIN"
  ]
}
```

---

## 📊 Verify Callbacks Are Working

### Method 1: Check Logs
```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log | grep "STK PUSH CALLBACK"
```

After user enters PIN, you should see:
```
[2025-11-18 05:10:30] local.INFO: === STK PUSH CALLBACK RECEIVED ===
[2025-11-18 05:10:30] local.INFO:
{
    "Body": {
        "stkCallback": {
            "CheckoutRequestID": "ws_CO_18112025080757546725134449",
            "ResultCode": 0,
            "ResultDesc": "The service request is processed successfully."
        }
    }
}
```

### Method 2: Check Transaction Status
```bash
# Use the verify payment endpoint
POST http://localhost:8000/api/v1/mpesa/verify-payment
Authorization: Bearer YOUR_TOKEN

{
  "checkout_request_id": "ws_CO_18112025080757546725134449"
}
```

**Before callback received:**
```json
{
  "status": "PENDING",
  "payment_complete": false
}
```

**After callback received:**
```json
{
  "status": "SUCCESS",
  "payment_complete": true,
  "data": {
    "amount_paid": 100.0,
    "mpesa_receipt_number": "OEI2AK4Q16"
  }
}
```

---

## 📝 Testing Checklist

- [ ] ngrok is installed
- [ ] ngrok is running (`ngrok http 8000`)
- [ ] APP_URL in .env is set to ngrok HTTPS URL
- [ ] Config cache cleared (`php artisan config:clear`)
- [ ] Diagnostic endpoint shows "READY"
- [ ] Test STK Push initiated
- [ ] User enters PIN on phone
- [ ] Callback logs appear in laravel.log
- [ ] Transaction status changes from PENDING to SUCCESS

---

## 💡 Important Notes

### Keep ngrok Running
Don't close the ngrok terminal window while testing. If it stops:
1. Restart ngrok
2. Update APP_URL with new ngrok URL
3. Clear config cache again

### ngrok Free vs Paid
- **Free**: New URL every time you restart
- **Paid**: Static URL that doesn't change

### Production Deployment
For production, deploy to a server with a real domain and SSL certificate:
```env
APP_URL=https://your-domain.com
```

---

## 🚨 Common Mistakes

### ❌ Mistake 1: Forgot to clear config cache
After changing APP_URL:
```bash
php artisan config:clear
```

### ❌ Mistake 2: Using HTTP instead of HTTPS
Always use the **HTTPS** URL from ngrok, not HTTP.

### ❌ Mistake 3: ngrok stopped running
Check that ngrok is still active:
```bash
# Open http://localhost:4040 in browser
# You should see ngrok dashboard
```

### ❌ Mistake 4: Wrong APP_URL format
```env
# Wrong (has trailing slash)
APP_URL=https://abc123.ngrok.io/

# Correct (no trailing slash)
APP_URL=https://abc123.ngrok.io
```

---

## 🔗 Related Documentation

- **Complete Troubleshooting**: `STK_CALLBACK_TROUBLESHOOTING.md`
- **M-Pesa Test Guide**: `MPESA_TEST_API_GUIDE.md`
- **Payment Verification**: `PAYMENT_VERIFICATION_ENDPOINT.md`

---

## 📞 Still Not Working?

1. Run the diagnostic endpoint: `GET /api/v1/mpesa/check-callback-config`
2. Check the full guide: `STK_CALLBACK_TROUBLESHOOTING.md`
3. Verify ngrok is running and accessible
4. Check firewall settings
5. Try with a different M-Pesa sandbox phone number

---

**Created**: November 18, 2025  
**Status**: Ready to Use  
**Difficulty**: ⭐☆☆☆☆ (Very Easy with ngrok)

