<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StkPushRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $phoneNumber;
    public $amount;
    public $accountReference;
    public $transactionDescription;

    /**
     * Create a new event instance.
     */
    public function __construct($phoneNumber, $amount, $accountReference, $transactionDescription)
    {
        $this->phoneNumber = $phoneNumber;
        $this->amount = $amount;
        $this->accountReference = $accountReference;
        $this->transactionDescription = $transactionDescription;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
