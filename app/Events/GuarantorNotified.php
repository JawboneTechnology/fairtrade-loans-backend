<?php

namespace App\Events;

use App\Models\Loan;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuarantorNotified
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Loan $loan;
    public string $guarantorId;
    public string $notificationId;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Loan  $loan
     * @param  string  $guarantorId
     */
    public function __construct(Loan $loan, string $guarantorId, string $notificationId)
    {
        $this->loan = $loan;
        $this->guarantorId = $guarantorId;
        $this->notificationId = $notificationId;
    }
}
