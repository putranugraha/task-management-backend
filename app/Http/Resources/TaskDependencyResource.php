<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskDependencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'depends_on_task_id' => $this->depends_on_task_id,
            'type' => $this->type,
            'lag_days' => $this->lag_days,
            'task' => $this->whenLoaded('task', function () {
                return [
                    'id' => $this->task->id,
                    'title' => $this->task->title,
                ];
            }),
            'depends_on' => $this->whenLoaded('dependsOn', function () {
                return [
                    'id' => $this->dependsOn->id,
                    'title' => $this->dependsOn->title,
                ];
            }),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}

