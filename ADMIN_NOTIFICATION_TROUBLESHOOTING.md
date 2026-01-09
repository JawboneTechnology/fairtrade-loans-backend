# Admin Notification Troubleshooting Guide

## Current Status

✅ **Admin notifications ARE being created in the database**
- Admin has 5 recent notifications
- Notifications are unread
- Channel authorization works correctly

✅ **Broadcasting is configured**
- Driver: `reverb`
- Channel: `private-notifications.{adminId}`
- Event: `.notification.new`

## Issue

Admin user is not receiving notifications via WebSocket, even though:
- Employee notifications work correctly
- Admin notifications exist in the database
- Broadcasting is configured

## Troubleshooting Steps

### 1. Verify Admin Client Connection

**Check if admin client is connected to WebSocket:**

```javascript
// In admin React app, verify connection
console.log('Echo connection state:', echo.connector.socket.readyState);
// Should be: 1 (OPEN)
```

### 2. Verify Channel Subscription

**Admin should subscribe to:**
```javascript
const channel = echo.private(`notifications.${adminUserId}`);
// Full channel name: private-notifications.{adminUserId}
```

**Verify subscription:**
```javascript
channel.subscribed(() => {
    console.log('✅ Admin subscribed to channel');
});

channel.error((error) => {
    console.error('❌ Subscription error:', error);
});
```

### 3. Verify Event Listener

**Admin should listen for:**
```javascript
channel.listen('.notification.new', (data) => {
    console.log('✅ Notification received:', data);
});
```

### 4. Verify Authentication

**Check if admin token is valid:**
```bash
# Test admin authentication
curl -X GET "http://your-api/api/v1/notifications" \
  -H "Authorization: Bearer {admin_token}"
```

### 5. Check Admin User ID

**Verify admin is using correct user ID:**
- Admin ID: `02b6c9ab-f0a9-4e8f-b798-1fcd970bcfd8`
- Channel: `private-notifications.02b6c9ab-f0a9-4e8f-b798-1fcd970bcfd8`

### 6. Test Notification Creation

**Create a test notification for admin:**
```bash
php artisan tinker --execute="
\$admin = App\Models\User::whereHas('roles', function(\$q) {
    \$q->whereIn('name', ['admin', 'super-admin']);
})->first();
\$service = app(App\Services\NotificationService::class);
\$service->create(\$admin, 'test_notification', [
    'title' => 'Test',
    'message' => 'Test message'
]);
"
```

### 7. Check Reverb Server

**Verify Reverb is running:**
```bash
sudo supervisorctl status reverb:*
# Should show: RUNNING
```

**Check Reverb logs:**
```bash
tail -f storage/logs/reverb.log
```

### 8. Compare Employee vs Admin Setup

Since employee notifications work, compare:
1. **Connection code** - Should be identical
2. **Channel subscription** - Should use same pattern
3. **Event listener** - Should listen for same event
4. **Authentication** - Should use same token format

## Common Issues

### Issue 1: Admin Client Not Subscribed
**Symptom:** Notifications exist in DB but not received
**Solution:** Verify admin client subscribes to `private-notifications.{adminId}`

### Issue 2: Wrong User ID
**Symptom:** Admin subscribed but not receiving
**Solution:** Ensure admin client uses correct admin user ID

### Issue 3: Authentication Failed
**Symptom:** Subscription fails
**Solution:** Verify admin token is valid and has correct permissions

### Issue 4: Reverb Not Running
**Symptom:** No WebSocket connection
**Solution:** Start Reverb server: `sudo supervisorctl start reverb:*`

## Admin Notification Channel

**Channel Name:** `private-notifications.{adminUserId}`
**Event Name:** `.notification.new`
**Example:** `private-notifications.02b6c9ab-f0a9-4e8f-b798-1fcd970bcfd8`

## React Code Example (Admin)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Initialize Echo (same as employee)
const echo = new Echo({
    broadcaster: 'reverb', // or 'pusher'
    key: process.env.REACT_APP_REVERB_APP_KEY,
    wsHost: process.env.REACT_APP_REVERB_HOST,
    wsPort: process.env.REACT_APP_REVERB_PORT,
    wssPort: process.env.REACT_APP_REVERB_PORT,
    forceTLS: (process.env.REACT_APP_REVERB_SCHEME ?? 'https') === 'https',
    authEndpoint: `${process.env.REACT_APP_API_URL}/broadcasting/auth`,
    auth: {
        headers: {
            Authorization: `Bearer ${adminToken}`,
        },
    },
});

// Get admin user ID from authenticated user
const adminUserId = adminUser.id; // Get from auth context

// Subscribe to admin's notification channel
const channel = echo.private(`notifications.${adminUserId}`);

// Listen for new notifications
channel.listen('.notification.new', (data) => {
    console.log('New admin notification:', data);
    // Update notification state
    // data.data contains the notification object
});

// Handle subscription success
channel.subscribed(() => {
    console.log('Admin subscribed to notifications channel');
});

// Handle subscription errors
channel.error((error) => {
    console.error('Subscription error:', error);
});
```

## Verification Checklist

- [ ] Admin notifications exist in database
- [ ] Admin client is connected to WebSocket
- [ ] Admin client is subscribed to correct channel
- [ ] Admin client is listening for `.notification.new` event
- [ ] Admin token is valid
- [ ] Admin user ID is correct
- [ ] Reverb server is running
- [ ] Broadcasting driver is configured (reverb/pusher)
- [ ] Channel authorization works

## Next Steps

1. Verify admin client is using correct user ID
2. Check admin client WebSocket connection status
3. Verify admin client is subscribed to the channel
4. Check browser console for WebSocket errors
5. Compare admin client code with working employee client code

