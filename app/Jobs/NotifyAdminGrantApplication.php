<?php

namespace App\Jobs;

use App\Models\Dependant;
use App\Models\Grant;
use App\Models\GrantType;
use App\Models\User;
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
    public function handle(): void
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

            foreach ($admins as $admin) {
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
                        $smsMessage = "New grant application from {$applicantName} for {$grantType->name} of KES " . number_format($this->grant->amount, 2) . ". Please review.";

                        Log::info('Dispatching SendSMSJob for admin (grant)', ['phone' => $admin->phone_number, 'grant_id' => $this->grant->id]);
                        SendSMSJob::dispatch($admin->phone_number, $smsMessage)->onQueue('sms');

                        // Optional synchronous fallback for debugging
                        if (env('FORCE_SEND_SMS_SYNC', false)) {
                            try {
                                app(SMSService::class)->sendSMS($admin->phone_number, $smsMessage);
                                Log::info('Synchronous grant admin SMS sent (FORCE_SEND_SMS_SYNC enabled)', ['phone' => $admin->phone_number]);
                            } catch (\Throwable $ex) {
                                Log::error('Synchronous grant admin SMS failed', ['error' => $ex->getMessage()]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch grant admin SMS: ' . $e->getMessage(), ['grant_id' => $this->grant->id]);
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
