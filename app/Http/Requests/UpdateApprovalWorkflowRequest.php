<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApprovalWorkflowRequest extends FormRequest
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
            'default_approval_type' => 'sometimes|string|in:manual,automatic',
            'auto_approval_threshold' => 'sometimes|numeric|min:0',
            'require_multiple_approvers' => 'sometimes|boolean',
            'required_approvers_count' => 'sometimes|integer|min:1',
            'approval_roles' => 'sometimes|array',
            'approval_roles.*' => 'string',
            'enable_escalation' => 'sometimes|boolean',
            'escalation_days' => 'sometimes|integer|min:1',
            'enable_auto_rejection' => 'sometimes|boolean',
            'auto_rejection_days' => 'sometimes|integer|min:1',
            'require_guarantor_approval' => 'sometimes|boolean',
            'guarantor_approval_deadline_days' => 'sometimes|integer|min:1',
        ];
    }
}

