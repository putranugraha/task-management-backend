<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectBaselineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'baseline_name' => $this->baseline_name,
            'taken_at' => optional($this->taken_at)->toDateTimeString(),
            'note' => $this->note,
            'start_planned_base' => optional($this->start_planned_base)->toDateString(),
            'end_planned_base' => optional($this->end_planned_base)->toDateString(),
            'value_amount_base' => $this->value_amount_base !== null ? (float) $this->value_amount_base : null,
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

