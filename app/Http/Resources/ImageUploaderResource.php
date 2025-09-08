<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageUploaderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id"=> $this->id,
            'image_path' => $this->image_path,
            'original_name' => $this->original_name,
            'file_size' => $this->file_size,
            'file_extension' => $this->file_extension,
            'thumbnail_width' => $this->thumbnail_width,
            'thumbnail_height' => $this->thumbnail_height,
            'created_at' => $this->created_at,
            'updated_at'=> $this->updated_at
        ];
    }
}
