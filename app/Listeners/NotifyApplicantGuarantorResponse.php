<?php

namespace App\Listeners;

use App\Events\LoanApplicantNotified;
use App\Models\Loan;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyApplicantGuarantorResponse
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(LoanApplicantNotified $event): void
    {
        try {
            $loan = Loan::with(['employee', 'guarantors'])->findOrFail($event->loan->id);
            $applicant = $loan->employee;
            $guarantor = User::findOrFail($event->guarantorId);
            
            if (!$applicant) {
                Log::error("Applicant not found for loan ID: {$loan->id}");
                return;
            }

            $guarantorName = $guarantor->first_name . ' ' . $guarantor->last_name;
            $formattedAmount = number_format($loan->loan_amount, 2);

            // Check if all guarantors have accepted (only for acceptance responses)
            $allAccepted = false;
            if ($event->response === 'accepted') {
                // Get guarantors from the pivot table
                $guarantors = \App\Models\Guarantor::where('loan_id', $loan->id)->get();
                $totalGuarantors = $guarantors->count();
                $acceptedCount = $guarantors->where('status', 'accepted')->count();
                
                // Check if all guarantors have accepted
                $allAccepted = $totalGuarantors > 0 && $acceptedCount === $totalGuarantors;
            }

            // Determine notification type and message
            if ($event->response === 'accepted') {
                if ($allAccepted) {
                    // All guarantors have accepted - use the template message
                    $notificationType = 'guarantor_acceptance';
                    $this->notificationService->create($applicant, $notificationType, [
                        'loan_id' => $loan->id,
                        'loan_number' => $loan->loan_number,
                        'amount' => $formattedAmount,
                        'guarantor_name' => $guarantorName,
                        'guarantor_id' => $guarantor->id,
                        'response' => $event->response,
                        'action_url' => config('app.url') . '/loans/' . $loan->id
                    ]);
                } else {
                    // Single guarantor accepted - use custom message
                    $this->notificationService->create($applicant, 'guarantor_acceptance', [
                        'title' => 'Guarantor Accepted',
                        'message' => "{$guarantorName} has accepted your loan guarantee request for loan #{$loan->loan_number} (KES {$formattedAmount}).",
                        'loan_id' => $loan->id,
                        'loan_number' => $loan->loan_number,
                        'amount' => $formattedAmount,
                        'guarantor_name' => $guarantorName,
                        'guarantor_id' => $guarantor->id,
                        'response' => $event->response,
                        'action_url' => config('app.url') . '/loans/' . $loan->id
                    ]);
                }
            } else {
                // Rejection - use the template
                $notificationType = 'guarantor_rejection';
                $this->notificationService->create($applicant, $notificationType, [
                    'loan_id' => $loan->id,
                    'loan_number' => $loan->loan_number,
                    'amount' => $formattedAmount,
                    'guarantor_name' => $guarantorName,
                    'guarantor_id' => $guarantor->id,
                    'response' => $event->response,
                    'action_url' => config('app.url') . '/loans/' . $loan->id
                ]);

                // Also notify admin when guarantor rejects
                $admins = User::query()->whereHas('roles', function($query) {
                    $query->where('name', 'super-admin');
                    $query->orWhere('name', 'admin');
                })->get();

                $applicantName = $applicant->first_name . ' ' . $applicant->last_name;

                foreach ($admins as $admin) {
                    $this->notificationService->create($admin, 'guarantor_rejected_loan', [
                        'loan_id' => $loan->id,
                        'loan_number' => $loan->loan_number,
                        'amount' => $formattedAmount,
                        'guarantor_name' => $guarantorName,
                        'guarantor_id' => $guarantor->id,
                        'applicant_name' => $applicantName,
                        'action_url' => config('app.url') . '/loans/' . $loan->id . '/admin-details'
                    ]);
                }
            }

            Log::info("Created {$event->response} notification for loan ID: {$loan->id}, guarantor: {$guarantor->id}, allAccepted: " . ($allAccepted ? 'true' : 'false'));

            // Also dispatch the job for email/SMS notifications
            \App\Jobs\NotifyApplicantGuarantorResponse::dispatch($event->loan, $event->response, $event->guarantorId);
        } catch (\Exception $e) {
            Log::error("Error in NotifyApplicantGuarantorResponse listener: " . $e->getMessage());
        }
    }
}
