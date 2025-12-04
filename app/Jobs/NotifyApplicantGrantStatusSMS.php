<?php

namespace App\Jobs;

use App\Models\Grant;
use App\Models\User;
use App\Models\GrantType;
use App\Services\SMSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyApplicantGrantStatusSMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $grant;
    public $status; // 'approved' or 'rejected'
    public $adminNotes;

    /**
     * Create a new job instance.
     */
    public function __construct(Grant $grant, string $status, ?string $adminNotes = null)
    {
        $this->grant = $grant;
        $this->status = $status;
        $this->adminNotes = $adminNotes;
    }

    /**
     * Execute the job.
     */
    public function handle(SMSService $smsService): void
    {
        try {
            $user = User::find($this->grant->user_id);

            if (!$user) {
                Log::warning('User not found for grant status SMS notification', [
                    'grant_id' => $this->grant->id,
                    'user_id' => $this->grant->user_id
                ]);
                return;
            }

            $recipientPhone = $user->phone_number ?? null;

            if (empty($recipientPhone)) {
                Log::warning('Grant status SMS not sent: no phone number for user', [
                    'grant_id' => $this->grant->id,
                    'user_id' => $user->id
                ]);
                return;
            }

            $message = $this->buildMessage($user);

            Log::info('Sending grant status SMS notification', [
                'phone' => $recipientPhone,
                'grant_id' => $this->grant->id,
                'status' => $this->status
            ]);

            // Send SMS via SMS service
            $smsService->sendSMS($recipientPhone, $message, $user->id);

            Log::info('Grant status SMS notification sent successfully', [
                'phone' => $recipientPhone,
                'grant_id' => $this->grant->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send grant status SMS notification', [
                'grant_id' => $this->grant->id,
                'status' => $this->status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Build the SMS message based on grant status
     */
    private function buildMessage(User $user): string
    {
        $grantType = GrantType::find($this->grant->grant_type_id);
        $grantTypeName = $grantType ? $grantType->name : 'grant';
        $userName = $user->first_name;
        $amount = number_format($this->grant->amount, 2);

        if ($this->status === 'approved') {
            $message = "Dear {$userName}, your grant application for {$grantTypeName} of KES {$amount} has been approved.";
            
            if ($this->adminNotes) {
                $message .= " Remarks: {$this->adminNotes}";
            }
            
            $message .= " You will receive disbursement details soon.";
        } else {
            $message = "Dear {$userName}, your grant application for {$grantTypeName} of KES {$amount} has been rejected.";
            
            if ($this->adminNotes) {
                $message .= " Remarks: {$this->adminNotes}";
            } else {
                $message .= " Please contact support for more information.";
            }
        }

        return $message;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('NotifyApplicantGrantStatusSMS job failed', [
            'grant_id' => $this->grant->id ?? null,
            'status' => $this->status ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}

