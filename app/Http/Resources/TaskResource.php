<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'start_planned' => optional($this->start_planned)->format('Y-m-d'),
            'end_planned' => optional($this->end_planned)->format('Y-m-d'),
            'duration_planned' => $this->duration_planned,
            'start_actual' => optional($this->start_actual)->format('Y-m-d'),
            'end_actual' => optional($this->end_actual)->format('Y-m-d'),
            'duration_actual' => $this->duration_actual,
            'percent_complete' => $this->percent_complete,
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                ];
            }),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}

