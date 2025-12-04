<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:test {--user-id= : User ID to send test notification to} {--email= : User email to send test notification to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test notification to a user';

    protected NotificationService $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        $email = $this->option('email');

        try {
            // Find user
            if ($userId) {
                $user = User::find($userId);
            } elseif ($email) {
                $user = User::where('email', $email)->first();
            } else {
                // Get first user as default
                $user = User::first();
            }

            if (!$user) {
                $this->error('User not found. Please provide a valid user-id or email.');
                return 1;
            }

            $this->info("Sending test notification to: {$user->first_name} {$user->last_name} ({$user->email})");

            // Create test notification
            $notification = $this->notificationService->create($user, 'test_notification', [
                'title' => 'Test Notification',
                'message' => 'This is a test notification to verify the notification system is working correctly. Sent at ' . now()->format('Y-m-d H:i:s'),
                'test_data' => [
                    'timestamp' => now()->toDateTimeString(),
                    'user_id' => $user->id,
                    'user_name' => $user->first_name . ' ' . $user->last_name,
                    'command' => 'notification:test'
                ],
                'action_url' => config('app.url') . '/notifications'
            ]);

            $unreadCount = $this->notificationService->getUnreadCount($user);

            $this->info("✅ Test notification created successfully!");
            $this->line("Notification ID: {$notification->id}");
            $this->line("User Unread Count: {$unreadCount}");
            $this->line("Notification should be broadcasted via WebSocket if broadcasting is configured.");

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error creating test notification: " . $e->getMessage());
            Log::error('Test notification error: ' . $e->getMessage());
            return 1;
        }
    }
}
