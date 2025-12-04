<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Laravel\Sanctum\PersonalAccessToken;
use App\Http\Resources\NotificationResource;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService) {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request): StreamedResponse
    {
        // Increase time limit and memory for long-running connection
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        // Validate token for SSE connection
        $this->validateToken($request);
        $user = $request->user();

        return response()->stream(function () use ($user, $request) {
            // Track last sent notification ID to avoid duplicates
            $lastNotificationId = null;
            $eventId = 0;
            $heartbeatInterval = 0;

            // Get last event ID from client (for reconnection)
            $lastEventId = $request->header('Last-Event-ID');
            if ($lastEventId) {
                $eventId = (int) $lastEventId;
            }

            try {
                // Send initial notifications
                $lastNotificationId = $this->sendNewNotifications($user, $lastNotificationId, $eventId);

                // Keep connection open and check for new notifications
                while (true) {
                    // Check if client disconnected
                    if (connection_aborted()) {
                        Log::info('SSE connection aborted for user: ' . $user->id);
                        break;
                    }

                    // Send heartbeat every 15 seconds to keep connection alive
                    if ($heartbeatInterval % 3 === 0) {
                        echo ": heartbeat\n\n";
                        if (ob_get_level() > 0) {
                            @ob_flush();
                        }
                        @flush();
                    }

                    // Check for new notifications every 5 seconds
                    $lastNotificationId = $this->sendNewNotifications($user, $lastNotificationId, $eventId);

                    // Garbage collection for long-running processes
                    if ($heartbeatInterval % 12 === 0) { // Every 60 seconds
                        gc_collect_cycles();
                    }

                    $heartbeatInterval++;
                    sleep(5); // Poll every 5 seconds
                }
            } catch (\Exception $e) {
                Log::error('SSE stream error for user ' . $user->id . ': ' . $e->getMessage());
                
                // Send error event to client
                echo "event: error\n";
                echo "data: " . json_encode(['error' => 'Connection error occurred']) . "\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();
            }
        }, 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Content-Type' => 'text/event-stream',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable Nginx buffering
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Send only NEW notifications that haven't been sent yet
     */
    protected function sendNewNotifications($user, &$lastNotificationId, &$eventId): ?string
    {
        try {
            $query = Notification::where('user_id', $user->id)
                ->where('is_read', 0)
                ->orderBy('created_at', 'desc');

            // Only get notifications created after the last sent one
            if ($lastNotificationId) {
                $query->where('id', '>', $lastNotificationId);
            }

            $notifications = $query->limit(10)->get();

            // Only send if there are new notifications
            if ($notifications->isNotEmpty()) {
                $data = [
                    'success' => true,
                    'data' => NotificationResource::collection($notifications),
                    'count' => $notifications->count(),
                    'timestamp' => now()->toDateTimeString()
                ];

                // Send with event ID for reconnection support
                $eventId++;
                echo "id: {$eventId}\n";
                echo "retry: 5000\n"; // Client should retry after 5 seconds if disconnected
                echo "event: notification\n";
                echo "data: " . json_encode($data) . "\n\n";

                // Flush output buffer
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();

                // Update last sent notification ID
                $lastNotificationId = $notifications->first()->id;
                
                Log::info('SSE: Sent ' . $notifications->count() . ' notifications to user: ' . $user->id);
            }

            return $lastNotificationId;

        } catch (\Exception $e) {
            Log::error('Error sending SSE notifications: ' . $e->getMessage());
            return $lastNotificationId;
        }
    }

    /**
     * Legacy method - kept for compatibility
     * @deprecated Use sendNewNotifications instead
     */
    protected function sendEvent($user): void
    {
        $notifications = Notification::where('user_id', $user->id)
            ->where('is_read', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        $data = [
            'success' => true,
            'data' => NotificationResource::collection($notifications),
            'timestamp' => now()->toDateTimeString()
        ];

        echo "event: notification\n";
        echo "data: " . json_encode($data) . "\n\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }

    protected function validateToken(Request $request): void
    {
        // Check for token in query parameter (for SSE connections that can't send headers easily)
        if (!$request->user() && $token = $request->query('api_token')) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken && $accessToken->tokenable) {
                auth()->setUser($accessToken->tokenable);
            }
        }

        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthorized');
        }
    }

    public function markAsRead(Request $request, $notificationId): JsonResponse
    {
        try {
            $notification = Notification::findOrFail($notificationId);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found.',
                    'data' => []
                ], 404);
            }

            // Check if user owns this notification
            if ($notification->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to mark this notification as read.',
                    'data' => []
                ], 403);
            }

            $this->notificationService->markAsRead($notification);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => new NotificationResource($notification)
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking notification as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get all notifications for authenticated user (regular GET endpoint)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getNotifications(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $perPage = $request->input('per_page', 20);
            $unreadOnly = $request->boolean('unread_only', false);

            $query = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            if ($unreadOnly) {
                $query->where('is_read', false);
            }

            $notifications = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Notifications fetched successfully',
                'data' => NotificationResource::collection($notifications),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'unread_count' => $this->notificationService->getUnreadCount($user)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    public function userNotifications(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $perPage = $request->input('per_page', 20);
            $unreadOnly = $request->boolean('unread_only', false);

            $query = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            if ($unreadOnly) {
                $query->where('is_read', 0);
            }

            $notifications = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'User Notifications Fetched Successfully',
                'data' => NotificationResource::collection($notifications),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'unread_count' => Notification::where('user_id', $user->id)
                        ->where('is_read', 0)
                        ->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get unread notifications for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUnreadNotifications(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $notifications = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Unread notifications fetched successfully',
                'data' => NotificationResource::collection($notifications),
                'count' => $notifications->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching unread notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'count' => 0
            ], 500);
        }
    }

    /**
     * Get unread notification count for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $count = $this->notificationService->getUnreadCount($user);

            return response()->json([
                'success' => true,
                'count' => $count,
                'message' => 'Unread count fetched successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching unread count: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'count' => 0,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $count = $this->notificationService->markAllAsRead($user);

            return response()->json([
                'success' => true,
                'message' => "Marked {$count} notification(s) as read",
                'count' => 0,
                'marked_count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'count' => 0
            ], 500);
        }
    }

    /**
     * Test endpoint to create a notification for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testNotification(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $notification = $this->notificationService->create($user, 'test_notification', [
                'title' => 'Test Notification',
                'message' => 'This is a test notification to verify the notification system is working correctly.',
                'test_data' => [
                    'timestamp' => now()->toDateTimeString(),
                    'user_id' => $user->id,
                    'user_name' => $user->first_name . ' ' . $user->last_name
                ],
                'action_url' => config('app.url') . '/notifications'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test notification created and broadcasted successfully',
                'data' => new NotificationResource($notification),
                'unread_count' => $this->notificationService->getUnreadCount($user)
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating test notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Delete a notification
     *
     * @param Request $request
     * @param string $notificationId
     * @return JsonResponse
     */
    public function delete(Request $request, string $notificationId): JsonResponse
    {
        try {
            $user = auth()->user();
            $notification = Notification::findOrFail($notificationId);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found.',
                    'data' => null
                ], 404);
            }

            // Check if user owns this notification
            if ($notification->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this notification.',
                    'data' => null
                ], 403);
            }

            $this->notificationService->delete($notification);

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully',
                'data' => null
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
                'data' => null
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
