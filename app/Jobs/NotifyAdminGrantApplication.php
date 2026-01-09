<?php

namespace App\Jobs;

use App\Models\Dependant;
use App\Models\Grant;
use App\Models\GrantType;
use App\Models\User;
use App\Services\NotificationService;
use App\Notifications\AdminGrantAppliedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendSMSJob;
use App\Services\SMSService;

class NotifyAdminGrantApplication implements ShouldQueue
{
    use Queueable;

    public Grant  $grant;

    /**
     * Create a new job instance.
     */
    public function __construct(Grant $grant)
    {
        $this->grant = $grant;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            // Get all admin users
            $admins = User::query()->whereHas('roles', function($query) {
                $query->where('name', 'super-admin');
                $query->orWhere('name', 'admin');
            })->get();

            if ($admins->isEmpty()) {
                Log::error('System admins not found');
                return;
            }

            // Eager load related models
            $dependant = $this->grant->dependant_id ? Dependant::query()->find($this->grant->dependant_id) : null;
            $grantType = GrantType::query()->find($this->grant->grant_type_id);
            $applicant = User::query()->find($this->grant->user_id);
            $applicantName = $applicant->first_name . ' ' . $applicant->last_name;

            foreach ($admins as $admin) {
                // Create database notification
                $notificationService->create($admin, 'new_grant_application', [
                    'grant_id' => $this->grant->id,
                    'grant_number' => $this->grant->grant_number,
                    'amount' => number_format($this->grant->amount, 2),
                    'applicant_name' => $applicantName,
                    'grant_type' => $grantType->name,
                    'dependant_name' => $dependant ? $dependant->first_name . ' ' . $dependant->last_name : null,
                    'action_url' => config('app.url') . '/grants/' . $this->grant->id . '/admin-details'
                ]);

                // Send email notification
                $admin->notify(new AdminGrantAppliedNotification(
                    $this->grant,
                    $dependant,
                    $grantType,
                    $applicant
                ));

                // Send SMS to admin as well (queued)
                try {
                    if (!empty($admin->phone_number)) {
                        $applicantName = $applicant->first_name . ' ' . $applicant->last_name;
                        $smsService = app(SMSService::class);
                        $templateData = [
                            'applicant_name' => $applicantName,
                            'grant_type' => $grantType->name,
                            'amount' => number_format($this->grant->amount, 2),
                        ];

                        Log::info('Sending SMS from template for admin (grant)', ['phone' => $admin->phone_number, 'grant_id' => $this->grant->id]);
                        $smsService->sendSMSFromTemplate($admin->phone_number, 'admin_new_grant', $templateData);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send grant admin SMS: ' . $e->getMessage(), ['grant_id' => $this->grant->id]);
                }

                Log::info('Admin notification sent successfully', [
                    'grant_id' => $this->grant->id,
                    'admin_email' => $admin->email
                ]);
            }
        } catch (\Throwable $th) {
            Log::error('Failed to send admin grant notification', [
                'grant_id' => $this->grant->id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            throw $th;
        }
    }
}
