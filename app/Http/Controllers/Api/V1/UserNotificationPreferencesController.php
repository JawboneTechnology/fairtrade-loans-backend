<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserNotificationPreferencesRequest;
use App\Services\UserNotificationPreferencesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UserNotificationPreferencesController extends Controller
{
    protected UserNotificationPreferencesService $preferencesService;

    public function __construct(UserNotificationPreferencesService $preferencesService)
    {
        $this->preferencesService = $preferencesService;
    }

    /**
     * Get current user's notification preferences
     */
    public function getPreferences(): JsonResponse
    {
        try {
            $user = auth()->user();
            $preferences = $this->preferencesService->getUserPreferences($user);

            return response()->json([
                'success' => true,
                'message' => 'Notification preferences retrieved successfully',
                'data' => $preferences,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving notification preferences: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification preferences',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update current user's notification preferences
     */
    public function updatePreferences(UpdateUserNotificationPreferencesRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $updatedUser = $this->preferencesService->updateUserPreferences($user, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Notification preferences updated successfully',
                'data' => $updatedUser->notification_preferences,
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error updating notification preferences: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification preferences',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Reset notification preferences to defaults
     */
    public function resetPreferences(): JsonResponse
    {
        try {
            $user = auth()->user();
            $defaults = $this->preferencesService->getDefaultPreferences();
            $updatedUser = $this->preferencesService->updateUserPreferences($user, $defaults);

            return response()->json([
                'success' => true,
                'message' => 'Notification preferences reset to defaults successfully',
                'data' => $updatedUser->notification_preferences,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error resetting notification preferences: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset notification preferences',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}

