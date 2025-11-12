<?php

namespace App\Jobs;

use App\Models\Grant;
use App\Models\User;
use App\Models\Dependant;
use App\Models\GrantType;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\UserGrantApprovedNotification;
use App\Jobs\SendSMSJob;
use App\Services\SMSService;

class NotifyUserGrantApproval implements ShouldQueue
{
    use Queueable;

    public Grant $grant;

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
            // Eager load related models
            $dependant = $this->grant->dependant_id ? Dependant::query()->find($this->grant->dependant_id) : null;
            $grantType = GrantType::query()->find($this->grant->grant_type_id);
            $applicant = User::query()->find($this->grant->user_id);

            $applicant->notify(new UserGrantApprovedNotification(
                $this->grant,
                $dependant,
                $grantType,
                $applicant
            ));

            // Send SMS to applicant as well (queued)
            try {
                if (!empty($applicant->phone_number)) {
                    $applicantName = $applicant->first_name . ' ' . $applicant->last_name;
                    $smsMessage = "Dear {$applicantName}, your grant application for {$grantType->name} of KES " . number_format($this->grant->amount, 2) . " has been approved. You will receive disbursement details soon.";

                    Log::info('Dispatching SendSMSJob for grant approval', ['phone' => $applicant->phone_number, 'grant_id' => $this->grant->id]);
                    SendSMSJob::dispatch($applicant->phone_number, $smsMessage, $applicant->id)->onQueue('sms');

                    // Optional synchronous fallback for debugging
                    if (env('FORCE_SEND_SMS_SYNC', false)) {
                        try {
                            app(SMSService::class)->sendSMS($applicant->phone_number, $smsMessage);
                            Log::info('Synchronous grant approval SMS sent (FORCE_SEND_SMS_SYNC enabled)', ['phone' => $applicant->phone_number]);
                        } catch (\Throwable $ex) {
                            Log::error('Synchronous grant approval SMS failed', ['error' => $ex->getMessage()]);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to dispatch grant approval SMS: ' . $e->getMessage(), ['grant_id' => $this->grant->id]);
            }

            Log::info('User approval notification sent successfully', [
                'grant_id' => $this->grant->id,
                'admin_email' => $applicant->email
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to send user grant approval notification', [
                'grant_id' => $this->grant->id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            throw $th;
        }
    }
}
