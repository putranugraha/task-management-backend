<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KpiSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'period_id' => $this->period_id,
            'tasks_total' => $this->tasks_total,
            'tasks_done' => $this->tasks_done,
            'overdue_count' => $this->overdue_count,
            'avg_cycle_time_days' => (float) $this->avg_cycle_time_days,
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                    'status' => $this->project->status,
                ];
            }),
            'reporting_period' => $this->whenLoaded('reportingPeriod', function () {
                return [
                    'id' => $this->reportingPeriod->id,
                    'period_date' => optional($this->reportingPeriod->period_date)->format('Y-m-d'),
                ];
            }),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}

