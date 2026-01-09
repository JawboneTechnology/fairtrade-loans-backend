<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSmsSettingsRequest;
use App\Services\SmsSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SmsSettingsController extends Controller
{
    protected SmsSettingsService $settingsService;

    public function __construct(SmsSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Get SMS settings
     */
    public function getSettings(): JsonResponse
    {
        try {
            $settings = $this->settingsService->getSettings();

            // Mask sensitive credentials
            if (isset($settings['provider']['api_key'])) {
                $settings['provider']['api_key'] = $this->maskApiKey($settings['provider']['api_key']);
            }

            return response()->json([
                'success' => true,
                'message' => 'SMS settings retrieved successfully',
                'data' => $settings,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving SMS settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve SMS settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update SMS settings
     */
    public function updateSettings(UpdateSmsSettingsRequest $request): JsonResponse
    {
        try {
            // Get current settings and merge with updates
            $currentSettings = $this->settingsService->getSettings();
            $updatedSettings = array_merge($currentSettings, $request->validated());

            $settings = $this->settingsService->updateSettings($updatedSettings);

            // Mask sensitive credentials
            if (isset($settings['provider']['api_key'])) {
                $settings['provider']['api_key'] = $this->maskApiKey($settings['provider']['api_key']);
            }

            return response()->json([
                'success' => true,
                'message' => 'SMS settings updated successfully',
                'data' => $settings,
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error updating SMS settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update SMS settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Mask API key for display
     */
    private function maskApiKey(string $apiKey): string
    {
        if (strlen($apiKey) <= 8) {
            return str_repeat('*', strlen($apiKey));
        }
        
        return substr($apiKey, 0, 4) . str_repeat('*', strlen($apiKey) - 8) . substr($apiKey, -4);
    }
}
