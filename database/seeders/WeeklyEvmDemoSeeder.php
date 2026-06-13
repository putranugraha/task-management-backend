<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Division;
use App\Models\KpiSnapshot;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\ReportingPeriod;
use App\Models\StatusHistory;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskBaseline;
use App\Models\TaskCostEntry;
use App\Models\TaskDependency;
use App\Models\TaskProgressEntry;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class WeeklyEvmDemoSeeder extends Seeder
{
    private const PROJECT_NAME = 'Demo EVM Manual 1 Minggu';

    private array $users = [];

    public function run(): void
    {
        DB::transaction(function () {
            if (Role::whereIn('name', ['Admin', 'Manager', 'Member'])->count() < 3) {
                $this->call(RolePermissionSeeder::class);
            }

            $this->prepareUsers();
            $this->resetProject();
            $this->seedProject();
        });
    }

    private function prepareUsers(): void
    {
        $division = Division::updateOrCreate(
            ['code' => 'WEEK-EVM'],
            [
                'name' => 'Weekly EVM Demo',
                'description' => 'Tim demo untuk simulasi EVM cost dan effort selama satu minggu.',
                'status' => 'Aktif',
            ]
        );

        $this->users['owner'] = $this->user('Owner Weekly EVM', 'owner.weekly.evm@example.com', 'Project Owner', $division->id, 'Admin');
        $this->users['pm'] = $this->user('PM Weekly EVM', 'pm.weekly.evm@example.com', 'Project Manager', $division->id, 'Manager');
        $this->users['dev'] = $this->user('Developer Weekly EVM', 'dev.weekly.evm@example.com', 'Developer', $division->id, 'Member');
        $this->users['qa'] = $this->user('QA Weekly EVM', 'qa.weekly.evm@example.com', 'QA Engineer', $division->id, 'Member');
    }

    private function user(string $name, string $email, string $jobTitle, int $divisionId, string $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password_hash' => 'password',
                'division_id' => $divisionId,
                'job_title' => $jobTitle,
                'is_active' => true,
                'status' => 'Aktif',
            ]
        );

        if (Role::where('name', $role)->exists()) {
            $user->syncRoles([$role]);
        }

        return $user;
    }

    private function resetProject(): void
    {
        Project::withTrashed()
            ->where('name', self::PROJECT_NAME)
            ->get()
            ->each(function (Project $project) {
                $taskIds = Task::withTrashed()->where('project_id', $project->id)->pluck('id');
                $milestoneIds = Milestone::withTrashed()->where('project_id', $project->id)->pluck('id');
                $baselineIds = ProjectBaseline::where('project_id', $project->id)->pluck('id');
                $periodIds = ReportingPeriod::where('project_id', $project->id)->pluck('id');

                if ($taskIds->isNotEmpty()) {
                    TaskDependency::whereIn('task_id', $taskIds)->orWhereIn('depends_on_task_id', $taskIds)->delete();
                    TaskAssignment::whereIn('task_id', $taskIds)->delete();
                    TaskCostEntry::whereIn('task_id', $taskIds)->delete();
                    TaskProgressEntry::whereIn('task_id', $taskIds)->delete();
                    TimeEntry::whereIn('task_id', $taskIds)->delete();
                    StatusHistory::whereIn('task_id', $taskIds)->delete();
                    TaskBaseline::whereIn('task_id', $taskIds)->orWhereIn('baseline_id', $baselineIds)->delete();
                }

                if ($periodIds->isNotEmpty()) {
                    KpiSnapshot::whereIn('period_id', $periodIds)->delete();
                    ReportingPeriod::whereIn('id', $periodIds)->delete();
                }

                if ($baselineIds->isNotEmpty()) {
                    ProjectBaseline::whereIn('id', $baselineIds)->delete();
                }

                Comment::where('entity_type', Project::class)->where('entity_id', $project->id)->delete();
                Milestone::withTrashed()->whereIn('id', $milestoneIds)->forceDelete();
                Task::withTrashed()->whereIn('id', $taskIds)->forceDelete();
                $project->forceDelete();
            });
    }

    private function seedProject(): void
    {
        $project = Project::create([
            'name' => self::PROJECT_NAME,
            'client_name' => 'Internal Demo',
            'value_amount' => 1000000,
            'scope' => 'Demo perhitungan EVM cost dan effort selama satu minggu.',
            'objective' => 'Memudahkan simulasi PV, EV, AC, SPI, dan CPI dengan 3 task sederhana.',
            'division_owner_id' => $this->users['owner']->id,
            'start_planned' => '2026-06-13',
            'end_planned' => '2026-06-19',
            'status' => 'In Progress',
        ]);

        $milestone = Milestone::create([
            'project_id' => $project->id,
            'name' => 'Milestone 1 - Demo EVM Mingguan',
            'due_planned' => '2026-06-19',
            'due_actual' => null,
            'status' => 'In Progress',
        ]);

        $tasks = [
            'T01' => $this->createTask(
                $project,
                $milestone,
                'Analisis kebutuhan dan desain singkat',
                'High',
                '2026-06-13',
                '2026-06-14',
                200000,
                16,
                'pm',
                ['2026-06-13' => 50, '2026-06-14' => 100],
                ['2026-06-13' => 8, '2026-06-14' => 7],
                ['2026-06-13' => 100000, '2026-06-14' => 90000]
            ),
            'T02' => $this->createTask(
                $project,
                $milestone,
                'Implementasi fitur utama',
                'High',
                '2026-06-15',
                '2026-06-17',
                500000,
                24,
                'dev',
                ['2026-06-15' => 30, '2026-06-16' => 70, '2026-06-17' => 100],
                ['2026-06-15' => 7, '2026-06-16' => 10, '2026-06-17' => 8],
                ['2026-06-15' => 150000, '2026-06-16' => 200000, '2026-06-17' => 160000]
            ),
            'T03' => $this->createTask(
                $project,
                $milestone,
                'Testing, revisi, dan handover',
                'Medium',
                '2026-06-18',
                '2026-06-19',
                300000,
                16,
                'qa',
                ['2026-06-18' => 60, '2026-06-19' => 100],
                ['2026-06-18' => 9, '2026-06-19' => 7],
                ['2026-06-18' => 180000, '2026-06-19' => 110000]
            ),
        ];

        $this->createDependency($tasks['T02'], $tasks['T01']);
        $this->createDependency($tasks['T03'], $tasks['T02']);

        $taskCollection = collect($tasks)->values();
        $this->createBaseline($project, $taskCollection);
        $this->createKpiSnapshots($project, $taskCollection);

        Comment::create([
            'entity_type' => Project::class,
            'entity_id' => $project->id,
            'user_id' => $this->users['pm']->id,
            'content' => 'Demo 1 minggu: total budget Rp 1.000.000, planned effort 56 jam, actual cost Rp 990.000.',
        ]);
    }

    private function createTask(
        Project $project,
        Milestone $milestone,
        string $title,
        string $priority,
        string $start,
        string $end,
        int $budget,
        int $estimatedEffort,
        string $assigneeKey,
        array $progressByDate,
        array $hoursByDate,
        array $costByDate
    ): Task {
        $assignee = $this->users[$assigneeKey];
        $endActual = (string) array_key_last($progressByDate);
        $percent = (int) end($progressByDate);

        $task = Task::create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'title' => $title,
            'description' => 'Task demo mingguan untuk melihat PV, EV, actual hours, dan actual cost berdasarkan as-of date.',
            'priority' => $priority,
            'status' => $percent >= 100 ? 'Done' : 'In Progress',
            'start_planned' => $start,
            'end_planned' => $end,
            'duration_planned' => $this->duration($start, $end),
            'start_actual' => $start,
            'end_actual' => $percent >= 100 ? $endActual : null,
            'duration_actual' => $this->duration($start, $endActual),
            'percent_complete' => $percent,
            'budget_cost' => $budget,
        ]);

        TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $assignee->id,
            'role_on_task' => $this->roleOnTask($assigneeKey),
            'estimated_effort_hours' => $estimatedEffort,
            'assigned_at' => Carbon::parse($start)->startOfDay(),
        ]);

        StatusHistory::create([
            'task_id' => $task->id,
            'from_status' => 'To Do',
            'to_status' => 'In Progress',
            'changed_by' => $assignee->id,
            'note' => 'Task mulai dikerjakan untuk demo EVM mingguan.',
            'created_at' => Carbon::parse($start)->setTime(9, 0),
            'updated_at' => Carbon::parse($start)->setTime(9, 0),
        ]);

        StatusHistory::create([
            'task_id' => $task->id,
            'from_status' => 'In Progress',
            'to_status' => 'Done',
            'changed_by' => $this->users['pm']->id,
            'note' => 'Task selesai untuk demo EVM mingguan.',
            'created_at' => Carbon::parse($endActual)->setTime(16, 0),
            'updated_at' => Carbon::parse($endActual)->setTime(16, 0),
        ]);

        foreach ($progressByDate as $date => $percentComplete) {
            TaskProgressEntry::create([
                'task_id' => $task->id,
                'progress_date' => $date,
                'percent_complete' => (int) $percentComplete,
                'changed_by' => $assignee->id,
            ]);
        }

        foreach ($hoursByDate as $date => $hours) {
            TimeEntry::create([
                'task_id' => $task->id,
                'user_id' => $assignee->id,
                'date' => $date,
                'hours' => $hours,
                'note' => 'Actual hours demo mingguan.',
            ]);
        }

        foreach ($costByDate as $date => $amount) {
            TaskCostEntry::create([
                'task_id' => $task->id,
                'incurred_on' => $date,
                'amount' => $amount,
                'category' => 'Weekly Demo Cost',
                'note' => 'Actual cost demo mingguan.',
            ]);
        }

        return $task;
    }

    private function createDependency(Task $task, Task $dependsOn): void
    {
        TaskDependency::create([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOn->id,
            'type' => 'FS',
            'lag_days' => 0,
        ]);
    }

    private function createBaseline(Project $project, $tasks): void
    {
        $baseline = ProjectBaseline::create([
            'project_id' => $project->id,
            'baseline_name' => 'Baseline Demo Manual 1 Minggu',
            'taken_at' => Carbon::parse('2026-06-13')->setTime(8, 0),
            'note' => 'Baseline untuk latihan EVM cost dan effort satu minggu.',
            'start_planned_base' => $project->start_planned,
            'end_planned_base' => $project->end_planned,
        ]);

        foreach ($tasks as $task) {
            TaskBaseline::create([
                'baseline_id' => $baseline->id,
                'task_id' => $task->id,
                'start_planned_base' => $task->start_planned,
                'end_planned_base' => $task->end_planned,
                'duration_planned_base' => $task->duration_planned,
                'weight' => round(100 / max(1, $tasks->count()), 2),
                'planned_effort_hours' => (float) TaskAssignment::where('task_id', $task->id)->sum('estimated_effort_hours'),
                'budget_cost_base' => $task->budget_cost,
            ]);
        }
    }

    private function createKpiSnapshots(Project $project, $tasks): void
    {
        foreach (['2026-06-14', '2026-06-17', '2026-06-19'] as $date) {
            $period = ReportingPeriod::create([
                'project_id' => $project->id,
                'period_date' => $date,
                'note' => 'Snapshot KPI demo mingguan per '.$date.'.',
            ]);

            $asOf = Carbon::parse($date)->endOfDay();
            $doneTasks = $tasks->filter(fn (Task $task) => $task->end_actual && Carbon::parse($task->end_actual)->endOfDay()->lte($asOf));

            KpiSnapshot::create([
                'project_id' => $project->id,
                'period_id' => $period->id,
                'tasks_total' => $tasks->count(),
                'tasks_done' => $doneTasks->count(),
                'overdue_count' => 0,
                'avg_cycle_time_days' => round((float) $doneTasks->avg(fn (Task $task) => $this->duration($task->start_actual, $task->end_actual)), 2),
            ]);
        }
    }

    private function roleOnTask(string $assigneeKey): string
    {
        return match ($assigneeKey) {
            'pm' => 'Project Manager',
            'qa' => 'QA Engineer',
            default => 'Developer',
        };
    }

    private function duration($start, $end): int
    {
        if (!$start || !$end) {
            return 0;
        }

        return Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
    }
}
