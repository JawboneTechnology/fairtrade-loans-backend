<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
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
        $roleId = $this->route('id');
        
        return [
            'name' => [
                'sometimes',
                'string',
                \Illuminate\Validation\Rule::unique('roles', 'name')->ignore($roleId, 'id')
            ],
            'display_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'priority' => 'sometimes|integer|min:0',
            'metadata' => 'sometimes|array|nullable',
        ];
    }
}
