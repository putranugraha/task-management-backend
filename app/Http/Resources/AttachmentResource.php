<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'uploaded_by' => $this->uploaded_by,
            'filename' => $this->filename,
            'mime' => $this->mime,
            'storage_path' => $this->storage_path,
            'size' => (int) $this->size,
            'uploaded_at' => optional($this->uploaded_at)->toDateTimeString(),
            'entity' => $this->whenLoaded('entity', function () {
                return [
                    'type' => class_basename($this->entity),
                    'id' => $this->entity->id,
                ];
            }),
            'uploader' => $this->whenLoaded('uploader', function () {
                return [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->name,
                    'email' => $this->uploader->email,
                ];
            }),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}

