<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Events\NewNotificationEvent;

class NotificationService
{
    public function create(User $user, string $type, array $data): Notification
    {
        $template = NotificationTemplate::firstWhere('type', $type);

        $notification = Notification::create([
            'id'            => Str::uuid(),
            'user_id'       => $user->id,
            'type'          => $type,
            'data'          => array_merge($data, [
                'title'     => $template->title,
                'message'   => $this->parseTemplate($template->message, $data)
            ])
        ]);

        broadcast(new NewNotificationEvent($notification));

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
}
