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
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DemoProjectManagementSeeder extends Seeder
{
    private Carbon $today;
    private array $users = [];

    public function run(): void
    {
        $this->today = Carbon::create(2026, 5, 10);

        DB::transaction(function () {
            if (Role::whereIn('name', ['Admin', 'Manager', 'Member'])->count() < 3) {
                $this->call(RolePermissionSeeder::class);
            }

            $this->prepareUsers();
            $this->resetDemoProjects();

            $this->seedProject([
                'name' => 'Website Trip Tirta Yatra Margi Nusa Penida',
                'client_name' => 'Margi Nusa Penida',
                'value_amount' => 13000000,
                'start' => '2026-04-07',
                'end' => '2026-07-07',
                'scope' => 'Website trip tirta yatra Bali dan Nusa Penida dengan katalog paket, itinerary, galeri destinasi, artikel ringan, dan form inquiry WhatsApp.',
                'objective' => 'Meningkatkan kepercayaan calon pemesan dan membuat alur konsultasi paket tirta yatra lebih cepat.',
                'costs' => [
                    ['task' => 'Discovery kebutuhan paket tirta yatra', 'incurred_on' => '2026-04-08', 'amount' => 650000, 'category' => 'Discovery', 'note' => 'Meeting awal, dokumentasi kebutuhan, dan notulen paket tirta yatra.'],
                    ['task' => 'Survey konten dan referensi destinasi Nusa Penida', 'incurred_on' => '2026-04-11', 'amount' => 1200000, 'category' => 'Content', 'note' => 'Transport dan konsumsi survey konten destinasi.'],
                    ['task' => 'Sitemap dan struktur halaman website trip', 'incurred_on' => '2026-04-15', 'amount' => 500000, 'category' => 'Planning', 'note' => 'Penyusunan sitemap dan user flow inquiry.'],
                    ['task' => 'Desain UI homepage Margi Nusa Penida', 'incurred_on' => '2026-04-23', 'amount' => 1700000, 'category' => 'Design', 'note' => 'Desain hero, paket unggulan, itinerary ringkas, dan CTA WhatsApp.'],
                    ['task' => 'Implementasi frontend homepage dan layout utama', 'incurred_on' => '2026-05-03', 'amount' => 1900000, 'category' => 'Development', 'note' => 'Layout utama, navbar, footer, dan section paket.'],
                    ['task' => 'Setup hosting, domain, dan staging awal', 'incurred_on' => '2026-05-06', 'amount' => 600000, 'category' => 'Infrastructure', 'note' => 'Domain, DNS, dan staging preview untuk review klien.'],
                ],
                'milestones' => [
                    [
                        'name' => 'Discovery & Perencanaan',
                        'due_planned' => '2026-04-18',
                        'due_actual' => '2026-04-17',
                        'status' => 'Completed',
                        'tasks' => [
                            ['key' => 'MNP-T01', 'title' => 'Discovery kebutuhan paket tirta yatra', 'priority' => 'High', 'status' => 'Done', 'start_planned' => '2026-04-07', 'end_planned' => '2026-04-09', 'start_actual' => '2026-04-07', 'end_actual' => '2026-04-09', 'percent' => 100, 'budget' => 700000, 'assignee' => 'admin'],
                            ['key' => 'MNP-T02', 'title' => 'Survey konten dan referensi destinasi Nusa Penida', 'priority' => 'High', 'status' => 'Done', 'start_planned' => '2026-04-10', 'end_planned' => '2026-04-13', 'start_actual' => '2026-04-10', 'end_actual' => '2026-04-12', 'percent' => 100, 'budget' => 1100000, 'assignee' => 'gung_aria'],
                            ['key' => 'MNP-T03', 'title' => 'Sitemap dan struktur halaman website trip', 'priority' => 'High', 'status' => 'Done', 'start_planned' => '2026-04-14', 'end_planned' => '2026-04-18', 'start_actual' => '2026-04-14', 'end_actual' => '2026-04-17', 'percent' => 100, 'budget' => 600000, 'assignee' => 'manager'],
                        ],
                    ],
                    [
                        'name' => 'UI/UX Design',
                        'due_planned' => '2026-04-30',
                        'due_actual' => null,
                        'status' => 'In Progress',
                        'tasks' => [
                            ['key' => 'MNP-T04', 'title' => 'Desain UI homepage Margi Nusa Penida', 'priority' => 'High', 'status' => 'Done', 'start_planned' => '2026-04-19', 'end_planned' => '2026-04-23', 'start_actual' => '2026-04-19', 'end_actual' => '2026-04-24', 'percent' => 100, 'budget' => 1500000, 'assignee' => 'gung_aria'],
                            ['key' => 'MNP-T05', 'title' => 'Desain halaman paket trip dan itinerary', 'priority' => 'High', 'status' => 'In Progress', 'start_planned' => '2026-04-24', 'end_planned' => '2026-04-30', 'start_actual' => '2026-04-25', 'end_actual' => null, 'percent' => 70, 'budget' => 1300000, 'assignee' => 'gung_aria'],
                        ],
                    ],
                    [
                        'name' => 'Development & Integrasi',
                        'due_planned' => '2026-06-16',
                        'due_actual' => null,
                        'status' => 'In Progress',
                        'tasks' => [
                            ['key' => 'MNP-T06', 'title' => 'Implementasi frontend homepage dan layout utama', 'priority' => 'High', 'status' => 'In Progress', 'start_planned' => '2026-05-01', 'end_planned' => '2026-05-09', 'start_actual' => '2026-05-01', 'end_actual' => null, 'percent' => 65, 'budget' => 2000000, 'assignee' => 'gustra'],
                            ['key' => 'MNP-T07', 'title' => 'Implementasi halaman paket trip dan detail itinerary', 'priority' => 'High', 'status' => 'In Progress', 'start_planned' => '2026-05-08', 'end_planned' => '2026-05-20', 'start_actual' => '2026-05-09', 'end_actual' => null, 'percent' => 20, 'budget' => 1600000, 'assignee' => 'gustra'],
                            ['key' => 'MNP-T08', 'title' => 'Integrasi form inquiry WhatsApp dan tracking sumber lead', 'priority' => 'Medium', 'status' => 'To Do', 'start_planned' => '2026-05-18', 'end_planned' => '2026-05-25', 'start_actual' => null, 'end_actual' => null, 'percent' => 0, 'budget' => 800000, 'assignee' => 'dwiki'],
                            ['key' => 'MNP-T09', 'title' => 'Setup galeri destinasi dan optimasi gambar', 'priority' => 'Medium', 'status' => 'To Do', 'start_planned' => '2026-05-22', 'end_planned' => '2026-05-31', 'start_actual' => null, 'end_actual' => null, 'percent' => 0, 'budget' => 700000, 'assignee' => 'dwiki'],
                            ['key' => 'MNP-T10', 'title' => 'Setup hosting, domain, dan staging awal', 'priority' => 'Medium', 'status' => 'Done', 'start_planned' => '2026-05-04', 'end_planned' => '2026-05-07', 'start_actual' => '2026-05-04', 'end_actual' => '2026-05-06', 'percent' => 100, 'budget' => 600000, 'assignee' => 'admin'],
                        ],
                    ],
                    [
                        'name' => 'Testing & Go Live',
                        'due_planned' => '2026-07-07',
                        'due_actual' => null,
                        'status' => 'Planned',
                        'tasks' => [
                            ['key' => 'MNP-T11', 'title' => 'Testing responsive mobile dan browser utama', 'priority' => 'Medium', 'status' => 'To Do', 'start_planned' => '2026-06-17', 'end_planned' => '2026-06-25', 'start_actual' => null, 'end_actual' => null, 'percent' => 0, 'budget' => 600000, 'assignee' => 'manager'],
                            ['key' => 'MNP-T12', 'title' => 'Final deployment dan handover admin', 'priority' => 'High', 'status' => 'To Do', 'start_planned' => '2026-06-26', 'end_planned' => '2026-07-07', 'start_actual' => null, 'end_actual' => null, 'percent' => 0, 'budget' => 1500000, 'assignee' => 'admin'],
                        ],
                    ],
                ],
                'dependencies' => [
                    ['task' => 'MNP-T02', 'depends_on' => 'MNP-T01', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MNP-T03', 'depends_on' => 'MNP-T01', 'type' => 'SS', 'lag' => 2],
                    ['task' => 'MNP-T04', 'depends_on' => 'MNP-T03', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MNP-T05', 'depends_on' => 'MNP-T04', 'type' => 'SS', 'lag' => 2],
                    ['task' => 'MNP-T06', 'depends_on' => 'MNP-T04', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MNP-T07', 'depends_on' => 'MNP-T05', 'type' => 'SS', 'lag' => 3],
                    ['task' => 'MNP-T08', 'depends_on' => 'MNP-T07', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MNP-T09', 'depends_on' => 'MNP-T07', 'type' => 'FF', 'lag' => 2],
                    ['task' => 'MNP-T12', 'depends_on' => 'MNP-T11', 'type' => 'SF', 'lag' => 0],
                ],
                'comments' => [
                    'Konten utama sudah masuk dari klien, foto pura dan itinerary masih menunggu kurasi final.',
                    'Prioritas minggu ini adalah menyelesaikan halaman paket dan CTA WhatsApp.',
                ],
            ]);

            $this->seedProject([
                'name' => 'Website Supplier MBG Sumber Wangi',
                'client_name' => 'Supplier MBG Sumber Wangi',
                'value_amount' => 16000000,
                'start' => '2026-04-23',
                'end' => '2026-07-23',
                'scope' => 'Website profil dapur dan supplier MBG dengan informasi kapasitas produksi, menu, fasilitas dapur, standar kebersihan, dokumentasi legal, dan form kerja sama.',
                'objective' => 'Membuat profil digital yang kredibel untuk kebutuhan presentasi supplier MBG dan komunikasi kerja sama dengan sekolah/mitra.',
                'costs' => [
                    ['task' => 'Mapping proses dapur MBG dan kebutuhan stakeholder', 'incurred_on' => '2026-04-24', 'amount' => 850000, 'category' => 'Discovery', 'note' => 'Interview operasional dan rangkuman kebutuhan website supplier.'],
                    ['task' => 'Pendataan kapasitas produksi dan alur distribusi', 'incurred_on' => '2026-04-28', 'amount' => 700000, 'category' => 'Operation', 'note' => 'Pendataan kapasitas dapur, rute distribusi, dan jam produksi.'],
                    ['task' => 'Dokumentasi foto dapur dan fasilitas produksi', 'incurred_on' => '2026-05-02', 'amount' => 1400000, 'category' => 'Content', 'note' => 'Foto area persiapan, penyimpanan, packing, dan armada.'],
                    ['task' => 'Desain UI homepage Supplier MBG Sumber Wangi', 'incurred_on' => '2026-05-06', 'amount' => 1500000, 'category' => 'Design', 'note' => 'Desain homepage, fasilitas dapur, dan CTA kerja sama.'],
                    ['task' => 'Setup staging dan struktur repository', 'incurred_on' => '2026-05-08', 'amount' => 600000, 'category' => 'Infrastructure', 'note' => 'Repository, environment staging, dan deploy preview awal.'],
                ],
                'milestones' => [
                    [
                        'name' => 'Requirement & Operational Mapping',
                        'due_planned' => '2026-05-03',
                        'due_actual' => '2026-05-03',
                        'status' => 'Completed',
                        'tasks' => [
                            ['key' => 'MBG-T01', 'title' => 'Mapping proses dapur MBG dan kebutuhan stakeholder', 'priority' => 'High', 'status' => 'Done', 'start_planned' => '2026-04-23', 'end_planned' => '2026-04-26', 'start_actual' => '2026-04-23', 'end_actual' => '2026-04-26', 'percent' => 100, 'budget' => 900000, 'assignee' => 'admin'],
                            ['key' => 'MBG-T02', 'title' => 'Pendataan kapasitas produksi dan alur distribusi', 'priority' => 'High', 'status' => 'Done', 'start_planned' => '2026-04-27', 'end_planned' => '2026-04-30', 'start_actual' => '2026-04-27', 'end_actual' => '2026-04-30', 'percent' => 100, 'budget' => 800000, 'assignee' => 'manager'],
                            ['key' => 'MBG-T03', 'title' => 'Dokumentasi foto dapur dan fasilitas produksi', 'priority' => 'Medium', 'status' => 'Done', 'start_planned' => '2026-05-01', 'end_planned' => '2026-05-03', 'start_actual' => '2026-05-01', 'end_actual' => '2026-05-03', 'percent' => 100, 'budget' => 1400000, 'assignee' => 'gung_aria'],
                        ],
                    ],
                    [
                        'name' => 'Information Architecture & Design',
                        'due_planned' => '2026-05-18',
                        'due_actual' => null,
                        'status' => 'In Progress',
                        'tasks' => [
                            ['key' => 'MBG-T04', 'title' => 'Rancang struktur halaman profil supplier MBG', 'priority' => 'High', 'status' => 'In Progress', 'start_planned' => '2026-05-04', 'end_planned' => '2026-05-07', 'start_actual' => '2026-05-04', 'end_actual' => null, 'percent' => 80, 'budget' => 800000, 'assignee' => 'manager'],
                            ['key' => 'MBG-T05', 'title' => 'Desain UI homepage Supplier MBG Sumber Wangi', 'priority' => 'High', 'status' => 'In Progress', 'start_planned' => '2026-05-06', 'end_planned' => '2026-05-12', 'start_actual' => '2026-05-06', 'end_actual' => null, 'percent' => 55, 'budget' => 1700000, 'assignee' => 'gung_aria'],
                            ['key' => 'MBG-T06', 'title' => 'Desain halaman menu mingguan dan standar gizi', 'priority' => 'Medium', 'status' => 'To Do', 'start_planned' => '2026-05-13', 'end_planned' => '2026-05-18', 'start_actual' => null, 'end_actual' => null, 'percent' => 0, 'budget' => 1100000, 'assignee' => 'gung_aria'],
                        ],
                    ],
                    [
                        'name' => 'Development Modul Profil & Menu',
                        'due_planned' => '2026-06-24',
                        'due_actual' => null,
                        'status' => 'Planned',
                        'tasks' => [
                            ['key' => 'MBG-T07', 'title' => 'Setup staging dan struktur repository', 'priority' => 'Medium', 'status' => 'Done', 'start_planned' => '2026-05-07', 'end_planned' => '2026-05-09', 'start_actual' => '2026-05-07', 'end_actual' => '2026-05-08', 'percent' => 100, 'budget' => 600000, 'assignee' => 'admin'],
                            ['key' => 'MBG-T08', 'title' => 'Implementasi halaman profil perusahaan dan fasilitas dapur', 'priority' => 'High', 'status' => 'To Do', 'start_planned' => '2026-05-19', 'end_planned' => '2026-06-02', 'start_actual' => null, 'end_actual' => null, 'percent' => 0, 'budget' => 2500000, 'assignee' => 'gustra'],
                            ['key' => 'MBG-T09', 'title' => 'Implementasi katalog menu MBG dan kapasitas produksi', 'priority' => 'High', 'status' => 'To Do', 'start_planned' => '2026-06-03', 'end_planned' => '2026-06-14', 'start_actual' => null, 'end_actual' => null, 'percent' => 0, 'budget' => 2100000, 'assignee' => 'dwiki'],
                            ['key' => 'MBG-T10', 'title' => 'Dokumentasi standar kebersihan dan legalitas dapur', 'priority' => 'Medium', 'status' => 'To Do', 'start_planned' => '2026-06-10', 'end_planned' => '2026-06-24', 'start_actual' => null, 'end_actual' => null, 'percent' => 0, 'budget' => 1300000, 'assignee' => 'manager'],
                        ],
                    ],
                    [
                        'name' => 'Testing & Deployment',
                        'due_planned' => '2026-07-23',
                        'due_actual' => null,
                        'status' => 'Planned',
                        'tasks' => [
                            ['key' => 'MBG-T11', 'title' => 'Testing form kerja sama dan performa mobile', 'priority' => 'Medium', 'status' => 'To Do', 'start_planned' => '2026-06-25', 'end_planned' => '2026-07-07', 'start_actual' => null, 'end_actual' => null, 'percent' => 0, 'budget' => 900000, 'assignee' => 'manager'],
                            ['key' => 'MBG-T12', 'title' => 'Final deployment dan training update konten MBG', 'priority' => 'High', 'status' => 'To Do', 'start_planned' => '2026-07-08', 'end_planned' => '2026-07-23', 'start_actual' => null, 'end_actual' => null, 'percent' => 0, 'budget' => 1900000, 'assignee' => 'admin'],
                        ],
                    ],
                ],
                'dependencies' => [
                    ['task' => 'MBG-T02', 'depends_on' => 'MBG-T01', 'type' => 'FS', 'lag' => 0],
                    ['task' => 'MBG-T03', 'depends_on' => 'MBG-T01', 'type' => 'SS', 'lag' => 1],
                    ['task' => 'MBG-T04', 'depends_on' => 'MBG-T02', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MBG-T05', 'depends_on' => 'MBG-T04', 'type' => 'SS', 'lag' => 2],
                    ['task' => 'MBG-T06', 'depends_on' => 'MBG-T05', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MBG-T08', 'depends_on' => 'MBG-T05', 'type' => 'FS', 'lag' => 3],
                    ['task' => 'MBG-T09', 'depends_on' => 'MBG-T08', 'type' => 'FS', 'lag' => 1],
                    ['task' => 'MBG-T10', 'depends_on' => 'MBG-T08', 'type' => 'SS', 'lag' => 4],
                    ['task' => 'MBG-T11', 'depends_on' => 'MBG-T10', 'type' => 'FF', 'lag' => 0],
                    ['task' => 'MBG-T12', 'depends_on' => 'MBG-T11', 'type' => 'SF', 'lag' => 0],
                ],
                'comments' => [
                    'Data kapasitas produksi sudah dikunci, menu mingguan masih menunggu validasi final dari pihak dapur.',
                    'Perlu menonjolkan standar kebersihan dan alur distribusi karena ini jadi bahan kepercayaan calon mitra.',
                ],
            ]);
        });
    }

    private function prepareUsers(): void
    {
        $division = Division::updateOrCreate(
            ['code' => 'SW'],
            ['name' => 'Software', 'description' => 'Tim software untuk UI/UX, frontend, backend, integrasi, QA, dan deployment website.', 'status' => 'Aktif']
        );

        $this->users['admin'] = $this->user('Admin', 'admin@example.com', 'Administrator', $division->id, 'Admin');
        $this->users['manager'] = $this->user('Ayu Pradnya', 'ayu.pm@example.com', 'Project Manager', $division->id, 'Manager');
        $this->users['gustra'] = $this->user('Gustra', 'gustra.software@example.com', 'Software Engineer', $division->id, 'Member');
        $this->users['gung_aria'] = $this->user('Gung Aria', 'gung.aria@example.com', 'UI/UX Designer', $division->id, 'Member');
        $this->users['dwiki'] = $this->user('Dwiki', 'dwiki.software@example.com', 'Backend Developer', $division->id, 'Member');
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

        foreach ($data['costs'] as $cost) {
            $task = collect($tasksByKey)->first(fn (Task $task) => $task->title === $cost['task']);
            if (!$task) {
                continue;
            }

            TaskCostEntry::create([
                'task_id' => $task->id,
                'incurred_on' => $cost['incurred_on'],
                'amount' => $cost['amount'],
                'category' => $cost['category'],
                'note' => $cost['note'],
            ]);
        }

        $this->seedProjectReporting($project, collect($tasksByKey)->values());
        $this->seedProjectCommentsAndAttachments($project, $data['comments']);
    }

    private function seedTaskActivity(Task $task, array $taskData): void
    {
        $assigneeKey = $taskData['assignee'];
        $assignee = $this->users[$assigneeKey] ?? $this->users['gustra'];

        TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $assignee->id,
            'role_on_task' => $this->taskRoleOnTask($assigneeKey),
            'estimated_effort_hours' => max(4, ($task->duration_planned ?? 1) * 6),
            'assigned_at' => Carbon::parse($taskData['start_planned'])->startOfDay(),
        ]);

        if ($taskData['start_actual']) {
            StatusHistory::create([
                'task_id' => $task->id,
                'from_status' => 'To Do',
                'to_status' => $taskData['status'] === 'Done' ? 'In Progress' : $taskData['status'],
                'changed_by' => $assignee->id,
                'note' => 'Task mulai dikerjakan sesuai update operasional.',
                'created_at' => Carbon::parse($taskData['start_actual'])->setTime(9, 0),
                'updated_at' => Carbon::parse($taskData['start_actual'])->setTime(9, 0),
            ]);

            TimeEntry::create([
                'task_id' => $task->id,
                'user_id' => $assignee->id,
                'date' => $taskData['start_actual'],
                'hours' => $taskData['status'] === 'Done' ? 6 : 4,
                'note' => 'Pengerjaan awal dan update progress task.',
            ]);
        }

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

            if ($taskData['end_actual'] !== $taskData['start_actual']) {
                TimeEntry::create([
                    'task_id' => $task->id,
                    'user_id' => $assignee->id,
                    'date' => $taskData['end_actual'],
                    'hours' => 5,
                    'note' => 'Finalisasi dan penyesuaian hasil review.',
                ]);
            }
        }

        TaskProgressEntry::create([
            'task_id' => $task->id,
            'progress_date' => $taskData['end_actual'] ?? ($taskData['start_actual'] ?? $this->today->toDateString()),
            'percent_complete' => $taskData['percent'],
            'changed_by' => $assignee->id,
        ]);
    }

    private function taskRoleOnTask(string $assigneeKey): string
    {
        return match ($assigneeKey) {
            'admin' => 'Project Owner',
            'manager' => 'Project Manager',
            'gung_aria' => 'UI/UX Designer',
            'dwiki' => 'Backend Developer',
            'gustra' => 'Software Engineer',
            default => 'Developer',
        };
    }

    private function seedProjectReporting(Project $project, $tasks): void
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
            TaskBaseline::create([
                'baseline_id' => $baseline->id,
                'task_id' => $task->id,
                'start_planned_base' => $task->start_planned,
                'end_planned_base' => $task->end_planned,
                'duration_planned_base' => $task->duration_planned,
                'weight' => $weight,
                'planned_effort_hours' => max(4, ($task->duration_planned ?? 1) * 6),
            ]);
        }

        foreach ([Carbon::parse($project->start_planned)->addDays(7), $this->today] as $date) {
            $period = ReportingPeriod::create([
                'project_id' => $project->id,
                'period_date' => $date->toDateString(),
                'note' => 'Snapshot progress demo sampai '.$date->translatedFormat('d F Y').'.',
            ]);

            $doneTasks = $tasks->where('status', 'Done');
            KpiSnapshot::create([
                'project_id' => $project->id,
                'period_id' => $period->id,
                'tasks_total' => $tasks->count(),
                'tasks_done' => $doneTasks->count(),
                'overdue_count' => $tasks
                    ->filter(fn (Task $task) => $task->end_planned && Carbon::parse($task->end_planned)->lt($date) && $task->status !== 'Done')
                    ->count(),
                'avg_cycle_time_days' => $doneTasks
                    ->filter(fn (Task $task) => $task->start_actual && $task->end_actual)
                    ->avg(fn (Task $task) => $this->duration($task->start_actual, $task->end_actual)) ?? 0,
            ]);
        }
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
