# M-Pesa Production Setup Guide

## Overview
This guide explains how to transition your M-Pesa integration from sandbox to production and ensures proper token management across all environments.

## Environment Configuration

### 1. Sandbox Environment (Current)
Your current `.env` configuration:
```env
MPESA_ENVIRONMENT=sandbox
MPESA_CONSUMER_KEY=your_sandbox_consumer_key
MPESA_CONSUMER_SECRET=your_sandbox_consumer_secret
MPESA_BUSINESS_SHORTCODE=174379
MPESA_INITIATOR_NAME=testapi
MPESA_INITIATOR_PASSWORD=Safaricom999!*!
MPESA_B2C_SHORTCODE=600981
```

### 2. Production Environment
When going live, update your `.env` with production credentials:
```env
MPESA_ENVIRONMENT=production  # or 'live'
MPESA_CONSUMER_KEY=your_production_consumer_key
MPESA_CONSUMER_SECRET=your_production_consumer_secret
MPESA_BUSINESS_SHORTCODE=your_production_shortcode
MPESA_INITIATOR_NAME=your_production_initiator_name
MPESA_INITIATOR_PASSWORD=your_production_initiator_password
MPESA_B2C_SHORTCODE=your_production_b2c_shortcode
```

## Token Management Updates

### What Was Fixed
1. **Environment-Aware Token Generation**: The `MpesaService` now automatically detects the environment and uses:
   - `generateSandBoxToken()` for sandbox
   - `generateLiveToken()` for production

2. **Token Refresh Integration**: All M-Pesa API methods now include token refresh:
   - `initiateStkPush()`
   - `queryTransactionStatus()`
   - `initiateB2C()`
   - `registerC2BUrls()`

3. **Cached Token Clearing**: All methods now clear cached tokens before making API calls to ensure fresh authentication.

### Methods Updated
```php
// Private method for environment-aware token refresh
private function refreshAccessToken(): array
{
    $environment = config('mpesa.environment', 'sandbox');
    
    if ($environment === 'live' || $environment === 'production') {
        $tokenResponse = Mpesa::generateLiveToken();
    } else {
        $tokenResponse = Mpesa::generateSandBoxToken();
    }
    
    return ['success' => true, 'environment' => $environment];
}
```

## Production URLs and Endpoints

### Safaricom API Endpoints
```
Sandbox:
- Base URL: https://sandbox.safaricom.co.ke
- OAuth URL: https://sandbox.safaricom.co.ke/oauth/v1/generate

Production:
- Base URL: https://api.safaricom.co.ke  
- OAuth URL: https://api.safaricom.co.ke/oauth/v1/generate
```

### Your Callback URLs (for production)
```
C2B Validation URL: https://loansapi.jawbonetechnology.co.ke/api/v1/c2b/validation
C2B Confirmation URL: https://loansapi.jawbonetechnology.co.ke/api/v1/c2b/confirmation
STK Push Callback URL: https://loansapi.jawbonetechnology.co.ke/api/v1/mpesa/stk-callback
B2C Result URL: https://loansapi.jawbonetechnology.co.ke/api/v1/mpesa/b2c-result
B2C Timeout URL: https://loansapi.jawbonetechnology.co.ke/api/v1/mpesa/b2c-timeout
```

## Pre-Production Checklist

### 1. Obtain Production Credentials
- [ ] Apply for M-Pesa Go-Live approval from Safaricom
- [ ] Receive production Consumer Key and Consumer Secret
- [ ] Get assigned production shortcode(s)
- [ ] Obtain production initiator credentials

### 2. SSL Certificate
- [ ] Ensure your domain has a valid SSL certificate
- [ ] All callback URLs must use HTTPS
- [ ] Test SSL certificate validity

### 3. Server Configuration
- [ ] Ensure production server can access Safaricom APIs
- [ ] Whitelist your server IP with Safaricom (if required)
- [ ] Configure firewall rules for outbound HTTPS traffic

### 4. Testing Preparation
- [ ] Test all endpoints with sandbox credentials
- [ ] Verify callback URL accessibility from external sources
- [ ] Run token generation test: `GET /api/v1/mpesa/test-token`
- [ ] Test callback registration: `POST /api/v1/mpesa/register-callbacks`

## Going Live Process

### Step 1: Update Environment Variables
```bash
# In your production .env file
MPESA_ENVIRONMENT=production
MPESA_CONSUMER_KEY=your_production_consumer_key
MPESA_CONSUMER_SECRET=your_production_consumer_secret
MPESA_BUSINESS_SHORTCODE=your_production_shortcode
# ... other production values
```

### Step 2: Clear Configuration Cache
```bash
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
```

### Step 3: Test Token Generation
```bash
curl -X GET "https://loansapi.jawbonetechnology.co.ke/api/v1/mpesa/test-token"
```

Expected response:
```json
{
    "success": true,
    "message": "Access token test completed",
    "environment": "production",
    "token_info": {
        "token_generated": true,
        "environment": "production",
        "token_length": 28,
        "expires_in": "3599",
        "http_code": 200
    }
}
```

### Step 4: Register Production Callback URLs
```bash
curl -X POST "https://loansapi.jawbonetechnology.co.ke/api/v1/mpesa/register-callbacks" \
  -H "Content-Type: application/json" \
  -d '{
    "c2b_validation_url": "https://loansapi.jawbonetechnology.co.ke/api/v1/c2b/validation",
    "c2b_confirmation_url": "https://loansapi.jawbonetechnology.co.ke/api/v1/c2b/confirmation"
  }'
```

Expected response:
```json
{
    "success": true,
    "message": "Callback URLs updated successfully",
    "safaricom_registration": {
        "success": true,
        "data": {
            "ResponseCode": "0",
            "ResponseDesc": "Success"
        },
        "environment": "production"
    }
}
```

## Monitoring and Debugging

### 1. Log Monitoring
All M-Pesa operations now log the environment:
```bash
tail -f storage/logs/laravel.log | grep -i mpesa
```

### 2. Token Issues
If you encounter token issues:
```bash
# Check current environment
curl -X GET "https://loansapi.jawbonetechnology.co.ke/api/v1/mpesa/test-token"

# Clear application cache
php artisan cache:clear
php artisan config:clear
```

### 3. Common Production Issues
- **Invalid SSL Certificate**: Ensure your domain has a valid SSL certificate
- **IP Whitelisting**: Some M-Pesa features require IP whitelisting with Safaricom
- **Shortcode Mismatch**: Ensure the shortcode in your config matches what Safaricom assigned
- **Callback URL Issues**: Ensure all callback URLs are accessible via HTTPS

## Rollback Plan

If production deployment fails, you can quickly rollback:
```bash
# Revert to sandbox
MPESA_ENVIRONMENT=sandbox

# Clear caches
php artisan config:clear
php artisan cache:clear

# Test sandbox functionality
curl -X GET "https://loansapi.jawbonetechnology.co.ke/api/v1/mpesa/test-token"
```

## Best Practices

### 1. Gradual Migration
- Test individual features one at a time
- Start with token generation and callback registration
- Then test STK Push with small amounts
- Finally enable full loan payment processing

### 2. Monitoring
- Set up alerts for failed M-Pesa transactions
- Monitor token generation success rates
- Track callback response times

### 3. Error Handling
- All methods now include comprehensive error logging
- Failed operations include environment context
- Detailed error messages help with debugging

## Support Resources

### Safaricom Developer Support
- Portal: https://developer.safaricom.co.ke
- Email: apisupport@safaricom.co.ke
- Documentation: https://developer.safaricom.co.ke/docs

### Your Implementation
- All M-Pesa methods now support both environments
- Token refresh is automatic and environment-aware
- Comprehensive logging includes environment context
- Error responses indicate which environment was used

## Environment Verification

After deployment, verify your environment:
```bash
# Check configuration
php artisan config:show mpesa.environment

# Test token generation
curl -X GET "https://loansapi.jawbonetechnology.co.ke/api/v1/mpesa/test-token"

# Verify logs show correct environment
tail -f storage/logs/laravel.log | grep "environment"
```