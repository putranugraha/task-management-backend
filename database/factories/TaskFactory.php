<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        $startPlanned = fake()->dateTimeBetween('-1 month', '+1 month');
        $plannedDays = fake()->numberBetween(1, 30);
        $endPlanned = (clone $startPlanned)->modify("+{$plannedDays} days");

        // 60% chance of having actuals
        $hasActual = fake()->boolean(60);
        $startActual = $hasActual ? (clone $startPlanned)->modify((string) fake()->numberBetween(-5, 10) . ' days') : null;
        $actualDays = $hasActual ? fake()->numberBetween(max(1, $plannedDays - 5), $plannedDays + 10) : null;
        $endActual = $hasActual ? (clone $startActual)->modify("+{$actualDays} days") : null;

        $status = fake()->randomElement(['To Do', 'In Progress', 'Done', 'On Hold', 'Cancelled']);
        $percent = $status === 'Done' ? 100 : ($hasActual ? fake()->numberBetween(10, 95) : fake()->numberBetween(0, 60));

        return [
            'project_id' => Project::factory(),
            'title' => ucfirst(fake()->words(4, true)),
            'description' => fake()->boolean(70) ? fake()->paragraph() : null,
            'priority' => fake()->randomElement(['Low', 'Medium', 'High', 'Critical']),
            'status' => $status,
            'start_planned' => $startPlanned->format('Y-m-d'),
            'end_planned' => $endPlanned->format('Y-m-d'),
            'duration_planned' => $plannedDays,
            'start_actual' => $startActual ? $startActual->format('Y-m-d') : null,
            'end_actual' => $endActual ? $endActual->format('Y-m-d') : null,
            'duration_actual' => $actualDays,
            'percent_complete' => $percent,
        ];
    }
}
