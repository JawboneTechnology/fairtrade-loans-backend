<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuarantorResponseRequest extends FormRequest
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
            'response' => 'required|in:accepted,declined',
            'reason' => 'nullable|string|max:255',
            'loan_id' => 'required|exists:loans,id',
            'notification_id' => 'required|exists:notifications,id',
            'guarantor_id' => 'nullable|exists:users,id'
        ];
    }
}
