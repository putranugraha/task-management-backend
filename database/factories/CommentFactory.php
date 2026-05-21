<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        // Use morph map aliases for entity_type for consistency
        $aliases = ['Task', 'Project', 'Milestone'];
        $alias = fake()->randomElement($aliases);

        $entityFactory = match ($alias) {
            'Task' => Task::factory(),
            'Project' => Project::factory(),
            'Milestone' => Milestone::factory(),
        };

        return [
            'entity_type' => $alias,
            'entity_id' => $entityFactory,
            'user_id' => User::factory(),
            'content' => fake()->sentences(fake()->numberBetween(1, 3), true),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
