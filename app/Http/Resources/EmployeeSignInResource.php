<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSignInResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "first_name" => $this->first_name,
            "middle_name" => $this->middle_name,
            "last_name" => $this->last_name,
            "phone_number" => $this->phone_number,
            "address" => $this->address,
            "dob" => $this->dob,
            "passport_image" => $this->passport_image,
            "gender" => $this->gender,
            "email" => $this->email,
            "years_of_employment" => $this->years_of_employment,
            "employee_id" => $this->employee_id,
            "old_employee_id" => $this->old_employee_id,
            "national_id" => $this->national_id,
            "salary" => $this->salary,
            "loan_limit" => $this->loan_limit,
            "role" => $this->role,
        ];
    }
}
