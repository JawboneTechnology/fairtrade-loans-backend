<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MiniStatementSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $statement;

    /**
     * Create a new event instance.
     */
    public function __construct(array $statement, User $user)
    {
        $this->statement = $statement;
        $this->user = $user;
    }
}
