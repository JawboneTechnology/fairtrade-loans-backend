# WebSocket Notification Setup Guide

## Backend Configuration

### Event Details
- **Event Class:** `App\Events\NewNotificationEvent`
- **Broadcast Name:** `notification.new`
- **Channel:** `private-notifications.{userId}`
- **Broadcast Type:** `ShouldBroadcastNow` (immediate, not queued)

### Channel Authorization
- **Route:** `routes/channels.php`
- **Channel Pattern:** `notifications.{id}`
- **Authorization:** User ID must match channel ID (UUID string comparison)

## React Client Setup

### 1. Event Name to Listen For

The client should listen for: **`notification.new`**

**NOT** `pusher_internal:subscription_succeeded` (this is just the subscription confirmation)

### 2. Example React Code

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Initialize Echo
const echo = new Echo({
    broadcaster: 'pusher', // or 'reverb'
    key: process.env.REACT_APP_PUSHER_APP_KEY,
    cluster: process.env.REACT_APP_PUSHER_APP_CLUSTER,
    authEndpoint: `${process.env.REACT_APP_API_URL}/broadcasting/auth`,
    auth: {
        headers: {
            Authorization: `Bearer ${token}`,
        },
    },
});

// Subscribe to channel
const channel = echo.private(`notifications.${userId}`);

// Listen for the notification event
channel.listen('.notification.new', (data) => {
    console.log('New notification received:', data);
    // data structure:
    // {
    //   success: true,
    //   data: {
    //     id: "uuid",
    //     type: "test_notification",
    //     title: "Test Notification",
    //     message: "Message text",
    //     data: {...},
    //     is_read: false,
    //     created_at: "2024-01-15 10:30:00",
    //     ...
    //   },
    //   timestamp: "2024-01-15 10:30:00"
    // }
});
```

### 3. Important Notes

1. **Event Name:** Use `.notification.new` (with the dot prefix) when listening
2. **Channel:** Must be `private-notifications.{userId}` (Laravel Echo adds `private-` prefix automatically)
3. **Subscription Success:** The `pusher_internal:subscription_succeeded` event is just confirmation - ignore its data
4. **Actual Notification:** Listen for `.notification.new` event

### 4. Troubleshooting

If notifications are not received:

1. **Check Broadcasting Config:**
   ```env
   BROADCAST_CONNECTION=pusher
   # or
   BROADCAST_CONNECTION=reverb
   ```

2. **Verify Channel Authorization:**
   - User ID must match the channel ID
   - Token must be valid in Authorization header

3. **Check Event Broadcasting:**
   - Event uses `ShouldBroadcastNow` for immediate broadcast
   - No queue delay

4. **Verify Event Name:**
   - Backend broadcasts as: `notification.new`
   - Client should listen for: `.notification.new`

### 5. Test Endpoint

Use the test endpoint to create a notification:
```
POST /api/v1/notifications/test
Authorization: Bearer {token}
```

Or use Artisan:
```bash
php artisan tinker --execute="
\$user = App\Models\User::where('id', 'YOUR_USER_ID')->first();
\$service = new App\Services\NotificationService();
\$service->create(\$user, 'test_notification', [
    'title' => 'Test',
    'message' => 'Test message'
]);
"
```

## Expected Data Format

When `notification.new` event is received:

```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "type": "test_notification",
    "title": "Test Notification",
    "message": "Test message",
    "data": {
      "title": "Test Notification",
      "message": "Test message"
    },
    "is_read": false,
    "read_at": null,
    "created_at": "2024-01-15 10:30:00",
    "created_at_formatted": "15 Jan 2024, 10:30 AM",
    "human_date": "2 minutes ago"
  },
  "timestamp": "2024-01-15 10:30:00"
}
```

