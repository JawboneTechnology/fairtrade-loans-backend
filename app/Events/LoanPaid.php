<?php

namespace App\Events;

use App\Models\Loan;
use App\Models\Transaction;
use App\Models\LoanDeduction;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class LoanPaid
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $loan;
    public $deduction;
    public $transaction;

    /**
     * Create a new event instance.
     */
    public function __construct(Loan $loan, LoanDeduction $deduction, Transaction $transaction)
    {
        $this->loan = $loan;
        $this->deduction = $deduction;
        $this->transaction = $transaction;
    }
}
