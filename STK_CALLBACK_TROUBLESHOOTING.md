# STK Push Callback Not Received - Troubleshooting Guide

## 🔍 Problem Identified

Your STK Push initiations are **successful**, but Safaricom is **NOT sending callbacks** to your server.

### Evidence from Logs:
- ✅ STK Push initiated successfully (ResponseCode: 0)
- ✅ Transactions created in database
- ❌ **NO callback logs** (no `=== STK PUSH CALLBACK RECEIVED ===`)
- ❌ Transactions remain in `PENDING` status

---

## 🎯 Root Cause

Safaricom cannot reach your callback URL. The most common reasons are:

### 1. **Running on Localhost** (Most Common)
If your `APP_URL` is set to `http://localhost:8000` or `http://127.0.0.1:8000`, Safaricom **CANNOT** reach it because:
- Localhost is only accessible from your computer
- Safaricom's servers are external and need a public URL

### 2. **Firewall Blocking**
Your server's firewall might be blocking incoming POST requests from Safaricom.

### 3. **SSL Certificate Issues** (Production)
Production environment requires a valid SSL certificate (HTTPS).

### 4. **Incorrect Callback URL**
The callback URL might be malformed or pointing to the wrong endpoint.

---

## 🔧 How to Fix

### ✅ Solution 1: Use ngrok (For Local Development)

**ngrok** creates a public URL that forwards to your localhost.

#### Step 1: Install ngrok
```bash
# Mac (using Homebrew)
brew install ngrok/ngrok/ngrok

# Or download from https://ngrok.com/download
```

#### Step 2: Start ngrok
```bash
# Forward to your Laravel app (default port 8000)
ngrok http 8000
```

You'll see output like:
```
Forwarding  https://abc123.ngrok.io -> http://localhost:8000
```

#### Step 3: Update `.env`
```env
APP_URL=https://abc123.ngrok.io
```

#### Step 4: Clear config cache
```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

#### Step 5: Test the callback URL
```bash
# Test that your callback is accessible
curl https://abc123.ngrok.io/api/v1/mpesa/stk/callback

# Should return: {"ResultCode":0,"ResultDesc":"Success"}
```

#### Step 6: Try STK Push again
Now when you initiate an STK Push, Safaricom will be able to reach your callback URL.

---

### ✅ Solution 2: Deploy to Production/Staging Server

If you have a production server with a public domain:

#### Step 1: Update `.env` on server
```env
APP_URL=https://your-domain.com
```

#### Step 2: Ensure HTTPS is configured
```bash
# Verify SSL is working
curl https://your-domain.com/api/v1/mpesa/stk/callback
```

#### Step 3: Check firewall rules
```bash
# Allow incoming HTTP/HTTPS traffic
sudo ufw allow 80
sudo ufw allow 443
sudo ufw status
```

---

### ✅ Solution 3: Alternative Tunneling Tools

If ngrok doesn't work, try these alternatives:

#### Expose (https://expose.dev/)
```bash
npm install -g @beyondcode/expose
expose share http://localhost:8000
```

#### LocalTunnel (https://localtunnel.github.io/)
```bash
npm install -g localtunnel
lt --port 8000
```

#### Serveo (https://serveo.net/)
```bash
ssh -R 80:localhost:8000 serveo.net
```

---

## 🧪 Diagnostic Checklist

Run through this checklist to identify the issue:

### 1. Check Current APP_URL
```bash
# In your project directory
php artisan config:show app.url

# Or check .env file
grep APP_URL .env
```

**Expected Output**:
- ❌ BAD: `http://localhost:8000` or `http://127.0.0.1:8000`
- ✅ GOOD: `https://your-domain.com` or `https://abc123.ngrok.io`

---

### 2. Check Generated Callback URL
```bash
# Check what URL is being generated
php artisan route:list | grep stk-callback
```

**Expected Output**:
```
POST  api/v1/mpesa/stk/callback  mpesa.stk-callback
```

Test the generated URL:
```bash
php artisan tinker
>>> route('mpesa.stk-callback')
```

**Expected Output**:
- ❌ BAD: `http://localhost:8000/api/v1/mpesa/stk/callback`
- ✅ GOOD: `https://your-domain.com/api/v1/mpesa/stk/callback`

---

### 3. Test Callback Endpoint is Accessible
```bash
# Test from your machine
curl -X POST http://localhost:8000/api/v1/mpesa/stk/callback

# Test from public URL (replace with your ngrok/domain)
curl -X POST https://abc123.ngrok.io/api/v1/mpesa/stk/callback
```

**Expected Response**:
```json
{"ResultCode":0,"ResultDesc":"Success"}
```

---

### 4. Check Logs for Callback Attempts
```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log

# Search for callback entries
grep "STK PUSH CALLBACK" storage/logs/laravel.log
```

**What You Should See**:
- If callbacks are working: `=== STK PUSH CALLBACK RECEIVED ===`
- If not working: No callback entries after STK Push

---

### 5. Test with Sample Callback
Create a test script to simulate what Safaricom sends:

```bash
# Create test file: test_callback.sh
cat > test_callback.sh << 'EOF'
#!/bin/bash

# Replace with your actual URL
CALLBACK_URL="http://localhost:8000/api/v1/mpesa/stk/callback"

# Sample M-Pesa callback payload
curl -X POST $CALLBACK_URL \
  -H "Content-Type: application/json" \
  -d '{
    "Body": {
      "stkCallback": {
        "MerchantRequestID": "TEST-MERCHANT-123",
        "CheckoutRequestID": "ws_CO_TEST123",
        "ResultCode": 0,
        "ResultDesc": "The service request is processed successfully.",
        "CallbackMetadata": {
          "Item": [
            {"Name": "Amount", "Value": 100},
            {"Name": "MpesaReceiptNumber", "Value": "TEST123"},
            {"Name": "Balance"},
            {"Name": "TransactionDate", "Value": 20231115103000},
            {"Name": "PhoneNumber", "Value": 254712345678}
          ]
        }
      }
    }
  }'
EOF

chmod +x test_callback.sh
./test_callback.sh
```

**Expected Result**:
- Check logs: You should see `=== STK PUSH CALLBACK RECEIVED ===`
- If this works, your callback endpoint is fine, issue is with Safaricom reaching it

---

## 📋 Step-by-Step Fix (Quick Version)

### For Local Development:

```bash
# 1. Install ngrok
brew install ngrok/ngrok/ngrok

# 2. Start ngrok
ngrok http 8000
# Copy the HTTPS URL (e.g., https://abc123.ngrok.io)

# 3. Update .env
# Change: APP_URL=http://localhost:8000
# To:     APP_URL=https://abc123.ngrok.io

# 4. Clear cache
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# 5. Verify callback URL
php artisan tinker
>>> route('mpesa.stk-callback')
# Should show: https://abc123.ngrok.io/api/v1/mpesa/stk/callback

# 6. Test callback endpoint
curl -X POST https://abc123.ngrok.io/api/v1/mpesa/stk/callback

# 7. Try STK Push again
# Should now receive callbacks!
```

---

## 🎯 Expected Behavior After Fix

### Before Fix:
```
[2025-11-18 05:07:57] local.INFO: === STK PUSH INITIATED SUCCESSFULLY ===
[2025-11-18 05:07:57] local.INFO: {
    "checkout_request_id": "ws_CO_18112025080757546725134449"
}
# ... No callback logs ...
# Transaction stays PENDING forever
```

### After Fix:
```
[2025-11-18 05:07:57] local.INFO: === STK PUSH INITIATED SUCCESSFULLY ===
[2025-11-18 05:07:57] local.INFO: {
    "checkout_request_id": "ws_CO_18112025080757546725134449"
}

# User enters PIN on phone...

[2025-11-18 05:08:10] local.INFO: === STK PUSH CALLBACK RECEIVED ===
[2025-11-18 05:08:10] local.INFO: {
    "Body": {
        "stkCallback": {
            "MerchantRequestID": "...",
            "CheckoutRequestID": "ws_CO_18112025080757546725134449",
            "ResultCode": 0,
            "ResultDesc": "The service request is processed successfully."
        }
    }
}

[2025-11-18 05:08:10] local.INFO: === STK PUSH PAYMENT SUCCESSFUL ===
[2025-11-18 05:08:10] local.INFO: {
    "transaction_id": "...",
    "mpesa_receipt": "OEI2AK4Q16",
    "amount": 100
}
```

---

## 🔍 Additional Checks

### Check Route Exists
```bash
php artisan route:list | grep "mpesa.stk-callback"
```

**Expected Output**:
```
POST  api/v1/mpesa/stk/callback  mpesa.stk-callback  App\Http\Controllers\Api\V1\MpesaController@stkCallback
```

### Check Middleware
```bash
# The STK callback route should NOT require authentication
# Verify in routes/api.php
grep -A5 "stk/callback" routes/api.php
```

**Expected**:
```php
Route::post('stk/callback', [MpesaController::class, 'stkCallback'])->name('mpesa.stk-callback');
```

---

## 💡 Pro Tips

### 1. Keep ngrok Running
While testing, keep the ngrok terminal window open. If it closes, you'll need to restart and update APP_URL with the new URL.

### 2. Use ngrok Static URLs (Paid)
Free ngrok generates new URLs each time. Paid version gives you static URLs.

### 3. Monitor ngrok Dashboard
ngrok provides a web interface at `http://localhost:4040` showing all requests.

### 4. Test Callback Separately
Always test your callback endpoint is publicly accessible before initiating STK Push.

### 5. Check Safaricom Portal (Production)
In production, verify your callback URL is correctly registered in the Safaricom portal.

---

## 🚨 Common Mistakes

### ❌ Mistake 1: APP_URL has trailing slash
```env
# Wrong
APP_URL=https://abc123.ngrok.io/

# Correct
APP_URL=https://abc123.ngrok.io
```

### ❌ Mistake 2: Forgot to clear cache
After changing APP_URL, always run:
```bash
php artisan config:clear
```

### ❌ Mistake 3: Using HTTP instead of HTTPS
Safaricom **requires HTTPS** for callbacks. ngrok automatically provides HTTPS.

### ❌ Mistake 4: Callback route requires authentication
The M-Pesa callback should NOT require authentication:
```php
// Wrong
Route::middleware('auth:sanctum')->group(function () {
    Route::post('stk/callback', ...); // ❌ M-Pesa can't authenticate
});

// Correct
Route::post('stk/callback', ...); // ✅ No auth required
```

---

## 📞 Still Not Working?

If callbacks still aren't coming through:

### 1. Check Safaricom Sandbox Status
Sometimes the sandbox has issues. Visit:
- https://developer.safaricom.co.ke/status

### 2. Try Different Phone Number
Some sandbox phone numbers don't send callbacks.

### 3. Check Web Server Logs
```bash
# For Apache
tail -f /var/log/apache2/access.log

# For Nginx
tail -f /var/log/nginx/access.log
```

Look for POST requests to `/api/v1/mpesa/stk/callback`

### 4. Use Query Status API
As a fallback, use the query status endpoint:
```bash
POST /api/v1/mpesa/query-status
{
  "checkout_request_id": "ws_CO_18112025080757546725134449"
}
```

---

## 📝 Summary

**The Issue**: Safaricom cannot reach your callback URL because it's on localhost.

**The Fix**: Use ngrok to create a public URL that forwards to your localhost.

**Quick Command**:
```bash
ngrok http 8000
# Copy the HTTPS URL, update APP_URL in .env, clear cache, test again
```

---

**Created**: November 18, 2025  
**Status**: Ready to Use  
**Next Step**: Follow "Solution 1: Use ngrok" above

