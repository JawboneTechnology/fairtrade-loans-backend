<?php

namespace App\Events;

use App\Models\Loan;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanApplicantNotified
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $loan;
    public $response;
    public $guarantorId;

    /**
     * Create a new event instance.
     */
    public function __construct(Loan $loan, string $response, string $guarantorId)
    {
        $this->loan = $loan;
        $this->response = $response;
        $this->guarantorId = $guarantorId;
    }
}
