<?php

namespace App\Events;

use App\Models\Loan;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $loan;
    public $remarks;

    /**
     * Create a new event instance.
     */
    public function __construct(Loan $loan, ?string $remarks = null)
    {
        $this->loan = $loan;
        $this->remarks = $remarks;
    }
}

