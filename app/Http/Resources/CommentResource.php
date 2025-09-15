<?php

namespace App\Http\Resources;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entitySummary = null;
        if ($this->relationLoaded('entity') && $this->entity) {
            $entitySummary = [
                'type' => class_basename($this->entity),
                'id' => $this->entity->id,
            ];
            if ($this->entity instanceof Task) {
                $entitySummary['title'] = $this->entity->title;
            } elseif ($this->entity instanceof Project) {
                $entitySummary['name'] = $this->entity->name;
            } elseif ($this->entity instanceof Milestone) {
                $entitySummary['name'] = $this->entity->name;
            }
        }

        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'user_id' => $this->user_id,
            'content' => $this->content,
            'entity' => $this->when($entitySummary !== null, $entitySummary),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}

