<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'role_on_task' => $this->role_on_task,
            'estimated_effort_hours' => $this->estimated_effort_hours,
            'assigned_at' => optional($this->assigned_at)->toDateTimeString(),
            'task' => $this->whenLoaded('task', function () {
                return [
                    'id' => $this->task->id,
                    'title' => $this->task->title,
                ];
            }),
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

