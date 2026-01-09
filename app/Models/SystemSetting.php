<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key_name',
        'key_value',
        'description',
    ];

    /**
     * Get a setting value by key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = self::where('key_name', $key)->first();
        return $setting ? $setting->key_value : $default;
    }

    /**
     * Set a setting value by key
     *
     * @param string $key
     * @param mixed $value
     * @param string|null $description
     * @return SystemSetting
     */
    public static function setValue(string $key, $value, ?string $description = null): SystemSetting
    {
        return self::updateOrCreate(
            ['key_name' => $key],
            [
                'key_value' => is_array($value) ? json_encode($value) : $value,
                'description' => $description,
            ]
        );
    }

    /**
     * Get a JSON setting value decoded as array
     *
     * @param string $key
     * @param array $default
     * @return array
     */
    public static function getJsonValue(string $key, array $default = []): array
    {
        $setting = self::where('key_name', $key)->first();
        if (!$setting) {
            return $default;
        }

        $decoded = json_decode($setting->key_value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * Set a JSON setting value
     *
     * @param string $key
     * @param array $value
     * @param string|null $description
     * @return SystemSetting
     */
    public static function setJsonValue(string $key, array $value, ?string $description = null): SystemSetting
    {
        return self::updateOrCreate(
            ['key_name' => $key],
            [
                'key_value' => json_encode($value),
                'description' => $description,
            ]
        );
    }
}
