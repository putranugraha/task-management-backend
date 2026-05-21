<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userIds = User::pluck('id');

        // If there are no users yet, create one owner to attach projects
        if ($userIds->isEmpty()) {
            $owner = User::factory()->create();
            $userIds = collect([$owner->id]);
        }

        Project::factory()
            ->count(10)
            ->state(function () use ($userIds) {
                return [
                    'division_owner_id' => $userIds->random(),
                ];
            })
            ->create();
    }
}

