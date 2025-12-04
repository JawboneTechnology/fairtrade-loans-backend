<?php

namespace App\Events;

use App\Models\Grant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GrantRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $grant;
    public $adminNotes;

    /**
     * Create a new event instance.
     */
    public function __construct(Grant $grant, ?string $adminNotes = null)
    {
        $this->grant = $grant;
        $this->adminNotes = $adminNotes;
    }
}

