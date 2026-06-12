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
            'budget_cost' => (string) ($this->budget_cost ?? '0.00'),
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
            'assignments' => $this->whenLoaded('assignments', function () {
                return $this->assignments->map(function ($a) {
                    $user = $a->user;

                    $primaryRoleName = null;
                    $roleNames = [];

                    if ($user) {
                        $roles = $user->roles ?? collect();
                        $primary = $roles->first();
                        $primaryRoleName = $primary->name ?? null;
                        $roleNames = $roles->pluck('name')->values()->all();
                    }

                    return [
                        'id' => $a->id,
                        'role_on_task' => $a->role_on_task,
                        'estimated_effort_hours' => $a->estimated_effort_hours,
                        'assigned_at' => optional($a->assigned_at)->toDateTimeString(),
                        'user' => [
                            'id' => $user->id ?? null,
                            'name' => $user->name ?? null,
                            'role' => $primaryRoleName,
                            'roles' => $roleNames,
                        ],
                    ];
                });
            }),
            'progress_entries' => $this->whenLoaded('progressEntries', function () {
                return $this->progressEntries
                    ->sortBy('progress_date')
                    ->values()
                    ->map(function ($entry) {
                        $changer = $entry->changer;

                        return [
                            'id' => $entry->id,
                            'task_id' => $entry->task_id,
                            'progress_date' => optional($entry->progress_date)->format('Y-m-d'),
                            'percent_complete' => $entry->percent_complete,
                            'changed_by' => $entry->changed_by,
                            'changer' => [
                                'id' => $changer->id ?? null,
                                'name' => $changer->name ?? null,
                            ],
                            'created_at' => optional($entry->created_at)->toDateTimeString(),
                            'updated_at' => optional($entry->updated_at)->toDateTimeString(),
                        ];
                    });
            }),
            'cost_entries' => $this->whenLoaded('costEntries', function () {
                return $this->costEntries
                    ->sortBy('incurred_on')
                    ->values()
                    ->map(function ($entry) {
                        return [
                            'id' => $entry->id,
                            'task_id' => $entry->task_id,
                            'incurred_on' => optional($entry->incurred_on)->format('Y-m-d'),
                            'amount' => (string) ($entry->amount ?? '0.00'),
                            'category' => $entry->category,
                            'note' => $entry->note,
                            'created_at' => optional($entry->created_at)->toDateTimeString(),
                            'updated_at' => optional($entry->updated_at)->toDateTimeString(),
                        ];
                    });
            }),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
            'deleted_at' => optional($this->deleted_at)->toDateTimeString(),
        ];
    }
}
