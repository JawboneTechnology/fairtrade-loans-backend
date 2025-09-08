<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
        'type' => $this->type,
        'title' => $this->data['title'],
        'message' => $this->data['message'],
        'data' => $this->data,
        'is_read' => $this->is_read === 0 ? false : true,
        'created_at' => $this->created_at,
        'human_date' => $this->created_at->diffForHumans()
    ];
    }
}
