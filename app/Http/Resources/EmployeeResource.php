<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'address' => $this->address,
            'dob' => $this->dob,
            'passport' => $this->passport,
            'gender' => $this->gender,
            'passport_image' => $this->passport_image,
            'years_of_employment' => $this->year_of_employement,
            'salary' => $this->salary,
            'token' => $this->token
        ];
    }
}
