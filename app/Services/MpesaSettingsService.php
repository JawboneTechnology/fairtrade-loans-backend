<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

class MpesaSettingsService
{
    const SETTINGS_KEY = 'mpesa_settings';

    /**
     * Get default M-Pesa settings
     */
    public function getDefaultSettings(): array
    {
        $baseUrl = config('app.url');
        
        return [
            'environment' => [
                'mode' => config('mpesa.environment', 'sandbox'),
                'api_base_url' => null, // Auto-generated based on mode
            ],
            'credentials' => [
                'consumer_key' => config('mpesa.mpesa_consumer_key', ''),
                'consumer_secret' => config('mpesa.mpesa_consumer_secret', ''),
                'passkey' => config('mpesa.passkey', ''),
            ],
            'shortcodes' => [
                'business_shortcode' => config('mpesa.shortcode', '174379'),
                'b2c_shortcode' => config('mpesa.b2c_shortcode', ''),
            ],
            'initiator' => [
                'name' => config('mpesa.initiator_name', 'testapi'),
                'password' => config('mpesa.initiator_password', ''),
            ],
            'callback_urls' => [
                'stk_push' => config('mpesa.callback_url', $baseUrl . '/api/v1/mpesa/stk/callback'),
                'c2b_validation' => config('mpesa.c2b_validation_url', $baseUrl . '/api/v1/mpesa/c2b/validation'),
                'c2b_confirmation' => config('mpesa.c2b_confirmation_url', $baseUrl . '/api/v1/mpesa/c2b/confirmation'),
                'b2c_result' => config('mpesa.b2c_result_url', $baseUrl . '/api/v1/mpesa/b2c/result'),
                'b2c_timeout' => config('mpesa.b2c_timeout_url', $baseUrl . '/api/v1/mpesa/b2c/timeout'),
                'status_result' => config('mpesa.status_result_url', $baseUrl . '/api/v1/mpesa/status/result'),
                'status_timeout' => config('mpesa.status_timeout_url', $baseUrl . '/api/v1/mpesa/status/timeout'),
                'balance_result' => config('mpesa.balance_result_url', $baseUrl . '/api/v1/mpesa/balance/result'),
                'balance_timeout' => config('mpesa.balance_timeout_url', $baseUrl . '/api/v1/mpesa/balance/timeout'),
                'reversal_result' => config('mpesa.reversal_result_url', $baseUrl . '/api/v1/mpesa/reversal/result'),
                'reversal_timeout' => config('mpesa.reversal_timeout_url', $baseUrl . '/api/v1/mpesa/reversal/timeout'),
                'b2b_result' => config('mpesa.b2b_result_url', $baseUrl . '/api/v1/mpesa/b2b/result'),
                'b2b_timeout' => config('mpesa.b2b_timeout_url', $baseUrl . '/api/v1/mpesa/b2b/timeout'),
            ],
            'transaction_limits' => [
                'stk_push' => [
                    'min_amount' => 1,
                    'max_amount' => 70000,
                ],
                'b2c' => [
                    'min_amount' => 10,
                    'max_amount' => 150000,
                ],
                'c2b' => [
                    'min_amount' => 1,
                    'max_amount' => 150000,
                ],
            ],
            'features' => [
                'enable_stk_push' => true,
                'enable_b2c' => true,
                'enable_c2b' => true,
                'enable_transaction_status_query' => true,
                'enable_account_balance' => false,
                'enable_reversal' => false,
                'enable_b2b' => false,
            ],
            'validation' => [
                'phone_number_format' => '254XXXXXXXXX',
                'require_account_reference' => true,
                'max_account_reference_length' => 20,
                'max_transaction_description_length' => 100,
            ],
            'timeouts' => [
                'stk_push_timeout' => 60,
                'b2c_timeout' => 120,
                'query_timeout' => 30,
            ],
        ];
    }

    /**
     * Get M-Pesa settings
     */
    public function getSettings(): array
    {
        $settings = SystemSetting::where('key_name', self::SETTINGS_KEY)->first();
        
        if (!$settings) {
            return $this->getDefaultSettings();
        }

        $storedSettings = json_decode($settings->key_value, true);
        $defaultSettings = $this->getDefaultSettings();

        // Merge stored settings with defaults
        $merged = array_merge($defaultSettings, $storedSettings ?? []);
        
        // Auto-generate API base URL based on environment mode
        if (isset($merged['environment']['mode'])) {
            $merged['environment']['api_base_url'] = $this->getEnvironmentUrl($merged['environment']['mode']);
        }

        return $merged;
    }

    /**
     * Update M-Pesa settings
     */
    public function updateSettings(array $settings): array
    {
        $this->validateSettings($settings);

        $existing = SystemSetting::where('key_name', self::SETTINGS_KEY)->first();
        $currentSettings = $existing ? json_decode($existing->key_value, true) : $this->getDefaultSettings();

        // Merge with existing settings
        $updatedSettings = array_merge($currentSettings, $settings);

        // Auto-generate API base URL if mode is set
        if (isset($updatedSettings['environment']['mode'])) {
            $updatedSettings['environment']['api_base_url'] = $this->getEnvironmentUrl($updatedSettings['environment']['mode']);
        }

        if ($existing) {
            $existing->update([
                'key_value' => json_encode($updatedSettings),
            ]);
        } else {
            SystemSetting::create([
                'key_name' => self::SETTINGS_KEY,
                'key_value' => json_encode($updatedSettings),
                'description' => 'M-Pesa system settings',
            ]);
        }

        return $this->getSettings();
    }

    /**
     * Validate M-Pesa settings
     */
    private function validateSettings(array $settings): void
    {
        // Validate environment
        if (isset($settings['environment']['mode']) && !in_array($settings['environment']['mode'], ['sandbox', 'production'])) {
            throw new \InvalidArgumentException('Environment mode must be either "sandbox" or "production"');
        }

        // Validate transaction limits
        if (isset($settings['transaction_limits'])) {
            foreach (['stk_push', 'b2c', 'c2b'] as $type) {
                if (isset($settings['transaction_limits'][$type])) {
                    $limits = $settings['transaction_limits'][$type];
                    
                    if (isset($limits['min_amount']) && $limits['min_amount'] < 1) {
                        throw new \InvalidArgumentException("Min amount for {$type} must be at least 1");
                    }
                    
                    if (isset($limits['max_amount']) && $limits['max_amount'] > 150000) {
                        throw new \InvalidArgumentException("Max amount for {$type} cannot exceed 150000");
                    }
                    
                    if (isset($limits['min_amount']) && isset($limits['max_amount']) && 
                        $limits['min_amount'] >= $limits['max_amount']) {
                        throw new \InvalidArgumentException("Min amount for {$type} must be less than max amount");
                    }
                }
            }
        }

        // Validate shortcodes
        if (isset($settings['shortcodes'])) {
            if (isset($settings['shortcodes']['business_shortcode'])) {
                $shortcode = $settings['shortcodes']['business_shortcode'];
                if (!preg_match('/^\d{5,6}$/', $shortcode)) {
                    throw new \InvalidArgumentException('Business shortcode must be 5-6 digits');
                }
            }
            
            if (isset($settings['shortcodes']['b2c_shortcode']) && !empty($settings['shortcodes']['b2c_shortcode'])) {
                $shortcode = $settings['shortcodes']['b2c_shortcode'];
                if (!preg_match('/^\d{5,6}$/', $shortcode)) {
                    throw new \InvalidArgumentException('B2C shortcode must be 5-6 digits');
                }
            }
        }

        // Validate callback URLs
        if (isset($settings['callback_urls'])) {
            foreach ($settings['callback_urls'] as $key => $url) {
                if ($url !== null && !filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new \InvalidArgumentException("Invalid callback URL for {$key}");
                }
            }
        }
    }

    /**
     * Get environment API base URL
     */
    public function getEnvironmentUrl(string $mode): string
    {
        return ($mode === 'production' || $mode === 'live')
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Test M-Pesa connection
     */
    public function testConnection(): array
    {
        try {
            $settings = $this->getSettings();
            $environment = $settings['environment']['mode'] ?? 'sandbox';
            
            $consumerKey = $settings['credentials']['consumer_key'] ?? '';
            $consumerSecret = $settings['credentials']['consumer_secret'] ?? '';
            
            if (empty($consumerKey) || empty($consumerSecret)) {
                return [
                    'environment' => $environment,
                    'token_generation' => 'failed',
                    'credentials_valid' => false,
                    'api_accessible' => false,
                    'error' => 'Consumer key or secret not configured',
                    'tested_at' => now()->toDateTimeString(),
                ];
            }

            // Try to generate access token
            $url = $this->getEnvironmentUrl($environment) . '/oauth/v1/generate?grant_type=client_credentials';
            $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . $credentials
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $responseData = json_decode($response, true);

            $tokenGenerated = ($httpCode === 200 && isset($responseData['access_token']));

            return [
                'environment' => $environment,
                'token_generation' => $tokenGenerated ? 'success' : 'failed',
                'credentials_valid' => $tokenGenerated,
                'api_accessible' => $httpCode !== 0,
                'http_code' => $httpCode,
                'error' => $tokenGenerated ? null : ($responseData['error_description'] ?? 'Unknown error'),
                'tested_at' => now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            Log::error('M-Pesa connection test failed: ' . $e->getMessage());
            $settings = $this->getSettings();
            return [
                'environment' => $settings['environment']['mode'] ?? 'unknown',
                'token_generation' => 'failed',
                'credentials_valid' => false,
                'api_accessible' => false,
                'error' => $e->getMessage(),
                'tested_at' => now()->toDateTimeString(),
            ];
        }
    }

    /**
     * Validate M-Pesa configuration
     */
    public function validateConfiguration(): array
    {
        $settings = $this->getSettings();
        $errors = [];
        $warnings = [];
        $checks = [];

        // Check credentials
        $checks['credentials'] = [
            'consumer_key' => !empty($settings['credentials']['consumer_key']) ? 'valid' : 'missing',
            'consumer_secret' => !empty($settings['credentials']['consumer_secret']) ? 'valid' : 'missing',
            'passkey' => !empty($settings['credentials']['passkey']) ? 'valid' : 'missing',
        ];

        if (empty($settings['credentials']['consumer_key'])) {
            $errors[] = 'Consumer key is required';
        }
        if (empty($settings['credentials']['consumer_secret'])) {
            $errors[] = 'Consumer secret is required';
        }
        if (empty($settings['credentials']['passkey'])) {
            $warnings[] = 'Passkey is required for STK Push';
        }

        // Check shortcodes
        $checks['shortcodes'] = [
            'business_shortcode' => !empty($settings['shortcodes']['business_shortcode']) ? 'valid' : 'missing',
            'b2c_shortcode' => !empty($settings['shortcodes']['b2c_shortcode']) ? 'valid' : 'missing',
        ];

        if (empty($settings['shortcodes']['business_shortcode'])) {
            $errors[] = 'Business shortcode is required';
        }
        if ($settings['features']['enable_b2c'] && empty($settings['shortcodes']['b2c_shortcode'])) {
            $errors[] = 'B2C shortcode is required when B2C is enabled';
        }

        // Check callback URLs
        $checks['callback_urls'] = [];
        if ($settings['features']['enable_stk_push']) {
            $checks['callback_urls']['stk_push'] = !empty($settings['callback_urls']['stk_push']) ? 'valid' : 'missing';
            if (empty($settings['callback_urls']['stk_push'])) {
                $warnings[] = 'STK Push callback URL is recommended';
            }
        }
        if ($settings['features']['enable_c2b']) {
            $checks['callback_urls']['c2b_validation'] = !empty($settings['callback_urls']['c2b_validation']) ? 'valid' : 'missing';
            $checks['callback_urls']['c2b_confirmation'] = !empty($settings['callback_urls']['c2b_confirmation']) ? 'valid' : 'missing';
        }
        if ($settings['features']['enable_b2c']) {
            $checks['callback_urls']['b2c_result'] = !empty($settings['callback_urls']['b2c_result']) ? 'valid' : 'missing';
            $checks['callback_urls']['b2c_timeout'] = !empty($settings['callback_urls']['b2c_timeout']) ? 'valid' : 'missing';
        }

        // Check initiator credentials for B2C
        $checks['initiator'] = [
            'name' => !empty($settings['initiator']['name']) ? 'valid' : 'missing',
            'password' => !empty($settings['initiator']['password']) ? 'valid' : 'missing',
        ];

        if ($settings['features']['enable_b2c']) {
            if (empty($settings['initiator']['name'])) {
                $errors[] = 'Initiator name is required for B2C';
            }
            if (empty($settings['initiator']['password'])) {
                $errors[] = 'Initiator password is required for B2C';
            }
        }

        // Check features configuration
        $checks['features'] = [
            'stk_push_configured' => $settings['features']['enable_stk_push'] && !empty($settings['credentials']['passkey']),
            'b2c_configured' => $settings['features']['enable_b2c'] && 
                              !empty($settings['shortcodes']['b2c_shortcode']) &&
                              !empty($settings['initiator']['name']) &&
                              !empty($settings['initiator']['password']),
            'c2b_configured' => $settings['features']['enable_c2b'] && 
                               !empty($settings['callback_urls']['c2b_validation']) &&
                               !empty($settings['callback_urls']['c2b_confirmation']),
        ];

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'checks' => $checks,
        ];
    }

    /**
     * Get transaction statistics
     */
    public function getTransactionStatistics(): array
    {
        try {
            $totalTransactions = \App\Models\MpesaTransaction::count();
            $successfulTransactions = \App\Models\MpesaTransaction::where('status', 'COMPLETED')->count();
            $failedTransactions = \App\Models\MpesaTransaction::where('status', 'FAILED')->count();
            $pendingTransactions = \App\Models\MpesaTransaction::where('status', 'PENDING')->count();

            $successRate = $totalTransactions > 0 
                ? round(($successfulTransactions / $totalTransactions) * 100, 2)
                : 0;

            $totalAmount = \App\Models\MpesaTransaction::where('status', 'COMPLETED')
                ->sum('amount');

            $byType = \App\Models\MpesaTransaction::select('transaction_type', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('transaction_type')
                ->pluck('count', 'transaction_type')
                ->toArray();

            $byStatus = \App\Models\MpesaTransaction::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            return [
                'total_transactions' => $totalTransactions,
                'successful_transactions' => $successfulTransactions,
                'failed_transactions' => $failedTransactions,
                'pending_transactions' => $pendingTransactions,
                'success_rate' => $successRate,
                'total_amount' => round($totalAmount, 2),
                'by_type' => $byType,
                'by_status' => $byStatus,
            ];
        } catch (\Exception $e) {
            Log::error('Error generating M-Pesa statistics: ' . $e->getMessage());
            throw new \Exception('Error generating M-Pesa statistics: ' . $e->getMessage());
        }
    }

    /**
     * Mask sensitive data
     */
    public function maskSensitiveData(array $settings): array
    {
        if (isset($settings['credentials']['consumer_secret'])) {
            $settings['credentials']['consumer_secret'] = $this->maskString($settings['credentials']['consumer_secret']);
        }
        if (isset($settings['credentials']['passkey'])) {
            $settings['credentials']['passkey'] = $this->maskString($settings['credentials']['passkey']);
        }
        if (isset($settings['initiator']['password'])) {
            $settings['initiator']['password'] = $this->maskString($settings['initiator']['password']);
        }

        return $settings;
    }

    /**
     * Mask a string for display
     */
    private function maskString(string $value): string
    {
        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }
        
        return substr($value, 0, 4) . str_repeat('*', strlen($value) - 8) . substr($value, -4);
    }
}

