<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ReportingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ReportingPeriod>
 */
class ReportingPeriodFactory extends Factory
{
    protected $model = ReportingPeriod::class;

    public function definition(): array
    {
        $periodDate = fake()->dateTimeBetween('-6 months', '+6 months');

        return [
            'project_id' => Project::factory(),
            'period_date' => $periodDate->format('Y-m-d'),
            'note' => fake()->boolean(40) ? fake()->sentence() : null,
        ];
    }
}

