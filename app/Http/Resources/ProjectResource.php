<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'client_name' => $this->client_name,
            'value_amount' => (float) $this->value_amount,
            'scope' => $this->scope,
            'objective' => $this->objective,
            'division_owner_id' => $this->division_owner_id,
            'division_owner' => $this->whenLoaded('divisionOwner', function () {
                return [
                    'id' => $this->divisionOwner->id,
                    'name' => $this->divisionOwner->name,
                    'email' => $this->divisionOwner->email,
                ];
            }),
            'start_planned' => optional($this->start_planned)->format('Y-m-d'),
            'end_planned' => optional($this->end_planned)->format('Y-m-d'),
            'status' => $this->status,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}

