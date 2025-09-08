<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\NotifyAdminAboutNewUserNotification;

class NotifyAdministratorNewUser implements ShouldQueue
{
    use Queueable;

    public $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $adminEmail = env('APP_SYSTEM_ADMIN');

            // Get Admin Details
            $administrator = User::where('email', $adminEmail)->first();

            if (!$administrator) {
                Log::error("Admin with email {$adminEmail} not found.");
                return;
            }

            $adminName = $administrator->first_name . ' ' . $administrator->last_name;

            // Handle admin email sending
            $administrator->notify(new NotifyAdminAboutNewUserNotification($adminName, $this->user));

        } catch (\Throwable $th) {
            Log::warning('Error while sending admin notification email', ['exception' => $th]);
        }

    }
}
