<?php

namespace App\Events;

use App\Models\Loan;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $loan;
    public $approvedAmount;
    public $remarks;

    /**
     * Create a new event instance.
     */
    public function __construct(Loan $loan, float $approvedAmount, ?string $remarks = null)
    {
        $this->loan = $loan;
        $this->approvedAmount = $approvedAmount;
        $this->remarks = $remarks;
    }
}

