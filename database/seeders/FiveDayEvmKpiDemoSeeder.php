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
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class FiveDayEvmKpiDemoSeeder extends Seeder
{
    private const PROJECT_NAME = 'Demo EVM 5 Hari 500K';

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
            ['code' => 'EVM-5D'],
            [
                'name' => 'EVM 5 Hari Demo',
                'description' => 'Tim demo kecil untuk latihan EVM effort, EVM cost, dan KPI snapshot selama 5 hari.',
                'status' => 'Aktif',
            ]
        );

        $this->users['owner'] = $this->user('Owner EVM 5 Hari', 'owner.evm5@example.com', 'Project Owner', $division->id, 'Admin');
        $this->users['pm'] = $this->user('PM EVM 5 Hari', 'pm.evm5@example.com', 'Project Manager', $division->id, 'Manager');
        $this->users['dev'] = $this->user('Dev EVM 5 Hari', 'dev.evm5@example.com', 'Developer', $division->id, 'Member');
        $this->users['qa'] = $this->user('QA EVM 5 Hari', 'qa.evm5@example.com', 'QA Engineer', $division->id, 'Member');
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
                    $this->deleteTaskNotifications($taskIds->all());
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
            'client_name' => 'Demo Sidang',
            'value_amount' => 500000,
            'scope' => 'Project latihan 5 hari untuk membaca EVM effort, EVM cost, dan KPI snapshot dengan 3 task.',
            'objective' => 'Menyediakan data kecil dengan total budget Rp 500.000 dan planned effort 56 jam agar rumus PV, EV, AC, SPI, CPI, dan KPI mudah dijelaskan.',
            'division_owner_id' => $this->users['owner']->id,
            'start_planned' => '2026-06-13',
            'end_planned' => '2026-06-17',
            'status' => 'Completed',
            'created_at' => '2026-06-13 07:00:00',
            'updated_at' => '2026-06-17 17:00:00',
        ]);

        $milestone = Milestone::create([
            'project_id' => $project->id,
            'name' => 'Milestone 1 - Simulasi EVM 5 Hari',
            'due_planned' => '2026-06-17',
            'due_actual' => '2026-06-17',
            'status' => 'Completed',
            'created_at' => '2026-06-13 07:05:00',
            'updated_at' => '2026-06-17 17:00:00',
        ]);

        $tasks = [
            'T01' => $this->createTask(
                project: $project,
                milestone: $milestone,
                title: 'T01 - Analisis kebutuhan singkat',
                priority: 'High',
                start: '2026-06-13',
                end: '2026-06-14',
                budget: 150000,
                plannedEffort: 16,
                assigneeKey: 'pm',
                progressByDate: ['2026-06-13' => 50, '2026-06-14' => 100],
                hoursByDate: ['2026-06-13' => 7, '2026-06-14' => 8],
                costByDate: ['2026-06-13' => 70000, '2026-06-14' => 70000],
                createdAt: '2026-06-13 07:10:00'
            ),
            'T02' => $this->createTask(
                project: $project,
                milestone: $milestone,
                title: 'T02 - Implementasi fitur inti',
                priority: 'High',
                start: '2026-06-14',
                end: '2026-06-16',
                budget: 250000,
                plannedEffort: 24,
                assigneeKey: 'dev',
                progressByDate: ['2026-06-13' => 0, '2026-06-14' => 20, '2026-06-15' => 60, '2026-06-16' => 100],
                hoursByDate: ['2026-06-14' => 5, '2026-06-15' => 8, '2026-06-16' => 10],
                costByDate: ['2026-06-14' => 50000, '2026-06-15' => 100000, '2026-06-16' => 90000],
                createdAt: '2026-06-13 07:15:00'
            ),
            'T03' => $this->createTask(
                project: $project,
                milestone: $milestone,
                title: 'T03 - Testing dan validasi demo',
                priority: 'Medium',
                start: '2026-06-16',
                end: '2026-06-17',
                budget: 100000,
                plannedEffort: 16,
                assigneeKey: 'qa',
                progressByDate: ['2026-06-13' => 0, '2026-06-16' => 40, '2026-06-17' => 100],
                hoursByDate: ['2026-06-16' => 7, '2026-06-17' => 8],
                costByDate: ['2026-06-16' => 40000, '2026-06-17' => 55000],
                createdAt: '2026-06-13 07:20:00'
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
            'content' => 'Demo 5 hari: total budget Rp 500.000, planned effort 56 jam, actual cost Rp 475.000, actual hours 53 jam.',
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
        float $plannedEffort,
        string $assigneeKey,
        array $progressByDate,
        array $hoursByDate,
        array $costByDate,
        string $createdAt
    ): Task {
        $assignee = $this->users[$assigneeKey];
        $endActual = (string) array_key_last($progressByDate);
        $percent = (int) end($progressByDate);

        $task = Task::create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'title' => $title,
            'description' => 'Task demo 5 hari untuk membaca EVM. PV dari baseline, EV dari progress history, AC effort dari time entries, AC cost dari cost entries.',
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
            'created_at' => $createdAt,
            'updated_at' => Carbon::parse($endActual)->setTime(17, 0),
        ]);

        TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $assignee->id,
            'role_on_task' => $this->roleOnTask($assigneeKey),
            'estimated_effort_hours' => $plannedEffort,
            'assigned_at' => Carbon::parse($start)->startOfDay(),
        ]);

        StatusHistory::create([
            'task_id' => $task->id,
            'from_status' => 'To Do',
            'to_status' => 'In Progress',
            'changed_by' => $assignee->id,
            'note' => 'Task mulai dikerjakan untuk demo EVM 5 hari.',
            'created_at' => Carbon::parse($start)->setTime(9, 0),
            'updated_at' => Carbon::parse($start)->setTime(9, 0),
        ]);

        StatusHistory::create([
            'task_id' => $task->id,
            'from_status' => 'In Progress',
            'to_status' => 'Done',
            'changed_by' => $this->users['pm']->id,
            'note' => 'Task selesai untuk demo EVM 5 hari.',
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
                'note' => 'Actual hours demo EVM 5 hari.',
            ]);
        }

        foreach ($costByDate as $date => $amount) {
            TaskCostEntry::create([
                'task_id' => $task->id,
                'incurred_on' => $date,
                'amount' => $amount,
                'category' => 'Demo 5 Hari',
                'note' => 'Actual cost demo EVM 5 hari.',
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
            'baseline_name' => 'Baseline Demo EVM 5 Hari',
            'taken_at' => Carbon::parse('2026-06-13')->setTime(8, 0),
            'note' => 'Baseline kecil: 3 task, BAC Rp 500.000, planned effort 56 jam.',
            'start_planned_base' => $project->start_planned,
            'end_planned_base' => $project->end_planned,
            'value_amount_base' => 500000,
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
        foreach (['2026-06-13', '2026-06-14', '2026-06-15', '2026-06-16', '2026-06-17'] as $date) {
            $period = ReportingPeriod::create([
                'project_id' => $project->id,
                'period_date' => $date,
                'note' => 'Snapshot KPI demo EVM 5 hari per '.$date.'.',
            ]);

            $asOf = Carbon::parse($date)->endOfDay();
            $doneTasks = $tasks->filter(fn (Task $task) => $task->end_actual && Carbon::parse($task->end_actual)->endOfDay()->lte($asOf));
            $overdueTasks = $tasks->filter(function (Task $task) use ($asOf) {
                $endPlanned = $task->end_planned ? Carbon::parse($task->end_planned)->endOfDay() : null;
                $doneAt = $task->end_actual ? Carbon::parse($task->end_actual)->endOfDay() : null;

                return $endPlanned
                    && $endPlanned->lt($asOf)
                    && (!$doneAt || $doneAt->gt($asOf));
            });

            KpiSnapshot::create([
                'project_id' => $project->id,
                'period_id' => $period->id,
                'tasks_total' => $tasks->count(),
                'tasks_done' => $doneTasks->count(),
                'overdue_count' => $overdueTasks->count(),
                'avg_cycle_time_days' => round((float) $doneTasks->avg(fn (Task $task) => $this->duration($task->start_actual, $task->end_actual)), 2),
            ]);
        }
    }

    /**
     * @param array<int> $taskIds
     */
    private function deleteTaskNotifications(array $taskIds): void
    {
        $taskIdSet = array_fill_keys(array_map('intval', $taskIds), true);

        DatabaseNotification::query()
            ->get()
            ->each(function (DatabaseNotification $notification) use ($taskIdSet) {
                $payload = $notification->data ?? [];
                $entityType = $payload['entity_type'] ?? null;
                $entityId = (int) ($payload['entity_id'] ?? 0);
                $taskId = (int) ($payload['task_id'] ?? 0);

                $matchesEntity = $entityType === 'Task' && $entityId > 0 && isset($taskIdSet[$entityId]);
                $matchesTaskId = $taskId > 0 && isset($taskIdSet[$taskId]);

                if ($matchesEntity || $matchesTaskId) {
                    $notification->delete();
                }
            });
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
