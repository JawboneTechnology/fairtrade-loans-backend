<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\SendResetCodeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendResetCodeToUser implements ShouldQueue
{
    use Queueable;

    public $resetCode;
    public $email;

    /**
     * Create a new job instance.
     */
    public function __construct($resetCode, $email)
    {
        $this->resetCode = $resetCode;
        $this->email = $email;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get user
            $user = User::where('email', $this->email)->first();

            if (!$user) {
                LogError::dispatch(
                    "User not found",
                    "SendResetCodeToUser",
                    "User with email {$this->email} not found"
                );
                throw new \Exception("User with email {$this->email} not found");
            }

            $userName = $user->first_name . ' ' . $user->last_name;

            $user->notify(new SendResetCodeNotification($userName, $this->resetCode));
        } catch (\Exception $exception) {
            LogError::dispatch(
                "Something went wrong",
                "SendResetCodeToUser",
                "Error sending reset code to user: " . $exception->getMessage() . ' Traces: ' . $exception->getTraceAsString(),
            );
            throw new \Exception("Error sending reset code to user: " . $exception->getMessage() . ' Traces: ' . $exception->getTraceAsString());
        }
    }
}
