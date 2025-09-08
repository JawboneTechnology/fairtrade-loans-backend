<?php

namespace App\Jobs;

use App\Models\Guarantor;
use App\Models\Loan;
use App\Models\User;
use App\Models\LoanType;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\NotifyAdminLoanReadyForApproval;

class UpdateLoanStatusOnGuarantorResponse implements ShouldQueue
{
    use Queueable;

    public $loan;
    public $response;
    public $guarantorId;
    public $notificationService;

    /**
     * Create a new job instance.
     */
    public function __construct(Loan $loan, $response, $guarantorId)
    {
        $this->loan = $loan;
        $this->response = $response;
        $this->guarantorId = $guarantorId;
        $this->notificationService = new NotificationService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $loan = Loan::findOrFail($this->loan->id);
            $guarantors = Guarantor::where('loan_id', $loan->id)->get();
            $guarantorStatuses = $guarantors->pluck('status');

            // Check if any guarantor declined
            if ($guarantorStatuses->contains('declined')) {
                $this->updateLoanStatus('rejected', 'One or more guarantors declined the request.');
                $this->createRejectionNotification($loan);
                Log::info("Loan ID: {$loan->id} status updated to rejected.");
                return;
            }

            // Check if any responses are still pending
            if ($guarantorStatuses->contains('pending')) {
                Log::info("Loan ID: {$loan->id} has pending guarantor responses.");
                return;
            }

            // Check if ALL guarantors have accepted
            if ($guarantorStatuses->every(fn ($status) => $status === 'accepted')) {
                $this->updateLoanStatus('processing', 'All guarantors have accepted the request. Waiting for approval.');
                $this->notifyAdmin();
                $this->createAcceptanceNotification($loan);
                Log::info("Loan ID: {$loan->id} status updated to processing after all guarantors accepted.");
            } else {
                Log::info("Loan ID: {$loan->id} has mixed guarantor responses: " . $guarantorStatuses->implode(', '));
            }

        } catch (\Exception $e) {
            Log::error("Error in UpdateLoanStatusOnGuarantorResponse job: " . $e->getMessage(), [
                'loan_id' => $this->loan->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Update the loan status and remarks.
     */
    protected function updateLoanStatus(string $status, string $remarks): void
    {
        $this->loan->update([
            'loan_status' => $status,
            'remarks' => $remarks,
        ]);
    }

    /**
     * Notify the admin that the loan is ready for processing.
     */
    protected function notifyAdmin(): void
    {
        $adminEmail = config('app.system_admin'); // Better to use config instead of env()
        $admin = User::where('email', $adminEmail)->first();

        if (!$admin) {
            Log::error("Admin with email {$adminEmail} not found.");
            return;
        }

        $applicant = User::findOrFail($this->loan->employee_id);
        $loanType = LoanType::findOrFail($this->loan->loan_type_id);

        $admin->notify(new NotifyAdminLoanReadyForApproval(
            $applicant->full_name, // Assuming you have an accessor for full name
            $this->loan,
            $loanType->name
        ));
    }

    protected function createAcceptanceNotification(Loan $loan): void
    {
        $applicant = $loan->employee;

        $this->notificationService->create($applicant, 'guarantor_acceptance', [
            'loan_id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'amount' => $loan->loan_amount,
            'applicant_name' => $applicant->full_name,
            'action_url' => config('app.url').'/api/v1/notifications/'.$loan->id.'/read'
        ]);

        Log::info("Created acceptance notification for loan ID: {$loan->id}");
    }

    protected function createRejectionNotification(Loan $loan): void
    {
        $applicant = $loan->employee;

        $this->notificationService->create($applicant, 'guarantor_rejection', [
            'loan_id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'amount' => $loan->loan_amount,
            'applicant_name' => $applicant->full_name,
            'action_url' => config('app.url').'/api/v1/notifications/'.$loan->id.'/read'
        ]);

        Log::info("Created rejection notification for loan ID: {$loan->id}");
    }
}
