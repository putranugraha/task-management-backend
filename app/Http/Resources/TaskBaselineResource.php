<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskBaselineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'baseline_id' => $this->baseline_id,
            'task_id' => $this->task_id,
            'start_planned_base' => optional($this->start_planned_base)->format('Y-m-d'),
            'end_planned_base' => optional($this->end_planned_base)->format('Y-m-d'),
            'duration_planned_base' => $this->duration_planned_base,
            'weight' => $this->weight !== null ? (float) $this->weight : null,
            'planned_effort_hours' => $this->planned_effort_hours !== null ? (float) $this->planned_effort_hours : null,
            'baseline' => $this->whenLoaded('baseline', function () {
                return [
                    'id' => $this->baseline->id,
                    'baseline_name' => $this->baseline->baseline_name,
                    'project' => $this->baseline->relationLoaded('project') ? [
                        'id' => $this->baseline->project->id,
                        'name' => $this->baseline->project->name,
                    ] : null,
                ];
            }),
            'task' => $this->whenLoaded('task', function () {
                return [
                    'id' => $this->task->id,
                    'title' => $this->task->title,
                    'project_id' => $this->task->project_id,
                ];
            }),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}

