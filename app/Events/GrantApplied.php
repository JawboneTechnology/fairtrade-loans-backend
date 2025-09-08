<?php

namespace App\Events;

use App\Models\Grant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GrantApplied
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
