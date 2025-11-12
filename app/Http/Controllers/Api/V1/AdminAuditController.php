<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LoanNotification;
use App\Models\SystemActivity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class AdminAuditController extends Controller
{
    /**
     * Get loan notifications summary
     */
    public function getLoanNotifications(Request $request): JsonResponse
    {
        try {
            $query = LoanNotification::with(['loan', 'user']);

            // Apply filters
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('notification_type')) {
                $query->where('notification_type', $request->notification_type);
            }

            if ($request->filled('channel')) {
                $query->where('channel', $request->channel);
            }

            // Paginate results
            $notifications = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 50);

            // Get summary statistics
            $statistics = LoanNotification::getStatistics(
                $request->filled('date_from') ? Carbon::parse($request->date_from) : today()
            );

            return response()->json([
                'success' => true,
                'message' => 'Loan notifications retrieved successfully',
                'data' => [
                    'notifications' => $notifications,
                    'statistics' => $statistics,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve loan notifications',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system activities summary
     */
    public function getSystemActivities(Request $request): JsonResponse
    {
        try {
            $query = SystemActivity::with(['triggeredByUser']);

            // Apply filters
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            if ($request->filled('activity_type')) {
                $query->where('activity_type', $request->activity_type);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('command_name')) {
                $query->where('command_name', $request->command_name);
            }

            // Paginate results
            $activities = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 50);

            // Get summary statistics
            $statistics = SystemActivity::getStatistics(
                $request->filled('date_from') ? Carbon::parse($request->date_from) : today()
            );

            return response()->json([
                'success' => true,
                'message' => 'System activities retrieved successfully',
                'data' => [
                    'activities' => $activities,
                    'statistics' => $statistics,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system activities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recent activities for dashboard
     */
    public function getRecentActivities(Request $request): JsonResponse
    {
        try {
            $limit = $request->limit ?? 10;

            $recentNotifications = LoanNotification::getRecentNotifications($limit);
            $recentActivities = SystemActivity::getRecentActivities($limit);

            return response()->json([
                'success' => true,
                'message' => 'Recent activities retrieved successfully',
                'data' => [
                    'recent_notifications' => $recentNotifications,
                    'recent_activities' => $recentActivities,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recent activities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get notification statistics by type
     */
    public function getNotificationStats(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : today()->subDays(30);
            $dateTo = $request->date_to ? Carbon::parse($request->date_to) : today();

            $stats = LoanNotification::selectRaw('
                notification_type,
                channel,
                status,
                COUNT(*) as count,
                DATE(created_at) as date
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy(['notification_type', 'channel', 'status', 'date'])
            ->orderBy('date', 'desc')
            ->get()
            ->groupBy(['date', 'notification_type', 'channel']);

            return response()->json([
                'success' => true,
                'message' => 'Notification statistics retrieved successfully',
                'data' => [
                    'date_range' => [
                        'from' => $dateFrom->toDateString(),
                        'to' => $dateTo->toDateString(),
                    ],
                    'statistics' => $stats,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system performance metrics
     */
    public function getSystemMetrics(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : today()->subDays(7);
            $dateTo = $request->date_to ? Carbon::parse($request->date_to) : today();

            // Get daily metrics
            $dailyMetrics = [];
            $currentDate = $dateFrom->copy();
            
            while ($currentDate->lte($dateTo)) {
                $notificationStats = LoanNotification::getStatistics($currentDate);
                $activityStats = SystemActivity::getStatistics($currentDate);
                
                $dailyMetrics[$currentDate->toDateString()] = [
                    'notifications' => $notificationStats,
                    'activities' => $activityStats,
                ];
                
                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'message' => 'System metrics retrieved successfully',
                'data' => [
                    'date_range' => [
                        'from' => $dateFrom->toDateString(),
                        'to' => $dateTo->toDateString(),
                    ],
                    'daily_metrics' => $dailyMetrics,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system metrics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}