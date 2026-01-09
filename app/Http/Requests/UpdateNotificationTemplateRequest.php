<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationTemplateRequest extends FormRequest
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
        $templateId = $this->route('id');
        
        return [
            'type' => [
                'sometimes',
                'string',
                Rule::unique('notification_templates', 'type')->ignore($templateId, 'id')
            ],
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'channels' => 'sometimes|array',
            'channels.*' => 'string|in:database,email,sms,push',
        ];
    }
}

