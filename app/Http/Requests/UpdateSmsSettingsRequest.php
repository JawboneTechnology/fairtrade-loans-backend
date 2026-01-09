<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSmsSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => 'sometimes|array',
            'provider.username' => 'sometimes|string|max:255',
            'provider.api_key' => 'sometimes|string|max:255',
            'provider.sender_id' => 'sometimes|string|max:11',
            'rate_limits' => 'sometimes|array',
            'rate_limits.max_per_minute' => 'sometimes|integer|min:1|max:1000',
            'rate_limits.max_per_hour' => 'sometimes|integer|min:1|max:10000',
            'rate_limits.max_per_day' => 'sometimes|integer|min:1|max:100000',
            'features' => 'sometimes|array',
            'features.enable_sms' => 'sometimes|boolean',
            'features.enable_bulk_sms' => 'sometimes|boolean',
            'features.enable_sms_notifications' => 'sometimes|boolean',
            'defaults' => 'sometimes|array',
            'defaults.country_code' => 'sometimes|string|max:10',
            'defaults.message_encoding' => 'sometimes|string|in:GSM7,UCS2',
        ];
    }
}
