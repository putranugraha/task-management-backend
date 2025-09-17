<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Comment;
use App\Models\Task;
use App\Models\Project;
use App\Models\Milestone;
use App\Models\User;

class CommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $users = User::factory()->count(3)->create();
        }

        $tasks = Task::all();
        if ($tasks->isEmpty()) {
            $tasks = Task::factory()->count(5)->create();
        }

        $projects = Project::all();
        if ($projects->isEmpty()) {
            $projects = Project::factory()->count(3)->create();
        }

        $milestones = Milestone::all();
        if ($milestones->isEmpty()) {
            $milestones = Milestone::factory()->count(3)->create();
        }

        // Helper to create comments for a collection of entities
        $makeComments = function ($collection, string $type) use ($users) {
            foreach ($collection as $entity) {
                $count = fake()->numberBetween(1, 3);
                for ($i = 0; $i < $count; $i++) {
                    Comment::create([
                        'entity_type' => $type,
                        'entity_id' => $entity->id,
                        'user_id' => $users->random()->id,
                        'content' => fake()->sentences(fake()->numberBetween(1, 3), true),
                        'created_at' => now()->subDays(fake()->numberBetween(0, 30))->startOfDay()->addHours(fake()->numberBetween(8, 18)),
                        'updated_at' => now(),
                    ]);
                }
            }
        };

        // Use morph map aliases instead of FQCN for entity_type
        $makeComments($projects, 'Project');
        $makeComments($tasks, 'Task');
        $makeComments($milestones, 'Milestone');
    }
}
