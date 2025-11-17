<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\LoanType;
use App\Models\User;
use App\Notifications\GuarantorLoanRequestNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyGuarantorOfloanRequest implements ShouldQueue
{
    use Queueable;

    public $guarantor;
    public $loan;
    public $notificationId;

    /**
     * Create a new job instance.
     */
    public function __construct(User $guarantor, Loan $loan, string $notificationId)
    {
        $this->loan = $loan;
        $this->guarantor = $guarantor;
        $this->notificationId = $notificationId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get loan type name
        $loanType = LoanType::findOrFail($this->loan->loan_type_id);

        // Get guarantor liability amount from the pivot table
        $liabilityAmount = $this->loan->guarantors()
            ->where('guarantor_id', $this->guarantor->id)
            ->where('loan_number', $this->loan->loan_number)
            ->first()
            ->pivot
            ->guarantor_liability_amount ?? null;

        // If liability amount is not found in the pivot table, calculate it dynamically
        if (!$liabilityAmount) {
            $totalPayable = $this->loan->loan_amount + ($this->loan->loan_amount * $this->loan->interest_rate / 100);
            $guarantorsCount = $this->loan->guarantors()->count();
            $liabilityAmount = $guarantorsCount > 0 ? $totalPayable / $guarantorsCount : 0;
        }

        // Prepare data for the notification
        $guarantorName = $this->guarantor->first_name . ' ' . $this->guarantor->last_name;
        $guarantorEmail = $this->guarantor->email;
        $guarantorId = $this->guarantor->id;
        $loanId = $this->loan->id;
        $loanName = $loanType->name;
        $notificationId = $this->notificationId;

        Log::info("=== SENDING LOAN REQUEST NOTIFICATION TO GUARANTOR ===");
        Log::info("Loan: {$this->loan}");
        Log::info('=== NOTIFICATION CREATED ===');
        Log::info("ID: {$notificationId}");

        // Send request to guarantor
        $this->guarantor->notify(new GuarantorLoanRequestNotification(
            $guarantorName,
            $guarantorEmail,
            $guarantorId,
            $loanId,
            $loanName,
            $this->loan,
            $liabilityAmount, // Pass liability amount
            $notificationId
        ));
    }
}
