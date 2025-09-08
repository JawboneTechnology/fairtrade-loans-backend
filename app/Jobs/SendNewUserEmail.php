<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Notifications\newPasswordNotification;
use Illuminate\Support\Facades\Log;

class SendNewUserEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $password;
    public $verificationUrl;

    public function __construct(User $user, $password, $verificationUrl)
    {
        $this->user = $user;
        $this->password = $password;
        $this->verificationUrl = $verificationUrl;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $employeeName = $this->user->first_name . ' ' . $this->user->last_name;
            $employeeEmail = $this->user->email;

            $this->user->notify(new newPasswordNotification($employeeName, $employeeEmail, $this->password, $this->verificationUrl));

            Log::info("New User created for $employeeName $employeeEmail in SendNewUserEmail");
        } catch (\Exception $e) {
            Log::error("Error creating new user for $employeeName $employeeEmail in SendNewUserEmail message: " . $e->getMessage());
        }
    }
}
