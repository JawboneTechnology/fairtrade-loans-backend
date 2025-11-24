<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAccountDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $userId;
    public string $userEmail;
    public string $userName;
    public string $employeeId;
    public ?string $deletedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $userId,
        string $userEmail,
        string $userName,
        string $employeeId,
        ?string $deletedBy = null
    ) {
        $this->userId = $userId;
        $this->userEmail = $userEmail;
        $this->userName = $userName;
        $this->employeeId = $employeeId;
        $this->deletedBy = $deletedBy;
    }
}

