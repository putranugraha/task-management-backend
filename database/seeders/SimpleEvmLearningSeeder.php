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

class SimpleEvmLearningSeeder extends Seeder
{
    private const PROJECT_NAME = 'Belajar EVM Sederhana 1 Juta';

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
            ['code' => 'EVM-LEARN'],
            [
                'name' => 'EVM Learning',
                'description' => 'Tim kecil untuk belajar perhitungan EVM, effort, dan KPI.',
                'status' => 'Aktif',
            ]
        );

        $this->users['owner'] = $this->user('Owner EVM', 'owner.evm@example.com', 'Project Owner', $division->id, 'Admin');
        $this->users['pm'] = $this->user('PM EVM', 'pm.evm@example.com', 'Project Manager', $division->id, 'Manager');
        $this->users['dev'] = $this->user('Dev EVM', 'dev.evm@example.com', 'Developer', $division->id, 'Member');
        $this->users['qa'] = $this->user('QA EVM', 'qa.evm@example.com', 'QA Engineer', $division->id, 'Member');
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
            'client_name' => 'Demo Belajar',
            'value_amount' => 1000000,
            'scope' => 'Project kecil untuk belajar EVM cost, EVM effort, dan KPI dengan angka sederhana.',
            'objective' => 'Membuat contoh 1 project bernilai Rp 1.000.000 dengan 2 milestone dan 6 task.',
            'division_owner_id' => $this->users['owner']->id,
            'start_planned' => '2026-06-01',
            'end_planned' => '2026-06-12',
            'status' => 'Completed',
        ]);

        $milestoneOne = Milestone::create([
            'project_id' => $project->id,
            'name' => 'Milestone 1 - Planning',
            'due_planned' => '2026-06-06',
            'due_actual' => '2026-06-06',
            'status' => 'Completed',
        ]);

        $milestoneTwo = Milestone::create([
            'project_id' => $project->id,
            'name' => 'Milestone 2 - Delivery',
            'due_planned' => '2026-06-12',
            'due_actual' => '2026-06-12',
            'status' => 'Completed',
        ]);

        $tasks = [
            'T01' => $this->createTask($project, $milestoneOne, 'T01 - Kumpulkan kebutuhan', 'High', '2026-06-01', '2026-06-02', 100000, 10, 'pm', ['2026-06-01' => 60, '2026-06-02' => 100], 9, 90000),
            'T02' => $this->createTask($project, $milestoneOne, 'T02 - Buat sitemap sederhana', 'High', '2026-06-03', '2026-06-04', 150000, 15, 'dev', ['2026-06-03' => 60, '2026-06-04' => 100], 14, 140000),
            'T03' => $this->createTask($project, $milestoneOne, 'T03 - Buat desain awal', 'Medium', '2026-06-05', '2026-06-06', 150000, 15, 'dev', ['2026-06-05' => 50, '2026-06-06' => 100], 15, 150000),
            'T04' => $this->createTask($project, $milestoneTwo, 'T04 - Implementasi halaman utama', 'High', '2026-06-07', '2026-06-08', 200000, 20, 'dev', ['2026-06-07' => 60, '2026-06-08' => 100], 19, 190000),
            'T05' => $this->createTask($project, $milestoneTwo, 'T05 - Integrasi data task', 'High', '2026-06-09', '2026-06-10', 200000, 20, 'dev', ['2026-06-09' => 60, '2026-06-10' => 100], 20, 200000),
            'T06' => $this->createTask($project, $milestoneTwo, 'T06 - Testing dan handover', 'Medium', '2026-06-11', '2026-06-12', 200000, 20, 'qa', ['2026-06-11' => 60, '2026-06-12' => 100], 20.5, 210000),
        ];

        $this->createDependency($tasks['T02'], $tasks['T01']);
        $this->createDependency($tasks['T03'], $tasks['T02']);
        $this->createDependency($tasks['T04'], $tasks['T03']);
        $this->createDependency($tasks['T05'], $tasks['T04']);
        $this->createDependency($tasks['T06'], $tasks['T05']);

        $taskCollection = collect($tasks)->values();
        $this->createBaseline($project, $taskCollection);
        $this->createKpiSnapshots($project, $taskCollection);

        Comment::create([
            'entity_type' => Project::class,
            'entity_id' => $project->id,
            'user_id' => $this->users['pm']->id,
            'content' => 'Contoh sederhana: total BAC Rp 1.000.000, total planned effort 100 jam, 6 task selesai dalam 12 hari.',
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
        array $progressHistory,
        float $actualHours,
        int $actualCost
    ): Task {
        $assignee = $this->users[$assigneeKey];
        $completeDate = (string) array_key_last($progressHistory);
        $currentPercent = (int) end($progressHistory);

        $task = Task::create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'title' => $title,
            'description' => 'Task sederhana untuk membaca rumus: PV dari jadwal, EV dari percent, AC dari biaya aktual.',
            'priority' => $priority,
            'status' => $currentPercent >= 100 ? 'Done' : 'In Progress',
            'start_planned' => $start,
            'end_planned' => $end,
            'duration_planned' => $this->duration($start, $end),
            'start_actual' => $start,
            'end_actual' => $completeDate,
            'duration_actual' => $this->duration($start, $completeDate),
            'percent_complete' => $currentPercent,
            'budget_cost' => $budget,
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
            'note' => 'Task mulai dikerjakan.',
            'created_at' => Carbon::parse($start)->setTime(9, 0),
            'updated_at' => Carbon::parse($start)->setTime(9, 0),
        ]);

        StatusHistory::create([
            'task_id' => $task->id,
            'from_status' => 'In Progress',
            'to_status' => 'Done',
            'changed_by' => $this->users['pm']->id,
            'note' => 'Task selesai '.$currentPercent.' persen.',
            'created_at' => Carbon::parse($completeDate)->setTime(16, 0),
            'updated_at' => Carbon::parse($completeDate)->setTime(16, 0),
        ]);

        $previousPercent = 0;
        foreach ($progressHistory as $progressDate => $percent) {
            $percent = (int) $percent;
            $delta = max(0, $percent - $previousPercent);

            TaskProgressEntry::create([
                'task_id' => $task->id,
                'progress_date' => $progressDate,
                'percent_complete' => $percent,
                'changed_by' => $assignee->id,
            ]);

            if ($delta > 0) {
                TimeEntry::create([
                    'task_id' => $task->id,
                    'user_id' => $assignee->id,
                    'date' => $progressDate,
                    'hours' => round($actualHours * ($delta / max(1, $currentPercent)), 2),
                    'note' => 'Actual effort sesuai kenaikan progress dari '.$previousPercent.'% ke '.$percent.'%.',
                ]);

                TaskCostEntry::create([
                    'task_id' => $task->id,
                    'incurred_on' => $progressDate,
                    'amount' => round($actualCost * ($delta / max(1, $currentPercent)), 2),
                    'category' => 'Learning Cost',
                    'note' => 'Actual cost sesuai kenaikan progress dari '.$previousPercent.'% ke '.$percent.'%.',
                ]);
            }

            $previousPercent = $percent;
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

    private function createBaseline(Project $project, $tasks): void
    {
        $baseline = ProjectBaseline::create([
            'project_id' => $project->id,
            'baseline_name' => 'Baseline Belajar EVM',
            'taken_at' => Carbon::parse('2026-06-01')->setTime(8, 0),
            'note' => 'Baseline sederhana: 6 task, BAC Rp 1.000.000, planned effort 100 jam.',
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
                'weight' => round(100 / 6, 2),
                'planned_effort_hours' => (float) TaskAssignment::where('task_id', $task->id)->sum('estimated_effort_hours'),
                'budget_cost_base' => $task->budget_cost,
            ]);
        }
    }

    private function createKpiSnapshots(Project $project, $tasks): void
    {
        $this->createKpiSnapshot($project, $tasks, '2026-06-06');
        $this->createKpiSnapshot($project, $tasks, '2026-06-12');
    }

    private function createKpiSnapshot(Project $project, $tasks, string $date): void
    {
        $period = ReportingPeriod::create([
            'project_id' => $project->id,
            'period_date' => $date,
            'note' => 'Snapshot KPI belajar EVM per '.$date.'.',
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
