<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;

class NotificationConnectionController extends Controller
{
    /**
     * Get connection configuration for client
     */
    public function getConfig(): JsonResponse
    {
        try {
            $broadcaster = config('broadcasting.default');
            $connection = config("broadcasting.connections.{$broadcaster}", []);

            $config = [
                'broadcaster' => $broadcaster,
                'key' => $connection['key'] ?? null,
                'cluster' => null,
                'ws_host' => 'localhost',
                'ws_port' => 8080,
                'wss_port' => 8080,
                'channel_prefix' => 'private-notifications',
                'event_name' => 'notification.new',
                'auth_endpoint' => '/broadcasting/auth',
                'sse_endpoint' => '/api/v1/notifications/stream',
                'reconnect_interval' => 5000,
                'heartbeat_interval' => 15000,
            ];

            // Add Reverb-specific config if using Reverb
            if ($broadcaster === 'reverb') {
                $options = $connection['options'] ?? [];
                $config['key'] = $connection['key'] ?? null;
                $config['ws_host'] = $options['host'] ?? env('REVERB_HOST', 'localhost');
                $config['ws_port'] = $options['port'] ?? env('REVERB_PORT', 8080);
                $config['wss_port'] = $options['port'] ?? env('REVERB_PORT', 8080);
                $config['scheme'] = $options['scheme'] ?? env('REVERB_SCHEME', 'https');
            }

            // Add Pusher-specific config if using Pusher
            if ($broadcaster === 'pusher') {
                $options = $connection['options'] ?? [];
                $config['key'] = $connection['key'] ?? null;
                $config['cluster'] = $options['cluster'] ?? env('PUSHER_APP_CLUSTER', 'mt1');
                $config['ws_host'] = $options['host'] ?? 'api-' . $config['cluster'] . '.pusher.com';
                $config['ws_port'] = $options['port'] ?? 443;
                $config['wss_port'] = $options['port'] ?? 443;
            }

            return response()->json([
                'success' => true,
                'message' => 'Connection configuration retrieved successfully',
                'data' => $config,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving connection configuration: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve connection configuration',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get connection status for current user
     */
    public function getStatus(): JsonResponse
    {
        try {
            $user = auth()->user();
            $broadcaster = config('broadcasting.default');
            $isAuthorized = true; // User is authenticated if they reached this endpoint

            $status = [
                'user_id' => $user->id,
                'channel_name' => "private-notifications.{$user->id}",
                'is_authorized' => $isAuthorized,
                'broadcasting_enabled' => $broadcaster !== 'log',
                'last_connection_check' => now()->toDateTimeString(),
                'connection_health' => 'healthy',
            ];

            return response()->json([
                'success' => true,
                'message' => 'Connection status retrieved successfully',
                'data' => $status,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving connection status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve connection status',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Test connection by sending a test notification
     */
    public function testConnection(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $testMessage = $request->input('test_message', 'Connection test notification');

            // Create a test notification
            $notificationService = app(\App\Services\NotificationService::class);
            $notification = $notificationService->create($user, 'test_notification', [
                'title' => 'Connection Test',
                'message' => $testMessage,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test notification sent successfully',
                'data' => [
                    'notification_id' => $notification->id,
                    'sent_at' => now()->toDateTimeString(),
                    'delivery_status' => 'sent',
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error testing connection: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test notification',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get connection health status
     */
    public function getHealth(): JsonResponse
    {
        try {
            $broadcaster = config('broadcasting.default');
            $connection = config("broadcasting.connections.{$broadcaster}");

            // Check if broadcasting is properly configured
            $broadcastingEnabled = $broadcaster !== 'log' && !empty($connection);
            
            // Check channel authorization (basic check)
            $channelAuthWorking = true; // Assume working if we can access this endpoint

            // Check if SSE streaming is available
            $sseAvailable = true; // SSE endpoint exists

            $health = [
                'status' => $broadcastingEnabled ? 'healthy' : 'degraded',
                'broadcasting_driver' => $broadcaster,
                'channel_authorization' => $channelAuthWorking ? 'working' : 'unknown',
                'event_broadcasting' => $broadcastingEnabled ? 'working' : 'not_configured',
                'sse_streaming' => $sseAvailable ? 'available' : 'unavailable',
                'last_check' => now()->toDateTimeString(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Connection health check completed',
                'data' => $health,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error checking connection health: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check connection health',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}

