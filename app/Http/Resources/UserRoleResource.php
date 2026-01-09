<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->resource['user']['id'] ?? null,
                'email' => $this->resource['user']['email'] ?? null,
                'first_name' => $this->resource['user']['first_name'] ?? null,
                'last_name' => $this->resource['user']['last_name'] ?? null,
            ],
            'roles' => $this->resource['roles'] ?? [],
            'permissions' => $this->resource['permissions'] ?? [],
            'direct_permissions' => $this->resource['direct_permissions'] ?? [],
            'permissions_via_roles' => $this->resource['permissions_via_roles'] ?? [],
        ];
    }
}
