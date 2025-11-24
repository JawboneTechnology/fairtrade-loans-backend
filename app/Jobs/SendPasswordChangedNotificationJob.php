<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPasswordChangedNotificationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public string $employeeId;
    public string $newPassword;
    public ?string $changedById;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $employeeId,
        string $newPassword,
        ?string $changedById = null
    ) {
        $this->employeeId = $employeeId;
        $this->newPassword = $newPassword;
        $this->changedById = $changedById;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Fetch the employee
            $employee = User::find($this->employeeId);

            if (!$employee) {
                Log::warning('=== MISSING DATA FOR PASSWORD CHANGED NOTIFICATION JOB ===');
                Log::warning(PHP_EOL . json_encode([
                    'employee_found' => false,
                    'employee_id' => $this->employeeId
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return;
            }

            // Fetch the admin who changed the password (if available)
            $changedBy = $this->changedById ? User::find($this->changedById) : null;

            $employeeName = $employee->first_name . ' ' . $employee->last_name;
            $adminName = $changedBy ? $changedBy->first_name . ' ' . $changedBy->last_name : 'System Administrator';

            // Send email notification
            $this->sendPasswordChangedEmail($employee, $employeeName, $adminName);

        } catch (\Exception $e) {
            Log::error('=== PASSWORD CHANGED NOTIFICATION JOB FAILED ===');
            Log::error(PHP_EOL . json_encode([
                'employee_id' => $this->employeeId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Send password changed notification via email
     */
    private function sendPasswordChangedEmail(
        User $employee,
        string $employeeName,
        string $adminName
    ): void {
        try {
            $emailData = [
                'employeeName' => $employeeName,
                'adminName' => $adminName,
                'newPassword' => $this->newPassword,
                'employee' => $employee,
                'loginUrl' => config('app.url') . '/login',
            ];

            Mail::send('emails.password-changed', $emailData, function ($message) use ($employee, $employeeName) {
                $message->to($employee->email, $employeeName)
                    ->subject('Your Password Has Been Changed - Fairtrade Loans');
            });

        } catch (\Exception $e) {
            Log::error('=== FAILED TO SEND PASSWORD CHANGED EMAIL ===');
            Log::error(PHP_EOL . json_encode([
                'employee_id' => $employee->id,
                'email' => $employee->email,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('=== PASSWORD CHANGED NOTIFICATION JOB FAILED PERMANENTLY ===');
        Log::error(PHP_EOL . json_encode([
            'employee_id' => $this->employeeId,
            'error' => $exception->getMessage(),
            'max_attempts' => $this->tries
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

