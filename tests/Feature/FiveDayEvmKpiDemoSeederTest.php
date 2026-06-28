<?php

namespace Tests\Feature;

use App\Models\KpiSnapshot;
use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\TaskAssignment;
use App\Models\TaskBaseline;
use App\Services\Contracts\EvmCostServiceInterface;
use App\Services\Contracts\EvmServiceInterface;
use Database\Seeders\FiveDayEvmKpiDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiveDayEvmKpiDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_day_demo_matches_production_evm_and_kpi_formulas(): void
    {
        $this->seed(FiveDayEvmKpiDemoSeeder::class);

        $project = Project::where('name', 'Demo EVM 5 Hari 500K')->firstOrFail();
        $baseline = ProjectBaseline::where('project_id', $project->id)->firstOrFail();

        $this->assertSame(500000.0, (float) $baseline->value_amount_base);
        $this->assertSame(500000.0, (float) TaskBaseline::where('baseline_id', $baseline->id)->sum('budget_cost_base'));
        $this->assertSame(56.0, (float) TaskBaseline::where('baseline_id', $baseline->id)->sum('planned_effort_hours'));
        $this->assertSame(100.0, (float) TaskBaseline::where('baseline_id', $baseline->id)->sum('weight'));

        $roleNames = TaskAssignment::query()
            ->whereHas('task', fn ($query) => $query->where('project_id', $project->id))
            ->pluck('role_on_task')
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['Manager', 'Member', 'Member'], $roleNames);

        $expectedKpi = [
            '2026-06-13' => [3, 0, 0, 0.0],
            '2026-06-14' => [3, 1, 0, 1.0],
            '2026-06-15' => [3, 1, 0, 1.0],
            '2026-06-16' => [3, 2, 0, 1.5],
            '2026-06-17' => [3, 3, 0, 1.33],
        ];

        foreach ($expectedKpi as $date => [$total, $done, $overdue, $avgCycle]) {
            $snapshot = KpiSnapshot::query()
                ->where('project_id', $project->id)
                ->whereHas('reportingPeriod', fn ($query) => $query->whereDate('period_date', $date))
                ->firstOrFail();

            $this->assertSame($total, $snapshot->tasks_total, $date.' tasks_total');
            $this->assertSame($done, $snapshot->tasks_done, $date.' tasks_done');
            $this->assertSame($overdue, $snapshot->overdue_count, $date.' overdue_count');
            $this->assertSame($avgCycle, (float) $snapshot->avg_cycle_time_days, $date.' avg_cycle_time_days');
        }

        $expectedEffort = [
            '2026-06-13' => [8.0, 8.0, 7.0, 1.0, 1.1429],
            '2026-06-14' => [24.0, 20.8, 20.0, 0.8667, 1.04],
            '2026-06-15' => [32.0, 30.4, 28.0, 0.95, 1.0857],
            '2026-06-16' => [48.0, 46.4, 45.0, 0.9667, 1.0311],
            '2026-06-17' => [56.0, 56.0, 53.0, 1.0, 1.0566],
        ];

        $expectedCost = [
            '2026-06-13' => [75000.0, 75000.0, 70000.0, 1.0, 1.0714],
            '2026-06-14' => [233333.33, 200000.0, 190000.0, 0.8571, 1.0526],
            '2026-06-15' => [316666.67, 300000.0, 290000.0, 0.9474, 1.0345],
            '2026-06-16' => [450000.0, 440000.0, 420000.0, 0.9778, 1.0476],
            '2026-06-17' => [500000.0, 500000.0, 475000.0, 1.0, 1.0526],
        ];

        foreach ($expectedEffort as $date => [$pv, $ev, $ac, $spi, $cpi]) {
            $result = app(EvmServiceInterface::class)
                ->computeForProjectDate($project->id, $date, $baseline->id);

            $this->assertSame($pv, $result['pv'], $date.' effort PV');
            $this->assertSame($ev, $result['ev'], $date.' effort EV');
            $this->assertSame($ac, $result['ac'], $date.' effort AC');
            $this->assertSame($spi, $result['spi'], $date.' effort SPI');
            $this->assertSame($cpi, $result['cpi'], $date.' effort CPI');
        }

        foreach ($expectedCost as $date => [$pv, $ev, $ac, $spi, $cpi]) {
            $result = app(EvmCostServiceInterface::class)
                ->computeForProjectDate($project->id, $date, $baseline->id);

            $this->assertSame(500000.0, $result['bac'], $date.' cost BAC');
            $this->assertSame($pv, $result['pv'], $date.' cost PV');
            $this->assertSame($ev, $result['ev'], $date.' cost EV');
            $this->assertSame($ac, $result['ac'], $date.' cost AC');
            $this->assertSame($spi, $result['spi'], $date.' cost SPI');
            $this->assertSame($cpi, $result['cpi'], $date.' cost CPI');
        }
    }
}
