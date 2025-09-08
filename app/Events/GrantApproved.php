<?php

namespace App\Events;

use App\Models\Grant;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class GrantApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Grant $grant;

    /**
     * Create a new event instance.
     */
    public function __construct(Grant $grant)
    {
        $this->grant = $grant;
    }
}
