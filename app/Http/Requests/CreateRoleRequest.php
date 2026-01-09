<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoleRequest extends FormRequest
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
            'name' => 'required|string|unique:roles,name',
            'display_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'guard_name' => 'sometimes|string|max:255',
            'priority' => 'sometimes|integer|min:0',
            'is_system_role' => 'sometimes|boolean',
            'metadata' => 'sometimes|array|nullable',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'uuid|exists:permissions,id',
        ];
    }
}
