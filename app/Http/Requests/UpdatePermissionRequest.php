<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
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
        $permissionId = $this->route('id');
        
        return [
            'name' => [
                'sometimes',
                'string',
                \Illuminate\Validation\Rule::unique('permissions', 'name')->ignore($permissionId, 'id')
            ],
            'display_name' => 'sometimes|string|max:255',
            'group' => 'sometimes|string|max:100|nullable',
            'description' => 'sometimes|string|nullable',
        ];
    }
}
