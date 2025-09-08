<?php

namespace App\Jobs;

use App\Models\Dependant;
use App\Models\Grant;
use App\Models\GrantType;
use App\Models\User;
use App\Notifications\AdminGrantAppliedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyAdminGrantApplication implements ShouldQueue
{
    use Queueable;

    public Grant  $grant;

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
            // Get all admin users
            $admins = User::query()->whereHas('roles', function($query) {
                $query->where('name', 'super-admin');
                $query->orWhere('name', 'admin');
            })->get();

            if ($admins->isEmpty()) {
                Log::error('System admins not found');
                return;
            }

            // Eager load related models
            $dependant = $this->grant->dependant_id ? Dependant::query()->find($this->grant->dependant_id) : null;
            $grantType = GrantType::query()->find($this->grant->grant_type_id);
            $applicant = User::query()->find($this->grant->user_id);

            foreach ($admins as $admin) {
                $admin->notify(new AdminGrantAppliedNotification(
                    $this->grant,
                    $dependant,
                    $grantType,
                    $applicant
                ));

                Log::info('Admin notification sent successfully', [
                    'grant_id' => $this->grant->id,
                    'admin_email' => $admin->email
                ]);
            }
        } catch (\Throwable $th) {
            Log::error('Failed to send admin grant notification', [
                'grant_id' => $this->grant->id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            throw $th;
        }
    }
}
