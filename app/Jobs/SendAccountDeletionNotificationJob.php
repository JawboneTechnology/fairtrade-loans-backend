<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAccountDeletionNotificationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public string $userId;
    public string $userEmail;
    public string $userName;
    public string $employeeId;
    public ?string $deletedBy;

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
        string $userId,
        string $userEmail,
        string $userName,
        string $employeeId,
        ?string $deletedBy = null
    ) {
        $this->userId = $userId;
        $this->userEmail = $userEmail;
        $this->userName = $userName;
        $this->employeeId = $employeeId;
        $this->deletedBy = $deletedBy;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // First, send the email notification before deleting
            $this->sendAccountDeletionEmail();

            // Then perform the account deletion
            $this->deleteUserAccount();

        } catch (\Exception $e) {
            Log::error('=== ACCOUNT DELETION NOTIFICATION JOB FAILED ===');
            Log::error(PHP_EOL . json_encode([
                'user_id' => $this->userId,
                'user_email' => $this->userEmail,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            Log::error('Stack trace: ' . PHP_EOL . $e->getTraceAsString());

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Send account deletion notification via email
     */
    private function sendAccountDeletionEmail(): void
    {
        try {
            $emailData = [
                'userName' => $this->userName,
                'employeeId' => $this->employeeId,
                'email' => $this->userEmail,
                'deletedBy' => $this->deletedBy,
                'deletionDate' => now()->format('d M Y, h:i A'),
                'supportEmail' => config('mail.support_email', 'support@fairtrade.com'),
                'supportPhone' => config('app.support_phone', '+254 700 000 000'),
            ];

            Mail::send('emails.account-deleted', $emailData, function ($message) {
                $message->to($this->userEmail, $this->userName)
                    ->subject('Account Deletion Notification - Fairtrade Loans');
            });

            Log::info('=== ACCOUNT DELETION EMAIL SENT SUCCESSFULLY ===');
            Log::info('User Email: ' . $this->userEmail);

        } catch (\Exception $e) {
            Log::error('=== FAILED TO SEND ACCOUNT DELETION EMAIL ===');
            Log::error(PHP_EOL . json_encode([
                'user_id' => $this->userId,
                'email' => $this->userEmail,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            throw $e;
        }
    }

    /**
     * Delete the user account
     */
    private function deleteUserAccount(): void
    {
        try {
            $user = User::find($this->userId);

            if (!$user) {
                Log::warning('=== USER NOT FOUND FOR DELETION ===');
                Log::warning('User ID: ' . $this->userId);
                return;
            }

            // Perform soft delete (if using SoftDeletes trait)
            // Or hard delete if not using soft deletes
            $user->delete();

            Log::info('=== USER ACCOUNT DELETED SUCCESSFULLY ===');
            Log::info(PHP_EOL . json_encode([
                'user_id' => $this->userId,
                'employee_id' => $this->employeeId,
                'email' => $this->userEmail,
                'deleted_by' => $this->deletedBy ?? 'System',
                'deleted_at' => now()->toDateTimeString()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        } catch (\Exception $e) {
            Log::error('=== FAILED TO DELETE USER ACCOUNT ===');
            Log::error(PHP_EOL . json_encode([
                'user_id' => $this->userId,
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
        Log::error('=== ACCOUNT DELETION NOTIFICATION JOB FAILED PERMANENTLY ===');
        Log::error(PHP_EOL . json_encode([
            'user_id' => $this->userId,
            'user_email' => $this->userEmail,
            'error' => $exception->getMessage(),
            'max_attempts' => $this->tries
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

