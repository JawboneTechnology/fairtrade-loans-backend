<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\User;
use App\Models\LoanType;
use App\Services\NotificationService;
use App\Jobs\SendSMSJob;
use App\Services\SMSService;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\NotifyAdministratorNewLoanApplied;

class NotifyAdminNewLoanApplied implements ShouldQueue
{
    use Queueable;

    public $loan;

    /**
     * Create a new job instance.
     */
    public function __construct(Loan $loan)
    {
        $this->loan = $loan;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            // Get admin email from environment
            $adminEmail = env('APP_SYSTEM_ADMIN');
            $adminPhone = env('APP_SYSTEM_ADMIN_PHONE');

            // Get Loan type
            $loanType = LoanType::findOrFail($this->loan->loan_type_id)->name;

            // Get Applicant Name
            $applicant = User::findOrFail($this->loan->employee_id);
            $applicantName = $applicant->first_name . ' ' . $applicant->last_name;

            // Get Guarantors names
            $guarantors = User::whereIn('id', $this->loan->guarantors ?? [])->get()
                ->map(function ($guarantor) {
                    return $guarantor->first_name . ' ' . $guarantor->last_name;
                })->implode(', ');

            // Get all admin users
            $admins = User::query()->whereHas('roles', function($query) {
                $query->where('name', 'super-admin');
                $query->orWhere('name', 'admin');
            })->get();

            if ($admins->isEmpty()) {
                Log::error("No admin users found.");
                return;
            }

            // Admin dashboard URL
            $adminDashboardUrl = route('admin.dashboard'); // Update with the correct endpoint

            // Create database notifications for all admins
            foreach ($admins as $admin) {
                // Create database notification
                $notificationService->create($admin, 'new_loan_application', [
                    'loan_id' => $this->loan->id,
                    'loan_number' => $this->loan->loan_number,
                    'amount' => number_format($this->loan->loan_amount, 2),
                    'applicant_name' => $applicantName,
                    'loan_type' => $loanType,
                    'guarantors' => $guarantors,
                    'action_url' => config('app.url') . '/loans/' . $this->loan->id . '/admin-details'
                ]);

                // Send email notification
                $adminName = $admin->first_name . ' ' . $admin->last_name;
                $admin->notify(new NotifyAdministratorNewLoanApplied(
                    $adminName,
                    $applicantName,
                    $guarantors,
                    $this->loan,
                    $loanType,
                    $adminDashboardUrl
                ));

                // Send SMS to admin as well (queued)
                try {
                    if (!empty($admin->phone_number)) {
                        $smsService = app(SMSService::class);
                        $templateData = [
                            'applicant_name' => $applicantName,
                            'loan_number' => $this->loan->loan_number,
                            'amount' => number_format($this->loan->loan_amount, 2),
                            'admin_dashboard_url' => $adminDashboardUrl,
                        ];

                        Log::info('Sending SMS from template for admin', ['phone' => $admin->phone_number, 'loan_id' => $this->loan->id]);
                        $smsService->sendSMSFromTemplate($admin->phone_number, 'admin_new_loan', $templateData);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send admin SMS for new loan application: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error("Error in NotifyAdminNewLoanApplied job: " . $e->getMessage() . " Line: " . $e->getLine());
        }
    }
}
