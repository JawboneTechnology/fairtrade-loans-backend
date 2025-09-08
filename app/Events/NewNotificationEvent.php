<?php

namespace App\Events;

use App\Http\Resources\NotificationResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use App\Models\Notification;

class NewNotificationEvent implements ShouldBroadcast
{
    use SerializesModels;

    public $notification;
    public $userId;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
        $this->userId = $notification->user_id;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("notifications.{$this->userId}");
    }

    public function broadcastWith(): array
    {
        return [
            'success' => true,
            'data' => NotificationResource::collection($this->notification),
            'timestamp' => now()->toDateTimeString()
        ];
    }
}

