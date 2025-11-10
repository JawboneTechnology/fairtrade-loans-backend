<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\MpesaService;

class MpesaCallbackController extends Controller
{
    /**
     * Public endpoint to register M-Pesa callback URLs (C2B and B2C) and their HTTP methods.
     * This will update the .env entries used by the application.
     *
     * Expected JSON payload (all fields optional but at least one required):
     * {
     *   "c2b_validation_url": "https://...",
     *   "c2b_validation_method": "POST",
     *   "c2b_confirmation_url": "https://...",
     *   "c2b_confirmation_method": "POST",
     *   "b2c_result_url": "https://...",
     *   "b2c_result_method": "POST",
     *   "b2c_timeout_url": "https://...",
     *   "b2c_timeout_method": "POST"
     * }
     */
    public function register(Request $request, MpesaService $mpesaService): JsonResponse
    {
        $data = $request->only([
            'c2b_validation_url', 'c2b_validation_method',
            'c2b_confirmation_url', 'c2b_confirmation_method',
            'b2c_result_url', 'b2c_result_method',
            'b2c_timeout_url', 'b2c_timeout_method'
        ]);

        if (empty(array_filter($data))) {
            return response()->json(['success' => false, 'message' => 'No callback data provided'], 400);
        }

        try {
            // Map incoming keys to .env keys
            $mapping = [
                'c2b_validation_url' => 'MPESA_C2B_VALIDATION_URL',
                'c2b_confirmation_url' => 'MPESA_C2B_CONFIRMATION_URL',
                'b2c_result_url' => 'MPESA_B2C_RESULT_URL',
                'b2c_timeout_url' => 'MPESA_B2C_TIMEOUT_URL',

                // Methods will be stored as new env keys
                'c2b_validation_method' => 'MPESA_C2B_VALIDATION_METHOD',
                'c2b_confirmation_method' => 'MPESA_C2B_CONFIRMATION_METHOD',
                'b2c_result_method' => 'MPESA_B2C_RESULT_METHOD',
                'b2c_timeout_method' => 'MPESA_B2C_TIMEOUT_METHOD',
            ];

            $updated = [];

            foreach ($mapping as $inputKey => $envKey) {
                if (isset($data[$inputKey]) && $data[$inputKey] !== null && $data[$inputKey] !== '') {
                    $this->setEnvironmentValue($envKey, $data[$inputKey]);
                    $updated[$envKey] = $data[$inputKey];
                }
            }

            // If C2B URLs were updated, attempt to register them with Safaricom
            $safaricom = null;
            if (isset($updated['MPESA_C2B_VALIDATION_URL']) || isset($updated['MPESA_C2B_CONFIRMATION_URL'])) {
                $safaricom = $mpesaService->registerC2BUrls();
            }

            Log::info('M-Pesa callback URLs updated via public endpoint', $updated);

            $payload = [
                'success' => true,
                'message' => 'Callback URLs updated successfully',
                'updated' => $updated
            ];

            if ($safaricom !== null) {
                $payload['safaricom_registration'] = $safaricom;
            }

            return response()->json($payload);
        } catch (\Exception $e) {
            Log::error('Error updating M-Pesa callback URLs: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update callback URLs', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update or add a value to the .env file.
     */
    private function setEnvironmentValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath) || !is_writable($envPath)) {
            throw new \RuntimeException('.env file not found or not writable');
        }

        $escaped = preg_replace('/([\\\\\"]/m', '\\$1', $value);

        $contents = file_get_contents($envPath);

        $pattern = "/^" . preg_quote($key, '/') . "=.*$/m";

        $line = $key . '=' . $this->maybeQuoteEnvValue($value);

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $line, $contents);
        } else {
            // Append new line
            $contents .= "\n" . $line . "\n";
        }

        file_put_contents($envPath, $contents);
    }

    private function maybeQuoteEnvValue(string $value): string
    {
        // If value contains spaces or special chars, wrap in double quotes
        if (preg_match('/\s/', $value) || str_contains($value, '"') || str_contains($value, "'")) {
            // Escape any existing double quotes
            $v = str_replace('"', '\\"', $value);
            return '"' . $v . '"';
        }
        return $value;
    }
}
