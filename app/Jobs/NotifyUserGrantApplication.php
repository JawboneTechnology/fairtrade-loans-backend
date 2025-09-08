<?php

namespace App\Jobs;

use App\Models\Grant;
use App\Models\User;
use App\Models\Dependant;
use App\Models\GrantType;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\GrantApplicationReceivedNotification;

class NotifyUserGrantApplication implements ShouldQueue
{
    use Queueable;

    public Grant $grant;

    /**
     * Create a new job instance.
     */
    public function __construct(Grant $grant)
    {
        $this->grant = $grant;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $dependant = $this->grant->dependant_id ? Dependant::query()->find($this->grant->dependant_id) : null;
            $grantType = GrantType::query()->find($this->grant->grant_type_id);
            $applicant = User::query()->find($this->grant->user_id);

            $applicant->notify(new GrantApplicationReceivedNotification(
                $this->grant,
                $dependant,
                $grantType,
                $applicant
            ));

            Log::info('User notification sent successfully', [
                'grant_id' => $this->grant->id,
                'admin_email' => $applicant->email
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to send user grant notification', [
                'grant_id' => $this->grant->id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            throw $th;
        }
    }
}
