<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMpesaSettingsRequest;
use App\Services\MpesaSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MpesaSettingsController extends Controller
{
    protected MpesaSettingsService $settingsService;

    public function __construct(MpesaSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Get M-Pesa settings
     */
    public function getSettings(): JsonResponse
    {
        try {
            $settings = $this->settingsService->getSettings();
            $settings = $this->settingsService->maskSensitiveData($settings);

            return response()->json([
                'success' => true,
                'message' => 'M-Pesa settings retrieved successfully',
                'data' => $settings,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving M-Pesa settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve M-Pesa settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update M-Pesa settings
     */
    public function updateSettings(UpdateMpesaSettingsRequest $request): JsonResponse
    {
        try {
            // Get current settings and merge with updates
            $currentSettings = $this->settingsService->getSettings();
            $updatedSettings = array_merge($currentSettings, $request->validated());

            $settings = $this->settingsService->updateSettings($updatedSettings);
            $settings = $this->settingsService->maskSensitiveData($settings);

            return response()->json([
                'success' => true,
                'message' => 'M-Pesa settings updated successfully',
                'data' => $settings,
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error updating M-Pesa settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update M-Pesa settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Test M-Pesa connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->settingsService->testConnection();

            return response()->json([
                'success' => $result['token_generation'] === 'success',
                'message' => $result['token_generation'] === 'success' 
                    ? 'M-Pesa connection test completed successfully' 
                    : 'M-Pesa connection test failed',
                'data' => $result,
            ], $result['token_generation'] === 'success' ? 200 : 400);
        } catch (\Exception $e) {
            Log::error('Error testing M-Pesa connection: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to test M-Pesa connection',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Validate M-Pesa configuration
     */
    public function validateConfiguration(): JsonResponse
    {
        try {
            $result = $this->settingsService->validateConfiguration();

            return response()->json([
                'success' => true,
                'message' => 'Configuration validation completed',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error validating M-Pesa configuration: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate configuration',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get M-Pesa transaction statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->settingsService->getTransactionStatistics();

            return response()->json([
                'success' => true,
                'message' => 'M-Pesa statistics retrieved successfully',
                'data' => $statistics,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving M-Pesa statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve M-Pesa statistics',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}
