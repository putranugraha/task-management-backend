<?php

namespace Database\Seeders;

use App\Models\Attachment;
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
use App\Notifications\TaskActivityNotification;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class FocusedProjectAuditSeeder extends Seeder
{
    private const PROJECT_NAME = 'Audit EVM KPI Notifications Demo';
    private const AS_OF_DATE = '2026-05-15';

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
            ['code' => 'AUDIT'],
            [
                'name' => 'Audit Demo',
                'description' => 'Tim demo kecil untuk audit EVM, KPI, baseline, dan notification.',
                'status' => 'Aktif',
            ]
        );

        $this->users['admin'] = $this->user('Audit Admin', 'audit.admin@example.com', 'Administrator', $division->id, 'Admin');
        $this->users['manager'] = $this->user('Audit Manager', 'audit.manager@example.com', 'Project Manager', $division->id, 'Manager');
        $this->users['dev'] = $this->user('Audit Developer', 'audit.dev@example.com', 'Software Engineer', $division->id, 'Member');
        $this->users['qa'] = $this->user('Audit QA', 'audit.qa@example.com', 'QA Engineer', $division->id, 'Member');
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
                    $this->deleteTaskNotifications($taskIds->all());
                    TaskDependency::whereIn('task_id', $taskIds)->orWhereIn('depends_on_task_id', $taskIds)->delete();
                    TaskAssignment::whereIn('task_id', $taskIds)->delete();
                    TaskCostEntry::whereIn('task_id', $taskIds)->delete();
                    TaskProgressEntry::whereIn('task_id', $taskIds)->delete();
                    TimeEntry::whereIn('task_id', $taskIds)->delete();
                    StatusHistory::whereIn('task_id', $taskIds)->delete();
                    TaskBaseline::whereIn('task_id', $taskIds)->orWhereIn('baseline_id', $baselineIds)->delete();
                    Comment::where('entity_type', Task::class)->whereIn('entity_id', $taskIds)->delete();
                    Attachment::where('entity_type', Task::class)->whereIn('entity_id', $taskIds)->delete();
                }

                if ($baselineIds->isNotEmpty()) {
                    ProjectBaseline::whereIn('id', $baselineIds)->delete();
                }

                if ($periodIds->isNotEmpty()) {
                    KpiSnapshot::whereIn('period_id', $periodIds)->delete();
                    ReportingPeriod::whereIn('id', $periodIds)->delete();
                }

                Comment::where('entity_type', Project::class)->where('entity_id', $project->id)->delete();
                Attachment::where('entity_type', Project::class)->where('entity_id', $project->id)->delete();
                Milestone::withTrashed()->whereIn('id', $milestoneIds)->forceDelete();
                Task::withTrashed()->whereIn('id', $taskIds)->forceDelete();
                $project->forceDelete();
            });
    }

    private function seedProject(): void
    {
        $project = Project::create([
            'name' => self::PROJECT_NAME,
            'client_name' => 'Audit Internal',
            'value_amount' => 10000000,
            'scope' => 'Project demo kecil untuk memvalidasi baseline, EVM cost, EVM effort, KPI snapshot, Gantt, dan notification.',
            'objective' => 'Menyediakan data kecil dengan angka expected yang mudah dicek manual.',
            'division_owner_id' => $this->users['admin']->id,
            'start_planned' => '2026-05-05',
            'end_planned' => '2026-06-05',
            'status' => 'In Progress',
        ]);

        $milestoneOne = Milestone::create([
            'project_id' => $project->id,
            'name' => 'Planning & Design',
            'due_planned' => '2026-05-16',
            'due_actual' => null,
            'status' => 'In Progress',
        ]);

        $milestoneTwo = Milestone::create([
            'project_id' => $project->id,
            'name' => 'Build & Release',
            'due_planned' => '2026-06-05',
            'due_actual' => null,
            'status' => 'Planned',
        ]);

        $tasks = [
            'AUD-T01' => $this->task($project, $milestoneOne, 'Requirement audit baseline', 'High', 'Done', '2026-05-05', '2026-05-07', '2026-05-05', '2026-05-07', 100, 1000000, 'manager', 18, 16, 900000),
            'AUD-T02' => $this->task($project, $milestoneOne, 'UI design audit dashboard', 'High', 'In Progress', '2026-05-08', '2026-05-14', '2026-05-08', null, 80, 2000000, 'dev', 42, 32, 1400000),
            'AUD-T03' => $this->task($project, $milestoneTwo, 'Backend API audit data', 'High', 'In Progress', '2026-05-15', '2026-05-24', '2026-05-15', null, 10, 2500000, 'dev', 60, 5, 300000),
            'AUD-T04' => $this->task($project, $milestoneTwo, 'Frontend integration audit', 'Medium', 'To Do', '2026-05-20', '2026-05-29', null, null, 0, 2000000, 'qa', 60, 0, 0),
            'AUD-T05' => $this->task($project, $milestoneTwo, 'Testing and handover audit', 'Medium', 'To Do', '2026-05-30', '2026-06-05', null, null, 0, 2500000, 'qa', 42, 0, 0),
        ];

        TaskDependency::create([
            'task_id' => $tasks['AUD-T02']->id,
            'depends_on_task_id' => $tasks['AUD-T01']->id,
            'type' => 'FS',
            'lag_days' => 0,
        ]);
        TaskDependency::create([
            'task_id' => $tasks['AUD-T03']->id,
            'depends_on_task_id' => $tasks['AUD-T02']->id,
            'type' => 'FS',
            'lag_days' => 0,
        ]);
        TaskDependency::create([
            'task_id' => $tasks['AUD-T04']->id,
            'depends_on_task_id' => $tasks['AUD-T03']->id,
            'type' => 'SS',
            'lag_days' => 2,
        ]);
        TaskDependency::create([
            'task_id' => $tasks['AUD-T05']->id,
            'depends_on_task_id' => $tasks['AUD-T04']->id,
            'type' => 'FS',
            'lag_days' => 0,
        ]);

        $this->seedBaseline($project, collect($tasks)->values());
        $this->seedKpi($project, collect($tasks)->values());
    }

    private function task(
        Project $project,
        Milestone $milestone,
        string $title,
        string $priority,
        string $status,
        string $startPlanned,
        string $endPlanned,
        ?string $startActual,
        ?string $endActual,
        int $percent,
        float $budget,
        string $assigneeKey,
        int $plannedEffort,
        float $actualHours,
        float $actualCost
    ): Task {
        $task = Task::create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'title' => $title,
            'description' => 'Task demo audit untuk validasi perhitungan EVM/KPI.',
            'priority' => $priority,
            'status' => $status,
            'start_planned' => $startPlanned,
            'end_planned' => $endPlanned,
            'duration_planned' => $this->duration($startPlanned, $endPlanned),
            'start_actual' => $startActual,
            'end_actual' => $endActual,
            'duration_actual' => $startActual ? $this->duration($startActual, $endActual ?? self::AS_OF_DATE) : null,
            'percent_complete' => $percent,
            'budget_cost' => $budget,
        ]);
        $task->forceFill([
            'created_at' => Carbon::parse('2026-05-05')->setTime(8, 0),
            'updated_at' => Carbon::parse(self::AS_OF_DATE)->setTime(17, 0),
        ])->save();

        $assignee = $this->users[$assigneeKey] ?? $this->users['dev'];
        $role = match ($assigneeKey) {
            'manager' => 'Project Manager',
            'qa' => 'QA Engineer',
            default => 'Software Engineer',
        };

        TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $assignee->id,
            'role_on_task' => $role,
            'estimated_effort_hours' => $plannedEffort,
            'assigned_at' => Carbon::parse($startPlanned)->startOfDay(),
        ]);

        $this->notifyAssignee($task, $assignee, $role);
        $this->seedTaskProgressAndHistory($task, $assignee, $startActual, $endActual, $status, $percent);

        if ($actualHours > 0) {
            TimeEntry::create([
                'task_id' => $task->id,
                'user_id' => $assignee->id,
                'date' => self::AS_OF_DATE,
                'hours' => $actualHours,
                'note' => 'Actual effort demo sampai tanggal audit.',
            ]);
        }

        if ($actualCost > 0) {
            TaskCostEntry::create([
                'task_id' => $task->id,
                'incurred_on' => self::AS_OF_DATE,
                'amount' => $actualCost,
                'category' => 'Demo Actual Cost',
                'note' => 'Actual cost demo sampai tanggal audit.',
            ]);
        }

        return $task;
    }

    private function seedTaskProgressAndHistory(Task $task, User $assignee, ?string $startActual, ?string $endActual, string $status, int $percent): void
    {
        if ($startActual) {
            StatusHistory::create([
                'task_id' => $task->id,
                'from_status' => 'To Do',
                'to_status' => $status === 'Done' ? 'In Progress' : $status,
                'changed_by' => $assignee->id,
                'note' => 'Task mulai dikerjakan pada data audit.',
                'created_at' => Carbon::parse($startActual)->setTime(9, 0),
                'updated_at' => Carbon::parse($startActual)->setTime(9, 0),
            ]);
        }

        if ($status === 'Done' && $endActual) {
            StatusHistory::create([
                'task_id' => $task->id,
                'from_status' => 'In Progress',
                'to_status' => 'Done',
                'changed_by' => $this->users['manager']->id,
                'note' => 'Task selesai pada data audit.',
                'created_at' => Carbon::parse($endActual)->setTime(16, 0),
                'updated_at' => Carbon::parse($endActual)->setTime(16, 0),
            ]);
        }

        TaskProgressEntry::create([
            'task_id' => $task->id,
            'progress_date' => self::AS_OF_DATE,
            'percent_complete' => $percent,
            'changed_by' => $assignee->id,
        ]);
    }

    private function seedBaseline(Project $project, $tasks): void
    {
        $baseline = ProjectBaseline::create([
            'project_id' => $project->id,
            'baseline_name' => 'Baseline Audit Awal',
            'taken_at' => Carbon::parse('2026-05-05')->setTime(8, 0),
            'note' => 'Baseline awal untuk audit perhitungan EVM cost dan effort.',
            'start_planned_base' => $project->start_planned,
            'end_planned_base' => $project->end_planned,
            'value_amount_base' => $project->value_amount,
        ]);

        foreach ($tasks as $task) {
            $plannedEffort = (float) TaskAssignment::where('task_id', $task->id)->sum('estimated_effort_hours');

            TaskBaseline::create([
                'baseline_id' => $baseline->id,
                'task_id' => $task->id,
                'start_planned_base' => $task->start_planned,
                'end_planned_base' => $task->end_planned,
                'duration_planned_base' => $task->duration_planned,
                'weight' => 20,
                'planned_effort_hours' => $plannedEffort,
                'budget_cost_base' => $task->budget_cost,
            ]);
        }
    }

    private function seedKpi(Project $project, $tasks): void
    {
        $period = ReportingPeriod::create([
            'project_id' => $project->id,
            'period_date' => self::AS_OF_DATE,
            'note' => 'KPI snapshot audit per 2026-05-15.',
        ]);

        $doneTasks = $tasks->filter(fn (Task $task) => $task->status === 'Done' && $task->end_actual);
        $avgCycle = $doneTasks
            ->avg(fn (Task $task) => Carbon::parse($task->start_actual)->diffInDays(Carbon::parse($task->end_actual))) ?? 0;

        KpiSnapshot::create([
            'project_id' => $project->id,
            'period_id' => $period->id,
            'tasks_total' => 5,
            'tasks_done' => 1,
            'overdue_count' => 1,
            'avg_cycle_time_days' => round((float) $avgCycle, 2),
        ]);
    }

    private function notifyAssignee(Task $task, User $assignee, string $roleOnTask): void
    {
        $assignee->notify(new TaskActivityNotification('task_assigned', [
            'task_id' => $task->id,
            'task_title' => $task->title,
            'entity_type' => 'Task',
            'entity_id' => $task->id,
            'actor_id' => null,
            'actor_name' => 'Focused Seeder',
            'message' => 'Anda ditugaskan pada task '.$task->title.' sebagai '.$roleOnTask,
        ]));
    }

    /**
     * @param array<int> $taskIds
     */
    private function deleteTaskNotifications(array $taskIds): void
    {
        $taskIdSet = array_fill_keys(array_map('intval', $taskIds), true);

        DatabaseNotification::query()
            ->where('type', TaskActivityNotification::class)
            ->get()
            ->each(function (DatabaseNotification $notification) use ($taskIdSet) {
                $payload = $notification->data ?? [];
                $taskId = (int) ($payload['task_id'] ?? 0);
                if ($taskId > 0 && isset($taskIdSet[$taskId])) {
                    $notification->delete();
                }
            });
    }

    private function duration($start, $end): int
    {
        if (!$start || !$end) {
            return 0;
        }

        return Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
    }
}
