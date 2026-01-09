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

            Log::info("=== OTP CODE SENT TO USER ===");
            Log::info("User: {$userName}");
        } catch (\Exception $exception) {
            Log::error('=== ERROR SENDING OTP CODE ===');
            Log::error('File: SendOtpCode');
            Log::error('Error: ' . $exception->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $exception->getTraceAsString());
        }
    }

    /**
     * Send OTP via email notification
     */
    private function sendOtpViaEmail(string $userName): void
    {
        try {
            $this->user->notify(new SendOtpCodeNotification($userName, $this->resetCode));
            Log::info("=== OTP SENT VIA EMAIL ===");
            Log::info("Email: {$this->user->email}");
        } catch (\Exception $e) {
            Log::error("=== FAILED TO SEND OTP VIA EMAIL ===");
            Log::error("Error: " . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Send OTP via SMS using Africa's Talking API
     */
    private function sendOtpViaSms(string $userName, SMSService $smsService): void
    {
        try {
            // Use template-based SMS
            $appName = config('app.name', 'LoanApp');
            $templateData = [
                'user_name' => $userName,
                'otp_code' => $this->resetCode,
                'app_name' => $appName,
            ];

            // Send SMS using template
            $smsService->sendSMSFromTemplate(
                $this->formatPhoneNumber($this->user->phone_number),
                'otp_code',
                $templateData,
                $this->user->id
            );

            // Log success
            Log::info("=== OTP SENT VIA SMS ===");
            Log::info("Phone: {$this->user->phone_number}");
        } catch (\Exception $e) {
            Log::error("=== ERROR SENDING OTP VIA SMS ===");
            Log::error("Error: " . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
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
            Log::error("=== ERROR SAVING RESET CODE ===");
            Log::error("Error: " . $e->getMessage());
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());
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
