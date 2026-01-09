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

            // Use template-based SMS
            $templateType = $this->status === 'approved' ? 'grant_approved' : 'grant_rejected';
            $templateData = $this->buildTemplateData($user);

            Log::info('Sending grant status SMS notification from template', [
                'phone' => $recipientPhone,
                'grant_id' => $this->grant->id,
                'status' => $this->status,
                'template_type' => $templateType
            ]);

            // Send SMS via SMS service using template
            $smsService->sendSMSFromTemplate($recipientPhone, $templateType, $templateData, $user->id);

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
     * Build template data for grant status SMS
     */
    private function buildTemplateData(User $user): array
    {
        $grantType = GrantType::find($this->grant->grant_type_id);
        $grantTypeName = $grantType ? $grantType->name : 'grant';
        $userName = $user->first_name;
        $amount = number_format($this->grant->amount, 2);

        return [
            'user_name' => $userName,
            'grant_type' => $grantTypeName,
            'amount' => $amount,
            'remarks' => $this->adminNotes ?? '',
        ];
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

