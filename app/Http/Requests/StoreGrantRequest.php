<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGrantRequest extends FormRequest
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
            'user_id' => 'required|string|exists:users,id',
            'grant_type_id' => 'required|string|exists:grant_types,id',
            'dependent_id' => 'nullable|string|exists:dependants,id',
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:1000',
        ];
    }
}
