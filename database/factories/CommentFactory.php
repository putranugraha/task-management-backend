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
        $types = [Task::class, Project::class, Milestone::class];
        $type = fake()->randomElement($types);

        $entityFactory = match ($type) {
            Task::class => Task::factory(),
            Project::class => Project::factory(),
            Milestone::class => Milestone::factory(),
        };

        return [
            'entity_type' => $type,
            'entity_id' => $entityFactory,
            'user_id' => User::factory(),
            'content' => fake()->sentences(fake()->numberBetween(1, 3), true),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

