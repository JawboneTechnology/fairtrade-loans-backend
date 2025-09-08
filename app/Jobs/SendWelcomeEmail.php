<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\WelcomeEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    public $user;
    public $password;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $verificationUrl = "http://127.0.0.1:8000/api/v1/verify-account?email={$this->user->email}";
            $appStoreUrl = '';
            $playStoreUrl = '';

            // Dispatch the job to send a welcome notification
            $this->user->notify(new WelcomeEmailNotification($this->user, $verificationUrl, $appStoreUrl, $playStoreUrl, $this->password));

        } catch (\Throwable $th) {
            Log::warning('Error while sending welcome email', ['exception' => $th]);
        }
    }
}
