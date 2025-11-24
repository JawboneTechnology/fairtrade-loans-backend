<?php

namespace App\Events;

use App\Models\LoanDeduction;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeductionProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public LoanDeduction $deduction;
    public Loan $loan;
    public User $user;
    public float $newLoanBalance;
    public string $deductionType;

    /**
     * Create a new event instance.
     */
    public function __construct(
        LoanDeduction $deduction,
        Loan $loan,
        User $user,
        float $newLoanBalance,
        string $deductionType
    ) {
        $this->deduction = $deduction;
        $this->loan = $loan;
        $this->user = $user;
        $this->newLoanBalance = $newLoanBalance;
        $this->deductionType = $deductionType;
    }
}

