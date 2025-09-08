<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\SendMiniStatementNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendMiniStatement implements ShouldQueue
{
    use Queueable;

    public $user;
    public $statement;

    /**
     * Create a new job instance.
     */
    public function __construct(array $statement, User $user)
    {
        $this->statement = $statement;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $userName = $this->user->first_name . ' ' . $this->user->last_name;

            $this->user->notify(new SendMiniStatementNotification($userName, $this->statement));
        } catch (\Exception $exception) {
            Log::error('Error sending email to user in file: SendMiniStatement - ' . $exception->getMessage());
        }
    }
}
