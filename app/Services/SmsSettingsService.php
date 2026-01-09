<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

class SmsSettingsService
{
    const SETTINGS_KEY = 'sms_settings';

    /**
     * Get default SMS settings
     */
    public function getDefaultSettings(): array
    {
        return [
            'provider' => [
                'name' => 'africastalking',
                'username' => config('services.africastalking.username', ''),
                'api_key' => config('services.africastalking.api_key', ''),
                'sender_id' => config('services.africastalking.sender_id', 'JAWBONETECH'),
            ],
            'rate_limits' => [
                'max_per_minute' => 10,
                'max_per_hour' => 100,
                'max_per_day' => 1000,
            ],
            'features' => [
                'enable_sms' => true,
                'enable_bulk_sms' => true,
                'enable_sms_notifications' => true,
            ],
            'defaults' => [
                'country_code' => '+254',
                'message_encoding' => 'GSM7',
            ],
        ];
    }

    /**
     * Get SMS settings
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
        return array_merge($defaultSettings, $storedSettings ?? []);
    }

    /**
     * Update SMS settings
     */
    public function updateSettings(array $settings): array
    {
        $this->validateSettings($settings);

        $existing = SystemSetting::where('key_name', self::SETTINGS_KEY)->first();

        if ($existing) {
            $existing->update([
                'key_value' => json_encode($settings),
            ]);
        } else {
            SystemSetting::create([
                'key_name' => self::SETTINGS_KEY,
                'key_value' => json_encode($settings),
                'description' => 'SMS system settings',
            ]);
        }

        return $this->getSettings();
    }

    /**
     * Validate SMS settings
     */
    private function validateSettings(array $settings): void
    {
        if (isset($settings['rate_limits'])) {
            $rateLimits = $settings['rate_limits'];
            
            if (isset($rateLimits['max_per_minute']) && ($rateLimits['max_per_minute'] < 1 || $rateLimits['max_per_minute'] > 1000)) {
                throw new \InvalidArgumentException('Max per minute must be between 1 and 1000');
            }
            
            if (isset($rateLimits['max_per_hour']) && ($rateLimits['max_per_hour'] < 1 || $rateLimits['max_per_hour'] > 10000)) {
                throw new \InvalidArgumentException('Max per hour must be between 1 and 10000');
            }
            
            if (isset($rateLimits['max_per_day']) && ($rateLimits['max_per_day'] < 1 || $rateLimits['max_per_day'] > 100000)) {
                throw new \InvalidArgumentException('Max per day must be between 1 and 100000');
            }
        }
    }

    /**
     * Get provider configuration
     */
    public function getProviderConfig(): array
    {
        $settings = $this->getSettings();
        return $settings['provider'] ?? [];
    }

    /**
     * Check if SMS is enabled
     */
    public function isSmsEnabled(): bool
    {
        $settings = $this->getSettings();
        return $settings['features']['enable_sms'] ?? true;
    }

    /**
     * Check if bulk SMS is enabled
     */
    public function isBulkSmsEnabled(): bool
    {
        $settings = $this->getSettings();
        return $settings['features']['enable_bulk_sms'] ?? true;
    }

    /**
     * Check if SMS notifications are enabled
     */
    public function isSmsNotificationsEnabled(): bool
    {
        $settings = $this->getSettings();
        return $settings['features']['enable_sms_notifications'] ?? true;
    }
}

