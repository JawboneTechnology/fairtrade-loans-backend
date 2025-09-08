<?php

namespace App\Jobs;

use App\Models\PasswordReset;
use App\Models\User;
use App\Notifications\SendOtpCodeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOtpCode implements ShouldQueue
{
    use Queueable;

    public $user;
    public $resetCode;

    /**
     * Create a new job instance.
     */
    public function __construct(string $resetCode, User $user)
    {
        $this->user = $user;
        $this->resetCode = $resetCode;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $userName = $this->user->first_name . ' ' . $this->user->last_name;

            self::saveOtpToken($this->resetCode, $this->user->email);

            $this->user->notify(new SendOtpCodeNotification($userName, $this->resetCode));

            Log::info("OTP Code sent to user: {$userName}");
        } catch (\Exception $exception) {
            Log::error('Error sending email to user in file: SendOtpCode' . $exception->getMessage());
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
                    "expired_at" => now()->addMinutes(30),
                ]);
            } else {
                $resetToken->update([
                    "reset_code" => $resetCode,
                    "expired_at" => now()->addMinutes(30),
                    "updated_at" => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error saving reset code: " . $e->getMessage() . ' Traces: ' . $e->getTraceAsString());
        }
    }
}
