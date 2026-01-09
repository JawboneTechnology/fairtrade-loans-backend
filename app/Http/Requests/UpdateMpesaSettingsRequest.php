<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMpesaSettingsRequest extends FormRequest
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
            'environment' => 'sometimes|array',
            'environment.mode' => 'sometimes|string|in:sandbox,production',
            
            'credentials' => 'sometimes|array',
            'credentials.consumer_key' => 'sometimes|string|max:255',
            'credentials.consumer_secret' => 'sometimes|string|max:255',
            'credentials.passkey' => 'sometimes|string|max:255',
            
            'shortcodes' => 'sometimes|array',
            'shortcodes.business_shortcode' => 'sometimes|string|regex:/^\d{5,6}$/',
            'shortcodes.b2c_shortcode' => 'sometimes|nullable|string|regex:/^\d{5,6}$/',
            
            'initiator' => 'sometimes|array',
            'initiator.name' => 'sometimes|string|max:255',
            'initiator.password' => 'sometimes|string|max:255',
            
            'callback_urls' => 'sometimes|array',
            'callback_urls.stk_push' => 'sometimes|nullable|url',
            'callback_urls.c2b_validation' => 'sometimes|nullable|url',
            'callback_urls.c2b_confirmation' => 'sometimes|nullable|url',
            'callback_urls.b2c_result' => 'sometimes|nullable|url',
            'callback_urls.b2c_timeout' => 'sometimes|nullable|url',
            'callback_urls.status_result' => 'sometimes|nullable|url',
            'callback_urls.status_timeout' => 'sometimes|nullable|url',
            'callback_urls.balance_result' => 'sometimes|nullable|url',
            'callback_urls.balance_timeout' => 'sometimes|nullable|url',
            'callback_urls.reversal_result' => 'sometimes|nullable|url',
            'callback_urls.reversal_timeout' => 'sometimes|nullable|url',
            'callback_urls.b2b_result' => 'sometimes|nullable|url',
            'callback_urls.b2b_timeout' => 'sometimes|nullable|url',
            
            'transaction_limits' => 'sometimes|array',
            'transaction_limits.stk_push' => 'sometimes|array',
            'transaction_limits.stk_push.min_amount' => 'sometimes|numeric|min:1|max:150000',
            'transaction_limits.stk_push.max_amount' => 'sometimes|numeric|min:1|max:150000',
            'transaction_limits.b2c' => 'sometimes|array',
            'transaction_limits.b2c.min_amount' => 'sometimes|numeric|min:1|max:150000',
            'transaction_limits.b2c.max_amount' => 'sometimes|numeric|min:1|max:150000',
            'transaction_limits.c2b' => 'sometimes|array',
            'transaction_limits.c2b.min_amount' => 'sometimes|numeric|min:1|max:150000',
            'transaction_limits.c2b.max_amount' => 'sometimes|numeric|min:1|max:150000',
            
            'features' => 'sometimes|array',
            'features.enable_stk_push' => 'sometimes|boolean',
            'features.enable_b2c' => 'sometimes|boolean',
            'features.enable_c2b' => 'sometimes|boolean',
            'features.enable_transaction_status_query' => 'sometimes|boolean',
            'features.enable_account_balance' => 'sometimes|boolean',
            'features.enable_reversal' => 'sometimes|boolean',
            'features.enable_b2b' => 'sometimes|boolean',
            
            'validation' => 'sometimes|array',
            'validation.phone_number_format' => 'sometimes|string|max:50',
            'validation.require_account_reference' => 'sometimes|boolean',
            'validation.max_account_reference_length' => 'sometimes|integer|min:1|max:100',
            'validation.max_transaction_description_length' => 'sometimes|integer|min:1|max:500',
            
            'timeouts' => 'sometimes|array',
            'timeouts.stk_push_timeout' => 'sometimes|integer|min:30|max:300',
            'timeouts.b2c_timeout' => 'sometimes|integer|min:30|max:600',
            'timeouts.query_timeout' => 'sometimes|integer|min:10|max:120',
        ];
    }
}
