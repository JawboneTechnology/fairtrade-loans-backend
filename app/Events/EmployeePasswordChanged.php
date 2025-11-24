<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeePasswordChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $employee;
    public string $newPassword;
    public ?User $changedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(
        User $employee,
        string $newPassword,
        ?User $changedBy = null
    ) {
        $this->employee = $employee;
        $this->newPassword = $newPassword;
        $this->changedBy = $changedBy;
    }
}

