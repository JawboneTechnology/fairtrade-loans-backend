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

class NotificationController extends Controller
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService) {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request): StreamedResponse
    {
        // Increase time limit for this script
        set_time_limit(0);

        $this->validateToken($request);

        $user = $request->user();

        return response()->stream(function () use ($user) {

            // Initial data dump
            $this->sendEvent($user);

            // Keep connection open and check for new notifications
            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $this->sendEvent($user);

                // Adjust sleep time as needed (in seconds)
                sleep(5);
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'Connection' => 'keep-alive',
        ]);
    }

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

        ob_flush();
        flush();
    }

    protected function validateToken(Request $request): void
    {
        // you could check for the token manually:
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
                ]);
            }

            $this->notificationService->markAsRead($notification);

            return response()->json(['success' => true,'message' => 'Notification marked as read', 'data' => []]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $e->getTrace()
            ], 500);
        }
    }

    public function userNotifications(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $notifications = Notification::where('user_id', $user->id)
                ->where('is_read', 0)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'User Notifications Fetched Successfully',
                'data' => NotificationResource::collection($notifications),

            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $e->getTrace()
            ]);
        }
    }
}
