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

class DemoProjectManagementSeeder extends Seeder
{
    private Carbon $today;
    private array $users = [];

    public function run(): void
    {
        // Demo position for thesis presentation: active projects with healthy KPI/EVM through 21 June.
        $this->today = Carbon::create(2026, 6, 21);

        DB::transaction(function () {
            if (Role::whereIn('name', ['Admin', 'Manager', 'Member'])->count() < 3) {
                $this->call(RolePermissionSeeder::class);
            }

            $this->call(MemberSeeder::class);
            $this->prepareUsers();
            $this->resetDemoProjects();

            foreach ($this->projectData() as $projectData) {
                $this->seedProject($projectData);
            }
        });
    }

    private function projectData(): array
    {
        return [
            [
                'name' => 'Website Trip Tirta Yatra Margi Nusa Penida',
                'client_name' => 'Margi Nusa Penida',
                'value_amount' => 13200000,
                'start' => '2026-05-01',
                'end' => '2026-07-31',
                'scope' => 'Website trip tirta yatra Bali dan Nusa Penida dengan katalog paket, itinerary, galeri destinasi, artikel ringan, dan form inquiry WhatsApp.',
                'objective' => 'Meningkatkan kepercayaan calon pemesan dan membuat alur konsultasi paket tirta yatra lebih cepat.',
                'snapshot_dates' => ['2026-05-01', '2026-05-08', '2026-05-15', '2026-05-22', '2026-05-29', '2026-06-05', '2026-06-12', '2026-06-19', '2026-06-21'],
                'milestones' => [
                    [
                        'name' => 'Discovery & Konten',
                        'due_planned' => '2026-05-24',
                        'due_actual' => '2026-05-22',
                        'status' => 'Completed',
                        'tasks' => [
                            $this->task('MNP-T01', 'Analisis kebutuhan paket tirta yatra', 'High', '2026-05-01', '2026-05-07', 1200000, 'gussastra', 42, ['2026-05-03' => 40, '2026-05-06' => 100], 0.90),
                            $this->task('MNP-T02', 'Kurasi konten destinasi dan itinerary', 'High', '2026-05-08', '2026-05-16', 1400000, 'gungaria', 50, ['2026-05-10' => 45, '2026-05-14' => 100], 0.88),
                            $this->task('MNP-T03', 'Sitemap dan struktur halaman trip', 'High', '2026-05-13', '2026-05-24', 1100000, 'wira', 44, ['2026-05-17' => 50, '2026-05-22' => 100], 0.90),
                        ],
                    ],
                    [
                        'name' => 'UI/UX & Development Inti',
                        'due_planned' => '2026-06-25',
                        'due_actual' => null,
                        'status' => 'In Progress',
                        'tasks' => [
                            $this->task('MNP-T04', 'Desain UI homepage dan halaman paket', 'High', '2026-05-25', '2026-06-04', 1700000, 'gungaria', 52, ['2026-05-29' => 60, '2026-06-03' => 100], 0.90),
                            $this->task('MNP-T05', 'Implementasi frontend katalog paket trip', 'High', '2026-06-03', '2026-06-18', 2200000, 'krisna', 60, ['2026-06-08' => 45, '2026-06-14' => 80, '2026-06-20' => 100], 0.92),
                            $this->task('MNP-T06', 'Integrasi form inquiry WhatsApp dan tracking lead', 'Medium', '2026-06-10', '2026-06-25', 2000000, 'mahen', 48, ['2026-06-14' => 45, '2026-06-21' => 90], 0.88),
                        ],
                    ],
                    [
                        'name' => 'QA, SEO & Deployment',
                        'due_planned' => '2026-07-31',
                        'due_actual' => null,
                        'status' => 'Planned',
                        'tasks' => [
                            $this->task('MNP-T07', 'Optimasi SEO paket dan artikel ringan', 'Medium', '2026-06-26', '2026-07-05', 1100000, 'dwiki', 36, [], 0.90),
                            $this->task('MNP-T08', 'Testing responsive mobile dan browser utama', 'Medium', '2026-07-01', '2026-07-14', 1300000, 'divo', 44, [], 0.90),
                            $this->task('MNP-T09', 'Final deployment dan handover admin', 'High', '2026-07-15', '2026-07-31', 1200000, 'madeadi', 40, [], 0.90),
                        ],
                    ],
                ],
                'dependencies' => [
                    ['task' => 'MNP-T02', 'depends_on' => 'MNP-T01', 'type' => 'FS', 'lag' => 0],
                    ['task' => 'MNP-T03', 'depends_on' => 'MNP-T01', 'type' => 'SS', 'lag' => 1],
                    ['task' => 'MNP-T04', 'depends_on' => 'MNP-T03', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MNP-T05', 'depends_on' => 'MNP-T04', 'type' => 'SS', 'lag' => 2],
                    ['task' => 'MNP-T06', 'depends_on' => 'MNP-T05', 'type' => 'FF', 'lag' => 1],
                    ['task' => 'MNP-T07', 'depends_on' => 'MNP-T06', 'type' => 'FS', 'lag' => 0],
                    ['task' => 'MNP-T08', 'depends_on' => 'MNP-T07', 'type' => 'SF', 'lag' => 2],
                    ['task' => 'MNP-T09', 'depends_on' => 'MNP-T08', 'type' => 'FS', 'lag' => 1],
                ],
                'comments' => [
                    'Cut-off 21 Juni menunjukkan progress pengembangan utama lebih cepat dari rencana dan biaya aktual masih efisien.',
                    'Task QA dan deployment sengaja belum dimulai agar dependency FS/SF bisa didemokan saat sidang.',
                ],
            ],
            [
                'name' => 'Website Supplier MBG Sumber Wangi',
                'client_name' => 'Supplier MBG Sumber Wangi',
                'value_amount' => 16500000,
                'start' => '2026-06-01',
                'end' => '2026-08-31',
                'scope' => 'Website profil dapur dan supplier MBG dengan informasi kapasitas produksi, menu, fasilitas dapur, standar kebersihan, dokumentasi legal, dan form kerja sama.',
                'objective' => 'Membuat profil digital yang kredibel untuk presentasi supplier MBG dan komunikasi kerja sama dengan sekolah atau mitra.',
                'snapshot_dates' => ['2026-06-01', '2026-06-08', '2026-06-15', '2026-06-21'],
                'milestones' => [
                    [
                        'name' => 'Requirement & Information Architecture',
                        'due_planned' => '2026-06-21',
                        'due_actual' => '2026-06-21',
                        'status' => 'Completed',
                        'tasks' => [
                            $this->task('MBG-T01', 'Mapping proses dapur MBG dan kebutuhan stakeholder', 'High', '2026-06-01', '2026-06-07', 1600000, 'gussastra', 40, ['2026-06-03' => 45, '2026-06-06' => 100], 0.90),
                            $this->task('MBG-T02', 'Pendataan kapasitas produksi dan alur distribusi', 'High', '2026-06-08', '2026-06-14', 1800000, 'wira', 44, ['2026-06-09' => 50, '2026-06-13' => 100], 0.88),
                            $this->task('MBG-T03', 'Rancang struktur halaman profil supplier MBG', 'High', '2026-06-12', '2026-06-21', 2000000, 'gungaria', 48, ['2026-06-15' => 50, '2026-06-21' => 100], 0.90),
                        ],
                    ],
                    [
                        'name' => 'Development Profil & Menu',
                        'due_planned' => '2026-07-20',
                        'due_actual' => null,
                        'status' => 'In Progress',
                        'tasks' => [
                            $this->task('MBG-T04', 'Desain UI homepage dan standar visual MBG', 'High', '2026-06-18', '2026-06-30', 2400000, 'gungindra', 50, ['2026-06-20' => 35, '2026-06-21' => 45], 0.89),
                            $this->task('MBG-T05', 'Implementasi halaman profil dan fasilitas dapur', 'High', '2026-07-01', '2026-07-15', 2600000, 'gustra', 58, [], 0.90),
                            $this->task('MBG-T06', 'Implementasi katalog menu MBG dan kapasitas produksi', 'High', '2026-07-05', '2026-07-20', 2200000, 'dwiki', 54, [], 0.90),
                        ],
                    ],
                    [
                        'name' => 'Testing, Legalitas & Deployment',
                        'due_planned' => '2026-08-31',
                        'due_actual' => null,
                        'status' => 'Planned',
                        'tasks' => [
                            $this->task('MBG-T07', 'Testing form kerja sama dan performa mobile', 'Medium', '2026-07-21', '2026-08-10', 1700000, 'divo', 48, [], 0.90),
                            $this->task('MBG-T08', 'Final deployment dan training update konten MBG', 'High', '2026-08-11', '2026-08-31', 2200000, 'madeadi', 46, [], 0.90),
                        ],
                    ],
                ],
                'dependencies' => [
                    ['task' => 'MBG-T02', 'depends_on' => 'MBG-T01', 'type' => 'FS', 'lag' => 0],
                    ['task' => 'MBG-T03', 'depends_on' => 'MBG-T02', 'type' => 'SS', 'lag' => 1],
                    ['task' => 'MBG-T04', 'depends_on' => 'MBG-T03', 'type' => 'FF', 'lag' => 0],
                    ['task' => 'MBG-T05', 'depends_on' => 'MBG-T04', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MBG-T06', 'depends_on' => 'MBG-T05', 'type' => 'SS', 'lag' => 2],
                    ['task' => 'MBG-T07', 'depends_on' => 'MBG-T06', 'type' => 'SF', 'lag' => 1],
                    ['task' => 'MBG-T08', 'depends_on' => 'MBG-T07', 'type' => 'FS', 'lag' => 0],
                ],
                'comments' => [
                    'Cut-off 21 Juni menunjukkan requirement selesai dan desain awal berjalan lebih cepat dari rencana.',
                    'Task development setelah 21 Juni belum dimulai sehingga validasi dependency masih mudah didemokan.',
                ],
            ],
        ];
    }

    private function task(
        string $key,
        string $title,
        string $priority,
        string $start,
        string $end,
        int $budget,
        string $assignee,
        int $effort,
        array $progress,
        float $actualCostFactor
    ): array {
        $percent = empty($progress) ? 0 : (int) end($progress);
        $startActual = empty($progress) ? null : (string) array_key_first($progress);
        $endActual = $percent >= 100 ? (string) array_key_last($progress) : null;
        $status = $percent >= 100 ? 'Done' : ($percent > 0 ? 'In Progress' : 'To Do');

        return [
            'key' => $key,
            'title' => $title,
            'priority' => $priority,
            'status' => $status,
            'start_planned' => $start,
            'end_planned' => $end,
            'start_actual' => $startActual,
            'end_actual' => $endActual,
            'percent' => $percent,
            'budget' => $budget,
            'assignee' => $assignee,
            'estimated_effort_hours' => $effort,
            'progress' => $progress,
            'actual_cost_factor' => $actualCostFactor,
        ];
    }

    private function prepareUsers(): void
    {
        $division = Division::updateOrCreate(
            ['code' => 'SW'],
            [
                'name' => 'Software',
                'description' => 'Tim software untuk UI/UX, frontend, backend, integrasi, QA, dan deployment website.',
                'status' => 'Aktif',
            ]
        );

        $mapping = [
            'gussastra' => ['Gus Sastra', 'gussastra@gmail.com', 'Administrator', 'Admin'],
            'wira' => ['Wira', 'wira@gmail.com', 'Project Manager', 'Manager'],
            'gungaria' => ['Gungaria', 'gungaria@gmail.com', 'UI/UX Designer', 'Member'],
            'gungindra' => ['Gungindra', 'gungindra@gmail.com', 'Content Designer', 'Member'],
            'krisna' => ['Krisna', 'krisna@gmail.com', 'Frontend Developer', 'Member'],
            'mahen' => ['Mahen', 'mahen@gmail.com', 'Backend Developer', 'Member'],
            'dwiki' => ['Dwiki', 'dwiki@gmail.com', 'Integration Developer', 'Member'],
            'divo' => ['Divo', 'divo@gmail.com', 'QA Engineer', 'Member'],
            'wisnu' => ['Wisnu', 'wisnu@gmail.com', 'DevOps Engineer', 'Member'],
            'gustra' => ['Gustra', 'gustra@gmail.com', 'Fullstack Developer', 'Member'],
            'madeadi' => ['Madeadi', 'madeadi@gmail.com', 'Documentation & Support', 'Member'],
        ];

        foreach ($mapping as $key => [$name, $email, $jobTitle, $role]) {
            $this->users[$key] = $this->user($name, $email, $jobTitle, $division->id, $role);
        }

        $this->users['admin'] = $this->users['gussastra'];
        $this->users['manager'] = $this->users['wira'];
        $this->users['member'] = $this->users['gustra'];
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

    private function resetDemoProjects(): void
    {
        Project::withTrashed()
            ->whereIn('name', [
                'Website Trip Tirta Yatra Margi Nusa Penida',
                'Website Supplier MBG Sumber Wangi',
            ])
            ->get()
            ->each(function (Project $project) {
                $taskIds = Task::withTrashed()->where('project_id', $project->id)->pluck('id');
                $milestoneIds = Milestone::withTrashed()->where('project_id', $project->id)->pluck('id');
                $baselineIds = ProjectBaseline::where('project_id', $project->id)->pluck('id');
                $periodIds = ReportingPeriod::where('project_id', $project->id)->pluck('id');

                if ($taskIds->isNotEmpty()) {
                    $this->deleteDemoTaskNotifications($taskIds->all());
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

                if ($milestoneIds->isNotEmpty()) {
                    Comment::where('entity_type', Milestone::class)->whereIn('entity_id', $milestoneIds)->delete();
                    Attachment::where('entity_type', Milestone::class)->whereIn('entity_id', $milestoneIds)->delete();
                    Milestone::withTrashed()->whereIn('id', $milestoneIds)->forceDelete();
                }

                Task::withTrashed()->whereIn('id', $taskIds)->forceDelete();
                $project->forceDelete();
            });
    }

    private function seedProject(array $data): void
    {
        $projectCreatedAt = Carbon::parse($data['start'])->setTime(8, 0);

        $project = Project::create([
            'name' => $data['name'],
            'client_name' => $data['client_name'],
            'value_amount' => $data['value_amount'],
            'scope' => $data['scope'],
            'objective' => $data['objective'],
            'division_owner_id' => $this->users['admin']->id,
            'start_planned' => $data['start'],
            'end_planned' => $data['end'],
            'status' => 'In Progress',
        ]);
        $this->setModelTimestamps($project, $projectCreatedAt);

        $tasksByKey = [];

        foreach ($data['milestones'] as $milestoneData) {
            $milestone = Milestone::create([
                'project_id' => $project->id,
                'name' => $milestoneData['name'],
                'due_planned' => $milestoneData['due_planned'],
                'due_actual' => $milestoneData['due_actual'],
                'status' => $milestoneData['status'],
            ]);
            $this->setModelTimestamps($milestone, $projectCreatedAt);

            foreach ($milestoneData['tasks'] as $taskData) {
                $task = Task::create([
                    'project_id' => $project->id,
                    'milestone_id' => $milestone->id,
                    'title' => $taskData['title'],
                    'description' => $this->taskDescription($taskData['title'], $data['client_name']),
                    'priority' => $taskData['priority'],
                    'status' => $taskData['status'],
                    'start_planned' => $taskData['start_planned'],
                    'end_planned' => $taskData['end_planned'],
                    'duration_planned' => $this->duration($taskData['start_planned'], $taskData['end_planned']),
                    'start_actual' => $taskData['start_actual'],
                    'end_actual' => $taskData['end_actual'],
                    'duration_actual' => $taskData['start_actual'] ? $this->duration($taskData['start_actual'], $taskData['end_actual'] ?? $this->today->toDateString()) : null,
                    'percent_complete' => $taskData['percent'],
                    'budget_cost' => $taskData['budget'],
                ]);
                $this->setModelTimestamps($task, $projectCreatedAt);

                $tasksByKey[$taskData['key']] = $task;
                $this->seedTaskActivity($task, $taskData);
            }
        }

        foreach ($data['dependencies'] as $dependency) {
            TaskDependency::create([
                'task_id' => $tasksByKey[$dependency['task']]->id,
                'depends_on_task_id' => $tasksByKey[$dependency['depends_on']]->id,
                'type' => $dependency['type'],
                'lag_days' => $dependency['lag'],
            ]);
        }

        $this->seedProjectReporting($project, collect($tasksByKey)->values(), $data['snapshot_dates']);
        $this->seedProjectCommentsAndAttachments($project, $data['comments']);
    }

    private function seedTaskActivity(Task $task, array $taskData): void
    {
        $assigneeKey = $taskData['assignee'];
        $assignee = $this->users[$assigneeKey] ?? $this->users['gustra'];

        $assignment = TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $assignee->id,
            'role_on_task' => $this->taskRoleOnTask($assigneeKey),
            'estimated_effort_hours' => $taskData['estimated_effort_hours'],
            'assigned_at' => Carbon::parse($taskData['start_planned'])->startOfDay(),
        ]);
        $this->setModelTimestamps($assignment, Carbon::parse($taskData['start_planned'])->setTime(8, 30));

        $this->notifyDemoAssignee($task, $assignee, $assignment->role_on_task ?: 'Member');
        $this->seedStatusHistory($task, $taskData, $assignee);
        $this->seedProgressEffortAndCost($task, $taskData, $assignee);
    }

    private function seedStatusHistory(Task $task, array $taskData, User $assignee): void
    {
        if (!$taskData['start_actual']) {
            return;
        }

        StatusHistory::create([
            'task_id' => $task->id,
            'from_status' => 'To Do',
            'to_status' => $taskData['status'] === 'Done' ? 'In Progress' : $taskData['status'],
            'changed_by' => $assignee->id,
            'note' => 'Task mulai dikerjakan sesuai rencana demo project.',
            'created_at' => Carbon::parse($taskData['start_actual'])->setTime(9, 0),
            'updated_at' => Carbon::parse($taskData['start_actual'])->setTime(9, 0),
        ]);

        if ($taskData['status'] === 'Done' && $taskData['end_actual']) {
            StatusHistory::create([
                'task_id' => $task->id,
                'from_status' => 'In Progress',
                'to_status' => 'Done',
                'changed_by' => $this->users['manager']->id,
                'note' => 'Task selesai dan sudah direview.',
                'created_at' => Carbon::parse($taskData['end_actual'])->setTime(16, 30),
                'updated_at' => Carbon::parse($taskData['end_actual'])->setTime(16, 30),
            ]);
        }
    }

    private function seedProgressEffortAndCost(Task $task, array $taskData, User $assignee): void
    {
        $previousPercent = 0;
        $progress = $taskData['progress'];

        if (empty($progress)) {
            return;
        }

        foreach ($progress as $date => $percent) {
            $percent = (int) $percent;
            $delta = max(0, $percent - $previousPercent);
            $effortHours = round(((float) $taskData['estimated_effort_hours']) * ($delta / 100) * 0.98, 2);
            $costAmount = round(((float) $taskData['budget']) * ($delta / 100) * (float) $taskData['actual_cost_factor'], 2);

            $progressEntry = TaskProgressEntry::create([
                'task_id' => $task->id,
                'progress_date' => $date,
                'percent_complete' => $percent,
                'changed_by' => $assignee->id,
            ]);
            $this->setModelTimestamps($progressEntry, Carbon::parse($date)->setTime(16, 0));

            if ($delta > 0) {
                $timeEntry = TimeEntry::create([
                    'task_id' => $task->id,
                    'user_id' => $assignee->id,
                    'date' => $date,
                    'hours' => max(1, $effortHours),
                    'note' => 'Update progress demo: '.$previousPercent.'% ke '.$percent.'%.',
                ]);
                $this->setModelTimestamps($timeEntry, Carbon::parse($date)->setTime(16, 10));

                $costEntry = TaskCostEntry::create([
                    'task_id' => $task->id,
                    'incurred_on' => $date,
                    'amount' => max(100000, $costAmount),
                    'category' => $this->costCategory($assignee),
                    'note' => 'Biaya operasional sesuai kenaikan progress demo.',
                ]);
                $this->setModelTimestamps($costEntry, Carbon::parse($date)->setTime(16, 20));
            }

            $previousPercent = $percent;
        }
    }

    private function taskRoleOnTask(string $assigneeKey): string
    {
        return match ($assigneeKey) {
            'gussastra', 'admin' => 'Project Owner',
            'wira', 'manager' => 'Project Manager',
            'gungaria' => 'UI/UX Designer',
            'gungindra' => 'Content Designer',
            'krisna' => 'Frontend Developer',
            'mahen' => 'Backend Developer',
            'dwiki' => 'Integration Developer',
            'divo' => 'QA Engineer',
            'wisnu' => 'DevOps Engineer',
            'gustra' => 'Fullstack Developer',
            'madeadi' => 'Documentation & Support',
            default => 'Team Member',
        };
    }

    private function costCategory(User $user): string
    {
        return match ($user->job_title) {
            'UI/UX Designer', 'Content Designer' => 'Design',
            'Backend Developer', 'Frontend Developer', 'Integration Developer', 'Fullstack Developer' => 'Development',
            'QA Engineer' => 'Quality Assurance',
            'DevOps Engineer' => 'Infrastructure',
            default => 'Project Operation',
        };
    }

    private function notifyDemoAssignee(Task $task, User $assignee, string $roleOnTask): void
    {
        $assignee->notify(new TaskActivityNotification('task_assigned', [
            'task_id' => $task->id,
            'task_title' => $task->title,
            'entity_type' => 'Task',
            'entity_id' => $task->id,
            'actor_id' => null,
            'actor_name' => 'System Seeder',
            'message' => 'Anda ditugaskan pada task '.$task->title.' sebagai '.$roleOnTask,
        ]));
    }

    /**
     * @param array<int> $taskIds
     */
    private function deleteDemoTaskNotifications(array $taskIds): void
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

    private function seedProjectReporting(Project $project, $tasks, array $snapshotDates): void
    {
        $baseline = ProjectBaseline::create([
            'project_id' => $project->id,
            'baseline_name' => 'Baseline Jadwal Awal',
            'taken_at' => Carbon::parse($project->start_planned)->setTime(10, 0),
            'note' => 'Baseline awal untuk demo progress, EVM, cost, dan gantt.',
            'start_planned_base' => $project->start_planned,
            'end_planned_base' => $project->end_planned,
        ]);

        $weight = round(100 / max(1, $tasks->count()), 2);
        foreach ($tasks as $task) {
            $assignmentEffort = (float) TaskAssignment::where('task_id', $task->id)->sum('estimated_effort_hours');

            TaskBaseline::create([
                'baseline_id' => $baseline->id,
                'task_id' => $task->id,
                'start_planned_base' => $task->start_planned,
                'end_planned_base' => $task->end_planned,
                'duration_planned_base' => $task->duration_planned,
                'weight' => $weight,
                'planned_effort_hours' => $assignmentEffort,
                'budget_cost_base' => $task->budget_cost,
            ]);
        }

        foreach ($snapshotDates as $date) {
            $date = Carbon::parse($date);
            $period = ReportingPeriod::create([
                'project_id' => $project->id,
                'period_date' => $date->toDateString(),
                'note' => 'Snapshot progress demo sampai '.$date->translatedFormat('d F Y').'.',
            ]);

            $asOfEnd = $date->copy()->endOfDay();
            $doneTasks = $tasks->filter(function (Task $task) use ($asOfEnd) {
                return $task->end_actual && Carbon::parse($task->end_actual)->endOfDay()->lessThanOrEqualTo($asOfEnd);
            });

            KpiSnapshot::create([
                'project_id' => $project->id,
                'period_id' => $period->id,
                'tasks_total' => $tasks->count(),
                'tasks_done' => $doneTasks->count(),
                'overdue_count' => $tasks
                    ->filter(fn (Task $task) => $this->isOverdueAsOf($task, $date))
                    ->count(),
                'avg_cycle_time_days' => $doneTasks
                    ->filter(fn (Task $task) => $task->start_actual && $task->end_actual)
                    ->avg(fn (Task $task) => Carbon::parse($task->start_actual)->diffInDays(Carbon::parse($task->end_actual))) ?? 0,
            ]);
        }
    }

    private function isOverdueAsOf(Task $task, Carbon $date): bool
    {
        if (!$task->end_planned || !Carbon::parse($task->end_planned)->lt($date)) {
            return false;
        }

        return !$task->end_actual || Carbon::parse($task->end_actual)->gt($date);
    }

    private function seedProjectCommentsAndAttachments(Project $project, array $comments): void
    {
        foreach ($comments as $content) {
            Comment::create([
                'entity_type' => Project::class,
                'entity_id' => $project->id,
                'user_id' => $this->users['manager']->id,
                'content' => $content,
            ]);
        }

        Attachment::create([
            'entity_type' => Project::class,
            'entity_id' => $project->id,
            'uploaded_by' => $this->users['manager']->id,
            'filename' => (string) str($project->client_name)->slug().'-brief.pdf',
            'mime' => 'application/pdf',
            'storage_path' => 'demo/attachments/'.(string) str($project->client_name)->slug().'-brief.pdf',
            'size' => 384000,
            'uploaded_at' => Carbon::parse($project->start_planned)->addDay()->setTime(11, 0),
            'status' => 'Approved',
            'verified_by' => $this->users['admin']->id,
            'verified_at' => Carbon::parse($project->start_planned)->addDays(2)->setTime(14, 0),
        ]);
    }

    private function taskDescription(string $title, string $client): string
    {
        return "Pekerjaan {$title} untuk {$client}, termasuk review internal dan dokumentasi hasil agar progress dapat dipantau di dashboard.";
    }

    private function setModelTimestamps($model, Carbon $at): void
    {
        if (!$model) {
            return;
        }

        $model->created_at = $at;
        $model->updated_at = $at;
        $model->timestamps = false;
        $model->saveQuietly();
        $model->timestamps = true;
    }

    private function duration($start, $end): int
    {
        if (!$start || !$end) {
            return 0;
        }

        return Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
    }
}
