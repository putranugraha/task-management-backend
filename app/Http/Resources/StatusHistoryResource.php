<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'changed_by' => $this->changed_by,
            'note' => $this->note,
            'task' => $this->whenLoaded('task', function () {
                return [
                    'id' => $this->task->id,
                    'title' => $this->task->title,
                ];
            }),
            'changer' => $this->whenLoaded('changer', function () {
                return [
                    'id' => $this->changer->id,
                    'name' => $this->changer->name,
                ];
            }),
            // Use ISO 8601 with timezone offset so FE date parsing is consistent across browsers/timezones.
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
