<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'job_title' => $this->job_title,
            'is_active' => (bool) $this->is_active,
            'status' => $this->status,
            'last_login_at' => optional($this->last_login_at)->toDateTimeString(),
            'email_verified_at' => optional($this->email_verified_at)->toDateTimeString(),
            'division' => $this->whenLoaded('division', function () {
                return [
                    'id' => $this->division->id,
                    'code' => $this->division->code,
                    'name' => $this->division->name,
                ];
            }),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
            'role' => $this->roles->first()->name ?? null,
        ];
    }
}
