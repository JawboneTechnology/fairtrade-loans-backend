<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SMSService;
use App\Models\PasswordReset;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\SendOtpCodeNotification;

class SendOtpCode implements ShouldQueue
{
    use Queueable;

    public $user;
    public $resetCode;
    public $sendVia;

    /**
     * Create a new job instance.
     */
    public function __construct(string $resetCode, User $user, string $sendVia = 'both')
    {
        $this->user = $user;
        $this->resetCode = $resetCode;
        $this->sendVia = $sendVia;
    }

    /**
     * Execute the job.
     */
    public function handle(SMSService $smsService): void
    {
        try {
            $userName = $this->user->first_name . ' ' . $this->user->last_name;

            self::saveOtpToken($this->resetCode, $this->user->email);

            // Send via email
            if (in_array($this->sendVia, ['email', 'both'])) {
                $this->sendOtpViaEmail($userName);
            }

            // Send via SMS
            if (in_array($this->sendVia, ['sms', 'both'])) {
                $this->sendOtpViaSms($userName, $smsService);
            }

            $this->user->notify(new SendOtpCodeNotification($userName, $this->resetCode));

            Log::info("OTP Code sent to user: {$userName}");
        } catch (\Exception $exception) {
            Log::error('Error sending email to user in file: SendOtpCode' . $exception->getMessage());
        }
    }

    /**
     * Send OTP via email notification
     */
    private function sendOtpViaEmail(string $userName): void
    {
        try {
            $this->user->notify(new SendOtpCodeNotification($userName, $this->resetCode));
            Log::info("OTP sent via email to: {$this->user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send OTP via email: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send OTP via SMS using Africa's Talking API
     */
    private function sendOtpViaSms(string $userName, SMSService $smsService): void
    {
        try {
            // Construct the SMS message
            $appName = config('app.name', 'LoanApp');
            $message = "Hello {$userName}, your {$appName} OTP code is: {$this->resetCode}. This code expires in 30 minutes. Do not share this code.";

            // Send SMS
            $smsService->sendSms($this->formatPhoneNumber($this->user->phone_number), $message, $this->user->id);

            // Log success
            Log::info("OTP sent via SMS to: {$this->user->phone_number}");
        } catch (\Exception $e) {
            Log::error("Error sending OTP via SMS: " . $e->getMessage());
        }
    }

    private static function saveOtpToken(string $resetCode, string $email): void
    {
        try {
            $resetToken = PasswordReset::where('email', $email)->first();

            if (!$resetToken) {
                PasswordReset::create([
                    "reset_code" => $resetCode,
                    "email"      => $email,
                    "expires_at" => now()->addMinutes(30),
                ]);
            } else {
                $resetToken->update([
                    "reset_code" => $resetCode,
                    "expires_at" => now()->addMinutes(30),
                    "updated_at" => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error saving reset code: " . $e->getMessage() . ' Traces: ' . $e->getTraceAsString());
        }
    }

    /**
     * Format phone number for Africa's Talking (ensure it has country code)
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove any non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If phone starts with 0, replace with country code (assuming Kenya +254)
        if (substr($phone, 0, 1) === '0') {
            $phone = '+254' . substr($phone, 1);
        }
        
        // If phone doesn't start with +, add Kenya country code
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+254' . $phone;
        }

        return $phone;
    }
}
