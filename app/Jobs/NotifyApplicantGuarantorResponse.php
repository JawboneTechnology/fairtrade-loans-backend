<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\User;
use App\Notifications\GuarantorResponseNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyApplicantGuarantorResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $loan;
    public $response;
    public $guarantorId;

    /**
     * Create a new job instance.
     */
    public function __construct(Loan $loan, string $response, string $guarantorId)
    {
        $this->loan = $loan;
        $this->response = $response;
        $this->guarantorId = $guarantorId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get Applicant
            $applicant = User::findOrFail($this->loan->employee_id);
            $applicantName = $applicant->first_name . ' ' . $applicant->last_name;

            // Get the Guarantor Name
            $guarantor = User::findOrFail($this->guarantorId);
            $guarantorName = $guarantor->first_name . ' ' . $guarantor->last_name;

            // Get the updated_at timestamp from the pivot table
            $pivotData = $this->loan->guarantors()
                ->where('guarantor_id', $this->guarantorId)
                ->first();

            if (!$pivotData) {
                Log::error("Guarantor (ID: {$this->guarantorId}) not found in the pivot table for loan (ID: {$this->loan->id}).");
                return;
            }

            $updatedDate = now()->format('Y-m-d H:i:s');

            // Notify the applicant
            $applicant->notify(new GuarantorResponseNotification(
                $guarantorName,
                $applicantName,
                $this->loan,
                $this->response,
                $updatedDate
            ));

        } catch (\Exception $e) {
            Log::error('Error in NotifyApplicantGuarantorResponse job: ' . $e->getMessage() . ' Line: ' . $e->getLine());
        }
    }
}
