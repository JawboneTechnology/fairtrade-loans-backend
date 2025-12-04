<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Events\NewNotificationEvent;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function create(User $user, string $type, array $data): Notification
    {
        $template = NotificationTemplate::firstWhere('type', $type);

        // Prepare notification data
        $notificationData = $data;
        
        // Add template data if template exists
        if ($template) {
            $notificationData = array_merge($data, [
                'title'     => $template->title,
                'message'   => $this->parseTemplate($template->message, $data)
            ]);
        } else {
            // Fallback if no template found
            $notificationData = array_merge($data, [
                'title'     => $data['title'] ?? ucfirst(str_replace('_', ' ', $type)),
                'message'   => $data['message'] ?? 'You have a new notification.'
            ]);
        }

        $notification = Notification::create([
            'id'            => Str::uuid(),
            'user_id'       => $user->id,
            'type'          => $type,
            'data'          => $notificationData
        ]);

        // Broadcast new notification
        try {
            broadcast(new NewNotificationEvent($notification));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast new notification: ' . $e->getMessage());
        }

        return $notification;
    }

    private function parseTemplate(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($data) {
            return $data[$matches[1]] ?? $matches[0];
        }, $template);
    }

    public function markAsRead(Notification $notification): void
    {
            Notification::where('id', $notification->id)->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }

    /**
     * Mark all notifications as read for a user
     *
     * @param User $user
     * @return int Number of notifications marked as read
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }

    /**
     * Get unread notification count for a user
     *
     * @param User $user
     * @return int
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Delete a notification
     *
     * @param Notification $notification
     * @return bool
     */
    public function delete(Notification $notification): bool
    {
        return $notification->delete();
    }
}
