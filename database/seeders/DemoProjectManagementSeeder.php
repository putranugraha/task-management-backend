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
        // Demo position for thesis presentation: active projects with healthy KPI/EVM through mid June.
        $this->today = Carbon::create(2026, 6, 16);

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
                'start' => '2026-05-13',
                'end' => '2026-06-23',
                'scope' => 'Website trip tirta yatra Bali dan Nusa Penida dengan katalog paket, itinerary, galeri destinasi, artikel ringan, dan form inquiry WhatsApp.',
                'objective' => 'Meningkatkan kepercayaan calon pemesan dan membuat alur konsultasi paket tirta yatra lebih cepat.',
                'snapshot_dates' => ['2026-05-26', '2026-06-02', '2026-06-09', '2026-06-16'],
                'milestones' => [
                    [
                        'name' => 'Discovery & Perencanaan',
                        'due_planned' => '2026-05-20',
                        'due_actual' => '2026-05-19',
                        'status' => 'Completed',
                        'tasks' => [
                            $this->task('MNP-T01', 'Discovery kebutuhan paket tirta yatra', 'High', '2026-05-13', '2026-05-15', 720000, 'gussastra', 24, ['2026-05-15' => 100], 0.95),
                            $this->task('MNP-T02', 'Survey konten dan referensi destinasi Nusa Penida', 'High', '2026-05-15', '2026-05-18', 1200000, 'gungaria', 30, ['2026-05-18' => 100], 1.00),
                            $this->task('MNP-T03', 'Sitemap dan struktur halaman website trip', 'High', '2026-05-17', '2026-05-20', 680000, 'wira', 28, ['2026-05-20' => 100], 0.92),
                        ],
                    ],
                    [
                        'name' => 'UI/UX Design',
                        'due_planned' => '2026-06-02',
                        'due_actual' => '2026-06-02',
                        'status' => 'Completed',
                        'tasks' => [
                            $this->task('MNP-T04', 'Desain UI homepage Margi Nusa Penida', 'High', '2026-05-21', '2026-05-26', 1550000, 'gungaria', 42, ['2026-05-26' => 85, '2026-06-02' => 100], 0.98),
                            $this->task('MNP-T05', 'Desain halaman paket trip dan itinerary', 'High', '2026-05-27', '2026-06-02', 1450000, 'gungindra', 46, ['2026-06-02' => 100], 1.01),
                        ],
                    ],
                    [
                        'name' => 'Development & Integrasi',
                        'due_planned' => '2026-06-16',
                        'due_actual' => null,
                        'status' => 'In Progress',
                        'tasks' => [
                            $this->task('MNP-T06', 'Implementasi frontend homepage dan layout utama', 'High', '2026-06-01', '2026-06-07', 2100000, 'gustra', 54, ['2026-06-02' => 25, '2026-06-09' => 100], 1.02),
                            $this->task('MNP-T07', 'Implementasi halaman paket trip dan detail itinerary', 'High', '2026-06-04', '2026-06-18', 1800000, 'krisna', 58, ['2026-06-09' => 55, '2026-06-16' => 92], 0.96),
                            $this->task('MNP-T08', 'Integrasi form inquiry WhatsApp dan tracking sumber lead', 'Medium', '2026-06-10', '2026-06-17', 900000, 'mahen', 36, ['2026-06-16' => 68], 0.94),
                            $this->task('MNP-T09', 'Setup galeri destinasi dan optimasi gambar', 'Medium', '2026-06-12', '2026-06-19', 780000, 'dwiki', 34, ['2026-06-16' => 45], 0.97),
                            $this->task('MNP-T10', 'Setup hosting, domain, dan staging awal', 'Medium', '2026-06-03', '2026-06-06', 620000, 'wisnu', 22, ['2026-06-09' => 100], 1.04),
                        ],
                    ],
                    [
                        'name' => 'Testing & Go Live',
                        'due_planned' => '2026-06-23',
                        'due_actual' => null,
                        'status' => 'Planned',
                        'tasks' => [
                            $this->task('MNP-T11', 'Testing responsive mobile dan browser utama', 'Medium', '2026-06-17', '2026-06-20', 650000, 'divo', 28, [], 1.00),
                            $this->task('MNP-T12', 'Final deployment dan handover admin', 'High', '2026-06-21', '2026-06-23', 1500000, 'madeadi', 24, [], 1.00),
                        ],
                    ],
                ],
                'dependencies' => [
                    ['task' => 'MNP-T02', 'depends_on' => 'MNP-T01', 'type' => 'FS', 'lag' => 0],
                    ['task' => 'MNP-T03', 'depends_on' => 'MNP-T01', 'type' => 'SS', 'lag' => 1],
                    ['task' => 'MNP-T04', 'depends_on' => 'MNP-T03', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MNP-T05', 'depends_on' => 'MNP-T04', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MNP-T06', 'depends_on' => 'MNP-T04', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MNP-T07', 'depends_on' => 'MNP-T05', 'type' => 'SS', 'lag' => 2],
                    ['task' => 'MNP-T08', 'depends_on' => 'MNP-T07', 'type' => 'SS', 'lag' => 3],
                    ['task' => 'MNP-T09', 'depends_on' => 'MNP-T07', 'type' => 'SS', 'lag' => 4],
                    ['task' => 'MNP-T11', 'depends_on' => 'MNP-T08', 'type' => 'FS', 'lag' => 0],
                    ['task' => 'MNP-T12', 'depends_on' => 'MNP-T11', 'type' => 'FS', 'lag' => 0],
                ],
                'comments' => [
                    'Progress masih sesuai rencana. Fokus pekan ini menyelesaikan detail paket, integrasi inquiry, dan galeri destinasi.',
                    'Risiko utama ada pada finalisasi konten itinerary, tetapi jadwal go live akhir Juni masih aman.',
                ],
            ],
            [
                'name' => 'Website Supplier MBG Sumber Wangi',
                'client_name' => 'Supplier MBG Sumber Wangi',
                'value_amount' => 16500000,
                'start' => '2026-05-19',
                'end' => '2026-06-30',
                'scope' => 'Website profil dapur dan supplier MBG dengan informasi kapasitas produksi, menu, fasilitas dapur, standar kebersihan, dokumentasi legal, dan form kerja sama.',
                'objective' => 'Membuat profil digital yang kredibel untuk presentasi supplier MBG dan komunikasi kerja sama dengan sekolah atau mitra.',
                'snapshot_dates' => ['2026-05-26', '2026-06-02', '2026-06-09', '2026-06-16'],
                'milestones' => [
                    [
                        'name' => 'Requirement & Operational Mapping',
                        'due_planned' => '2026-05-28',
                        'due_actual' => '2026-05-28',
                        'status' => 'Completed',
                        'tasks' => [
                            $this->task('MBG-T01', 'Mapping proses dapur MBG dan kebutuhan stakeholder', 'High', '2026-05-19', '2026-05-22', 900000, 'gussastra', 30, ['2026-05-22' => 100], 0.93),
                            $this->task('MBG-T02', 'Pendataan kapasitas produksi dan alur distribusi', 'High', '2026-05-22', '2026-05-26', 850000, 'wira', 34, ['2026-05-26' => 100], 0.98),
                            $this->task('MBG-T03', 'Dokumentasi foto dapur dan fasilitas produksi', 'Medium', '2026-05-25', '2026-05-28', 1450000, 'gungindra', 30, ['2026-05-26' => 55, '2026-06-02' => 100], 1.03),
                        ],
                    ],
                    [
                        'name' => 'Information Architecture & Design',
                        'due_planned' => '2026-06-10',
                        'due_actual' => null,
                        'status' => 'In Progress',
                        'tasks' => [
                            $this->task('MBG-T04', 'Rancang struktur halaman profil supplier MBG', 'High', '2026-05-29', '2026-06-03', 850000, 'wira', 36, ['2026-06-02' => 75, '2026-06-09' => 100], 0.97),
                            $this->task('MBG-T05', 'Desain UI homepage Supplier MBG Sumber Wangi', 'High', '2026-06-02', '2026-06-09', 1750000, 'gungaria', 48, ['2026-06-09' => 85, '2026-06-16' => 100], 1.00),
                            $this->task('MBG-T06', 'Desain halaman menu mingguan dan standar gizi', 'Medium', '2026-06-07', '2026-06-18', 1150000, 'mahen', 38, ['2026-06-09' => 35, '2026-06-16' => 82], 0.96),
                        ],
                    ],
                    [
                        'name' => 'Development Modul Profil & Menu',
                        'due_planned' => '2026-06-24',
                        'due_actual' => null,
                        'status' => 'In Progress',
                        'tasks' => [
                            $this->task('MBG-T07', 'Setup staging dan struktur repository', 'Medium', '2026-06-04', '2026-06-06', 650000, 'wisnu', 20, ['2026-06-09' => 100], 1.02),
                            $this->task('MBG-T08', 'Implementasi halaman profil perusahaan dan fasilitas dapur', 'High', '2026-06-10', '2026-06-18', 2600000, 'gustra', 56, ['2026-06-16' => 62], 0.95),
                            $this->task('MBG-T09', 'Implementasi katalog menu MBG dan kapasitas produksi', 'High', '2026-06-14', '2026-06-23', 2150000, 'dwiki', 54, ['2026-06-16' => 32], 0.94),
                            $this->task('MBG-T10', 'Dokumentasi standar kebersihan dan legalitas dapur', 'Medium', '2026-06-16', '2026-06-24', 1350000, 'krisna', 42, ['2026-06-16' => 20], 0.96),
                        ],
                    ],
                    [
                        'name' => 'Testing & Deployment',
                        'due_planned' => '2026-06-30',
                        'due_actual' => null,
                        'status' => 'Planned',
                        'tasks' => [
                            $this->task('MBG-T11', 'Testing form kerja sama dan performa mobile', 'Medium', '2026-06-25', '2026-06-27', 950000, 'divo', 24, [], 1.00),
                            $this->task('MBG-T12', 'Final deployment dan training update konten MBG', 'High', '2026-06-28', '2026-06-30', 1950000, 'madeadi', 24, [], 1.00),
                        ],
                    ],
                ],
                'dependencies' => [
                    ['task' => 'MBG-T02', 'depends_on' => 'MBG-T01', 'type' => 'FS', 'lag' => 0],
                    ['task' => 'MBG-T03', 'depends_on' => 'MBG-T01', 'type' => 'SS', 'lag' => 2],
                    ['task' => 'MBG-T04', 'depends_on' => 'MBG-T02', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MBG-T05', 'depends_on' => 'MBG-T04', 'type' => 'SS', 'lag' => 2],
                    ['task' => 'MBG-T06', 'depends_on' => 'MBG-T05', 'type' => 'SS', 'lag' => 3],
                    ['task' => 'MBG-T08', 'depends_on' => 'MBG-T05', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MBG-T09', 'depends_on' => 'MBG-T08', 'type' => 'SS', 'lag' => 3],
                    ['task' => 'MBG-T10', 'depends_on' => 'MBG-T08', 'type' => 'SS', 'lag' => 4],
                    ['task' => 'MBG-T11', 'depends_on' => 'MBG-T10', 'type' => 'FS', 'lag' => 0],
                    ['task' => 'MBG-T12', 'depends_on' => 'MBG-T11', 'type' => 'FS', 'lag' => 0],
                ],
                'comments' => [
                    'Data operasional dan dokumentasi fasilitas sudah lengkap. Fokus berikutnya adalah implementasi halaman profil dan katalog menu.',
                    'Project masih sehat untuk target akhir Juni, dengan prioritas menjaga kualitas konten dan performa mobile.',
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

        $tasksByKey = [];

        foreach ($data['milestones'] as $milestoneData) {
            $milestone = Milestone::create([
                'project_id' => $project->id,
                'name' => $milestoneData['name'],
                'due_planned' => $milestoneData['due_planned'],
                'due_actual' => $milestoneData['due_actual'],
                'status' => $milestoneData['status'],
            ]);

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
            TaskProgressEntry::create([
                'task_id' => $task->id,
                'progress_date' => $this->today->toDateString(),
                'percent_complete' => 0,
                'changed_by' => $assignee->id,
            ]);

            return;
        }

        foreach ($progress as $date => $percent) {
            $percent = (int) $percent;
            $delta = max(0, $percent - $previousPercent);
            $effortHours = round(((float) $taskData['estimated_effort_hours']) * ($delta / 100) * 0.98, 2);
            $costAmount = round(((float) $taskData['budget']) * ($delta / 100) * (float) $taskData['actual_cost_factor'], 2);

            TaskProgressEntry::create([
                'task_id' => $task->id,
                'progress_date' => $date,
                'percent_complete' => $percent,
                'changed_by' => $assignee->id,
            ]);

            if ($delta > 0) {
                TimeEntry::create([
                    'task_id' => $task->id,
                    'user_id' => $assignee->id,
                    'date' => $date,
                    'hours' => max(1, $effortHours),
                    'note' => 'Update progress demo: '.$previousPercent.'% ke '.$percent.'%.',
                ]);

                TaskCostEntry::create([
                    'task_id' => $task->id,
                    'incurred_on' => $date,
                    'amount' => max(100000, $costAmount),
                    'category' => $this->costCategory($assignee),
                    'note' => 'Biaya operasional sesuai kenaikan progress demo.',
                ]);
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
                    ->avg(fn (Task $task) => $this->duration($task->start_actual, $task->end_actual)) ?? 0,
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

    private function duration($start, $end): int
    {
        if (!$start || !$end) {
            return 0;
        }

        return Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
    }
}
