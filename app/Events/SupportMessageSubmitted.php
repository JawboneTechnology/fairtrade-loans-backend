<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $supportData;

    /**
     * Create a new event instance.
     */
    public function __construct(array $supportData)
    {
        $this->supportData = $supportData;
    }
}