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
            'milestone_id' => $this->milestone_id,
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
            'milestone' => new MilestoneResource($this->whenLoaded('milestone')),
            'dependencies' => $this->whenLoaded('dependencies', function () use ($request) {
                $deps = $this->dependencies;
                $filterDependsOn = $request->query('depends_on_task_id');
                if ($filterDependsOn !== null) {
                    $deps = $deps->where('depends_on_task_id', (int) $filterDependsOn);
                }
                return $deps->map(function ($dep) {
                    return [
                        'id' => $dep->id,
                        'type' => $dep->type,
                        'lag_days' => $dep->lag_days,
                        'depends_on' => [
                            'id' => $dep->dependsOn->id ?? null,
                            'title' => $dep->dependsOn->title ?? null,
                        ],
                    ];
                });
            }),
            'dependents' => $this->whenLoaded('dependents', function () {
                return $this->dependents->map(function ($dep) {
                    return [
                        'id' => $dep->id,
                        'type' => $dep->type,
                        'lag_days' => $dep->lag_days,
                        'task' => [
                            'id' => $dep->task->id ?? null,
                            'title' => $dep->task->title ?? null,
                        ],
                    ];
                });
            }),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
