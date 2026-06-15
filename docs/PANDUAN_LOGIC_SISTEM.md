# Panduan Logic Sistem Task Management

Dokumen ini dibuat untuk membantu menjelaskan alur sistem dari awal, mulai dari login, role dan permission, CRUD project, archive, task dependencies, Gantt Chart, sampai perhitungan EVM.

Repositori yang dipakai:

- Backend Laravel: `D:\TA 2\task-management-copy`
- Frontend Next.js: `D:\TA 2\fe-task-management-copy`

## 1. Gambaran Arsitektur

Sistem ini memakai pola berlapis:

```text
Frontend page/component
  -> API helper
  -> Laravel route
  -> Controller
  -> Form Request
  -> Service
  -> Repository
  -> Model / Database
  -> Resource JSON
```

Penjelasan sederhananya:

- Frontend bertugas menampilkan halaman, tombol, form, toast, modal, dan memanggil API.
- Route menentukan endpoint mana yang boleh diakses dan permission apa yang dibutuhkan.
- Controller menerima request dan mengembalikan response.
- Form Request memvalidasi input sebelum masuk ke service.
- Service menyimpan logic bisnis, validasi alur, cache, activity log, dan aturan sistem.
- Repository menjalankan query database.
- Model mewakili tabel dan relasi database.
- Resource mengatur bentuk response JSON agar frontend menerima format data yang konsisten.

Contoh alur umum:

```text
Admin klik tombol Archive Project
  -> Frontend memanggil DELETE /api/projects/{id}
  -> routes/api.php cek auth + permission
  -> ProjectController::destroy()
  -> ProjectService::deleteProject()
  -> ProjectRepository::deleteProject()
  -> Project::delete() soft delete
  -> Frontend menampilkan toast berhasil
```

## 2. Login dan Auth

### 2.1 Frontend login

File utama:

- `D:\TA 2\fe-task-management-copy\src\app\auth\login\page.tsx`
- `D:\TA 2\fe-task-management-copy\src\contexts\auth-context.tsx`
- `D:\TA 2\fe-task-management-copy\src\lib\api.ts`

Saat user mengisi email dan password, page login memanggil fungsi `login(email, password)` dari auth context.

Di frontend, request login dikirim ke backend:

```ts
const res = await apiRequest<LoginResponse>("POST", "/api/login", {
  email,
  password,
});
```

Lokasi:

```text
D:\TA 2\fe-task-management-copy\src\contexts\auth-context.tsx
```

Setelah login berhasil, token dan data auth disimpan:

```ts
window.localStorage.setItem("access_token", token);
window.localStorage.setItem("token_type", tokenType);
window.localStorage.setItem("user", JSON.stringify(res.user));
window.localStorage.setItem("auth_meta", JSON.stringify(metaToStore));
```

Tujuannya:

- `access_token` dipakai untuk Authorization header.
- `user` dipakai untuk informasi user login.
- `auth_meta` berisi roles, permissions, primary_role, dashboard_type, dan home_path.

### 2.2 API helper menambahkan token

File:

```text
D:\TA 2\fe-task-management-copy\src\lib\api.ts
```

Setiap request API akan otomatis diberi header Bearer token:

```ts
const token = localStorage.getItem("access_token");
if (token && config.headers) {
  config.headers["Authorization"] = `Bearer ${token}`;
}
```

Artinya frontend tidak perlu menulis Authorization manual di setiap halaman.

### 2.3 Backend login

File:

```text
D:\TA 2\task-management-copy\app\Http\Controllers\AuthController.php
```

Method utama:

```php
public function login(Request $request)
```

Alurnya:

1. Validasi email dan password.
2. Normalisasi email ke lowercase.
3. Cari user berdasarkan email.
4. Cek password dengan `Hash::check`.
5. Cek status user harus aktif.
6. Ambil role dan permission aktif.
7. Buat token Sanctum.
8. Kembalikan user, role, permission, dashboard type, dan token.

Potongan logic penting:

```php
if (($user->status ?? null) !== 'Aktif' || ! $user->is_active) {
    return response()->json(['message' => 'Akun Anda tidak aktif.'], 403);
}
```

Artinya user nonaktif tidak bisa login.

Token dibuat dengan:

```php
$token = $user->createToken('auth-token', ['*'], Carbon::now()->addDay())->plainTextToken;
```

### 2.4 Refresh profile

Setelah token tersimpan, frontend memanggil:

```http
GET /api/profile
```

Route profile ada di:

```text
D:\TA 2\task-management-copy\routes\api.php
```

Tujuannya untuk memastikan data role dan permission selalu terbaru dari backend.

## 3. Role dan Permission

### 3.1 Seeder permission

File:

```text
D:\TA 2\task-management-copy\database\seeders\RolePermissionSeeder.php
```

Permission sudah dipisah berdasarkan CRUD:

```php
'melihat project',
'membuat project',
'mengubah project',
'menghapus project',
```

Artinya:

- `melihat project` hanya boleh membaca/view.
- `membuat project` boleh create.
- `mengubah project` boleh update.
- `menghapus project` boleh archive/delete.

Ini lebih rapi daripada satu permission lama seperti `mengelola project`, karena `mengelola` terlalu luas dan susah dipakai untuk hide button.

### 3.2 Permission aktif user

File:

```text
D:\TA 2\task-management-copy\app\Models\User.php
```

Method:

```php
public function activePermissionNames()
```

Logic-nya:

```php
$directPermissions = $this->permissions
    ->filter(fn ($permission) => ($permission->status ?? 'Aktif') === 'Aktif')
    ->pluck('name');

$rolePermissions = $this->roles
    ->filter(fn ($role) => ($role->status ?? 'Aktif') === 'Aktif')
    ->flatMap(fn ($role) => $role->permissions)
    ->filter(fn ($permission) => ($permission->status ?? 'Aktif') === 'Aktif')
    ->pluck('name');
```

Kesimpulan:

- Permission langsung dari user dicek.
- Permission dari role dicek.
- Role dan permission nonaktif tidak dihitung.

### 3.3 Middleware permission backend

File:

```text
D:\TA 2\task-management-copy\routes\api.php
```

Contoh route project:

```php
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::get('projects/stats', [ProjectController::class, 'stats']);
    Route::get('projects/archived', [ProjectController::class, 'archived']);
    Route::apiResource('projects', ProjectController::class)->only(['index','show']);
});
```

Contoh hapus project:

```php
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus project'])->group(function () {
    Route::apiResource('projects', ProjectController::class)->only(['destroy']);
    Route::patch('projects/{project}/restore', [ProjectController::class, 'restore']);
    Route::delete('projects/{project}/force', [ProjectController::class, 'forceDelete']);
});
```

Artinya:

- Semua request harus login.
- User harus aktif.
- User harus punya permission yang sesuai.

### 3.4 Hide menu dan button di frontend

File:

```text
D:\TA 2\fe-task-management-copy\src\contexts\auth-context.tsx
```

Auth context menyediakan:

```ts
hasRole(role)
can(permission)
```

Contoh:

```ts
const canCreateProject = can("membuat project");
const canUpdateProject = can("mengubah project");
const canDeleteProject = hasRole("Admin") && can("menghapus project");
```

Lokasi contoh:

```text
D:\TA 2\fe-task-management-copy\src\app\dashboard\projects\page.tsx
```

Menu sidebar difilter di:

```text
D:\TA 2\fe-task-management-copy\src\components\app-sidebar.tsx
```

Menu hanya tampil jika role/permission cocok dengan:

```text
D:\TA 2\fe-task-management-copy\src\config\menu.ts
```

## 4. Project Management

### 4.1 File utama backend project

- Controller: `D:\TA 2\task-management-copy\app\Http\Controllers\ProjectController.php`
- Service: `D:\TA 2\task-management-copy\app\Services\Implementations\ProjectService.php`
- Repository: `D:\TA 2\task-management-copy\app\Repositories\Eloquent\ProjectRepository.php`
- Model: `D:\TA 2\task-management-copy\app\Models\Project.php`
- Routes: `D:\TA 2\task-management-copy\routes\api.php`

### 4.2 Create project

Endpoint:

```http
POST /api/projects
```

Route permission:

```php
permission:membuat project
```

Alur:

```text
Project create page
  -> POST /api/projects
  -> ProjectController::store()
  -> ProjectService::createProject()
  -> ProjectRepository::createProject()
  -> Project::create()
```

Di service, setelah project dibuat, sistem otomatis membuat baseline awal:

```php
$this->baselineService->createBaseline([
    'project_id' => $project->id,
    'baseline_name' => 'Initial Baseline',
    'taken_at' => Carbon::now(),
]);
```

Baseline penting karena EVM dan Gantt bisa membandingkan rencana awal dengan realisasi.

### 4.3 Read/list project

Endpoint:

```http
GET /api/projects
GET /api/projects/{id}
GET /api/projects/stats
```

Route permission:

```php
permission:melihat project
```

Project list memakai pagination dan filter:

```php
$projects = $this->service->paginateProjects($filters, $perPage);
```

### 4.4 Update project

Endpoint:

```http
PUT /api/projects/{id}
PATCH /api/projects/{id}
PATCH /api/projects/{id}/status
```

Route permission:

```php
permission:mengubah project
```

Service juga menulis activity log `updated` atau `status_changed`.

### 4.5 Archive project atau soft delete

Endpoint:

```http
DELETE /api/projects/{id}
```

Route permission:

```php
permission:menghapus project
```

Controller:

```php
public function destroy(string $id)
{
    $deleted = $this->service->deleteProject($id);
    if (!$deleted) {
        return response()->json(['message' => 'Project tidak ditemukan'], 404);
    }
    return response()->json(['message' => 'Project berhasil di-archive']);
}
```

Repository:

```php
$project->delete();
```

Karena model `Project` memakai:

```php
use SoftDeletes;
```

maka data tidak benar-benar hilang. Laravel hanya mengisi kolom `deleted_at`.

Rumus sederhana:

```text
Archive project = update deleted_at, bukan hapus row database
```

### 4.6 Restore project

Endpoint:

```http
PATCH /api/projects/{id}/restore
```

Repository mencari project yang sudah soft delete:

```php
$project = $this->model->onlyTrashed()->find($id);
$project->restore();
```

### 4.7 Permanent delete project

Endpoint:

```http
DELETE /api/projects/{id}/force
```

Backend hanya mencari project yang sudah archive:

```php
$project = $this->model->onlyTrashed()->find($id);
```

Artinya project aktif tidak bisa langsung dihapus permanen.

Repository permanent delete:

```php
public function forceDeleteArchivedProject($id): bool
{
    $project = $this->model->onlyTrashed()->find($id);
    if (!$project) {
        return false;
    }

    DB::transaction(function () use ($project) {
        $taskIds = Task::withTrashed()
            ->where('project_id', $project->id)
            ->pluck('id');

        $milestoneIds = Milestone::withTrashed()
            ->where('project_id', $project->id)
            ->pluck('id');

        $this->deletePolymorphicRows(Comment::class, Project::class, [$project->id]);
        $this->deletePolymorphicRows(Attachment::class, Project::class, [$project->id]);

        if ($milestoneIds->isNotEmpty()) {
            $this->deletePolymorphicRows(Comment::class, Milestone::class, $milestoneIds->all());
            $this->deletePolymorphicRows(Attachment::class, Milestone::class, $milestoneIds->all());
        }

        if ($taskIds->isNotEmpty()) {
            $this->deletePolymorphicRows(Comment::class, Task::class, $taskIds->all());
            $this->deletePolymorphicRows(Attachment::class, Task::class, $taskIds->all());
        }

        $project->forceDelete();
    });

    return true;
}
```

Penjelasan ke dosen:

```text
Project yang dihapus dari halaman utama tidak langsung hilang, tetapi masuk ke archive.
Permanent delete hanya tersedia di halaman archive khusus Admin.
Saat permanent delete dijalankan, project dan seluruh relasinya dihapus permanen.
```

## 5. Milestone

File utama:

- Controller: `D:\TA 2\task-management-copy\app\Http\Controllers\MilestoneController.php`
- Service: `D:\TA 2\task-management-copy\app\Services\Implementations\MilestoneService.php`
- Repository: `D:\TA 2\task-management-copy\app\Repositories\Eloquent\MilestoneRepository.php`
- Model: `D:\TA 2\task-management-copy\app\Models\Milestone.php`

Milestone adalah tahapan besar dalam project.

Relasi:

```text
Project has many Milestones
Milestone has many Tasks
```

Di database:

```php
$table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
```

Artinya kalau project benar-benar dihapus, milestone ikut terhapus oleh cascade.

Milestone juga memakai soft delete:

```php
use SoftDeletes;
```

Alur milestone archive mirip project:

```text
DELETE /api/milestones/{id}
  -> MilestoneController::destroy()
  -> MilestoneService::deleteMilestone()
  -> MilestoneRepository::deleteMilestone()
  -> milestone->delete()
```

Restore:

```text
PATCH /api/milestones/{id}/restore
```

## 6. Task

File utama:

- Controller: `D:\TA 2\task-management-copy\app\Http\Controllers\TaskController.php`
- Service: `D:\TA 2\task-management-copy\app\Services\Implementations\TaskService.php`
- Repository: `D:\TA 2\task-management-copy\app\Repositories\Eloquent\TaskRepository.php`
- Model: `D:\TA 2\task-management-copy\app\Models\Task.php`

Task adalah pekerjaan detail di dalam project atau milestone.

Relasi:

```text
Project has many Tasks
Milestone has many Tasks
Task has many Assignments
Task has many Dependencies
Task has many Time Entries
Task has many Cost Entries
Task has many Progress Entries
```

Task status:

```php
const ALLOWED_STATUSES = ['To Do', 'In Progress', 'Done', 'On Hold', 'Cancelled'];
```

Task priority:

```php
const ALLOWED_PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];
```

### 6.1 Create task

Endpoint:

```http
POST /api/tasks
POST /api/projects/{project}/tasks
```

Saat task dibuat, service dapat menerima:

- data task
- assignments
- dependencies

Service memisahkan data tersebut:

```php
$assignments = $data['assignments'] ?? null;
$dependencies = $data['dependencies'] ?? null;
unset($data['assignments']);
unset($data['dependencies']);
```

Task dibuat lewat repository:

```php
$task = $this->repository->createTask($data);
```

Assignment disimpan:

```php
$task->assignments()->createMany($rows);
```

Dependency disimpan:

```php
$task->dependencies()->createMany($depRows);
```

### 6.2 Update task

Endpoint:

```http
PUT /api/tasks/{id}
PATCH /api/tasks/{id}
PATCH /api/tasks/{id}/status
```

Sebelum update status, backend memeriksa dependency:

```php
$this->assertDependencyStatusTransitionAllowed($currentTask, $data['status']);
```

Tujuannya agar task tidak bisa sembarangan `In Progress` atau `Done` kalau dependency belum terpenuhi.

### 6.3 Progress history

File:

```text
D:\TA 2\task-management-copy\app\Services\Implementations\TaskService.php
```

Method:

```php
protected function recordProgressEntryForToday(Task $task): void
```

Progress disimpan ke tabel `task_progress_entries`:

```php
TaskProgressEntry::updateOrCreate(
    ['task_id' => (int) $task->id, 'progress_date' => $date],
    ['percent_complete' => $pct, 'changed_by' => Auth::id()]
);
```

Ini penting untuk EVM cost-based, karena EV historis memakai progress task pada tanggal tertentu.

## 7. Task Assignment

Task assignment menyimpan siapa yang mengerjakan task.

Tabel:

```text
task_assignments
```

Kolom penting:

- `task_id`
- `user_id`
- `role_on_task`
- `estimated_effort_hours`
- `assigned_at`

Dipakai untuk:

- melihat siapa PIC/member task
- menghitung planned effort untuk EVM effort-based
- notifikasi ke assignee

Contoh di service:

```php
$task->assignments()->createMany($rows);
```

## 8. Task Dependencies

### 8.1 Apa itu dependency?

Dependency adalah hubungan antar task. Contohnya task B tidak boleh mulai sebelum task A selesai.

Tabel:

```text
task_dependencies
```

File migration:

```text
D:\TA 2\task-management-copy\database\migrations\2025_09_13_000900_create_task_dependencies_table.php
```

Kolom penting:

- `task_id`: task penerus/successor
- `depends_on_task_id`: task pendahulu/predecessor
- `type`: FS, SS, FF, SF
- `lag_days`: jeda hari

### 8.2 Jenis dependency

```text
FS = Finish to Start
Successor boleh mulai setelah predecessor selesai.

SS = Start to Start
Successor boleh mulai setelah predecessor mulai.

FF = Finish to Finish
Successor boleh selesai setelah predecessor selesai.

SF = Start to Finish
Successor boleh selesai setelah predecessor mulai.
```

### 8.3 Rumus jadwal dependency

Lokasi logic:

```text
D:\TA 2\task-management-copy\app\Services\Implementations\TaskService.php
```

Method:

```php
protected function assertDependencyScheduleAllowed(...)
```

Rumus:

```text
FS: successor.start  >= predecessor.finish + lag_days
SS: successor.start  >= predecessor.start  + lag_days
FF: successor.finish >= predecessor.finish + lag_days
SF: successor.finish >= predecessor.start  + lag_days
```

Potongan code:

```php
if ($type === 'FS' && $predecessorEnd && $successorStartDate) {
    $required = $predecessorEnd->copy()->addDays($lagDays);
    $actual = $successorStartDate;
} elseif ($type === 'SS' && $predecessorStart && $successorStartDate) {
    $required = $predecessorStart->copy()->addDays($lagDays);
    $actual = $successorStartDate;
} elseif ($type === 'FF' && $predecessorEnd && $successorEndDate) {
    $required = $predecessorEnd->copy()->addDays($lagDays);
    $actual = $successorEndDate;
} elseif ($type === 'SF' && $predecessorStart && $successorEndDate) {
    $required = $predecessorStart->copy()->addDays($lagDays);
    $actual = $successorEndDate;
}
```

### 8.4 Rumus status dependency

Lokasi logic:

```text
D:\TA 2\task-management-copy\app\Services\Implementations\TaskService.php
```

Method:

```php
protected function assertDependencyStatusTransitionAllowed(Task $task, string $targetStatus): void
```

Aturan saat task mau `In Progress`:

```text
FS: predecessor harus Done.
SS: predecessor harus sudah mulai, bukan To Do.
```

Aturan saat task mau `Done`:

```text
FS dan FF: predecessor harus Done.
SF: predecessor harus sudah mulai.
SS: dicek lag-nya dari start predecessor.
```

Lag day dicek di:

```php
protected function dependencyLagStatusViolation(...)
```

Rumus lag:

```text
allowedAt = anchorDate + lag_days
hari_ini harus >= allowedAt
```

Anchor date:

```text
FS/FF memakai finish predecessor
SS/SF memakai start predecessor
```

Potongan code:

```php
$allowedAt = $anchor->copy()->addDays($lagDays);
if (Carbon::today()->lt($allowedAt)) {
    return sprintf(
        'lag %d hari dari %s belum terpenuhi, minimal %s',
        $lagDays,
        $anchorLabel,
        $allowedAt->toDateString()
    );
}
```

Penjelasan sederhana:

```text
Kalau SS dengan lag 2 hari, successor baru boleh mulai atau selesai sesuai aturan setelah 2 hari dari start predecessor.
```

### 8.5 Frontend dependency editor

File:

```text
D:\TA 2\fe-task-management-copy\src\components\tasks\TaskDependencyEditor.tsx
```

User bisa memilih:

- predecessor task
- type FS/SS/FF/SF
- lag days

Frontend juga melakukan validasi ringan di edit task:

```text
D:\TA 2\fe-task-management-copy\src\app\dashboard\tasks\[id]\edit\page.tsx
```

Catatan penting:

```text
Validasi frontend hanya membantu UX.
Validasi yang benar-benar wajib tetap di backend TaskService.
```

## 9. Gantt Chart

### 9.1 Lokasi frontend

Halaman:

```text
D:\TA 2\fe-task-management-copy\src\app\dashboard\projects\[id]\gantt\page.tsx
```

Komponen:

```text
D:\TA 2\fe-task-management-copy\src\components\gantt\GanttChart.tsx
```

### 9.2 Data yang dipakai

Halaman Gantt mengambil:

```ts
const [t, m] = await Promise.all([
  listTasksByProject(id).catch(() => []),
  listMilestonesByProject(id).catch(() => []),
]);
```

Task API dipanggil dengan include dependencies:

```text
D:\TA 2\fe-task-management-copy\src\lib\api\tasks.ts
```

Tujuannya agar Gantt bisa menggambar garis dependency.

Gantt Chart memakai data task terbaru/current plan. Jadi jika task baru dibuat, jadwal task diedit, atau dependency berubah, Gantt langsung mengikuti kondisi terbaru. Baseline tidak dipakai untuk mengganti tampilan Gantt, karena baseline berfungsi sebagai snapshot pembanding EVM.

### 9.3 Logic menggambar dependency

File:

```text
D:\TA 2\fe-task-management-copy\src\components\gantt\GanttChart.tsx
```

Komponen:

```ts
function DependenciesOverlay(...)
```

Logic anchor garis:

```ts
const startsFromStart = type === 'SS' || type === 'SF';
const endsAtFinish = type === 'FF' || type === 'SF';
const x1 = startsFromStart ? pred.x : pred.x + pred.w;
const x2 = endsAtFinish ? succ.x + succ.w : succ.x;
```

Artinya:

```text
FS: garis dari finish predecessor ke start successor
SS: garis dari start predecessor ke start successor
FF: garis dari finish predecessor ke finish successor
SF: garis dari start predecessor ke finish successor
```

### 9.4 Alasan Gantt Chart ada di sistem

Gantt Chart dibutuhkan karena sistem ini bukan hanya daftar task, tetapi sistem project management.

Manfaat:

- Menampilkan timeline task secara visual.
- Menunjukkan urutan kerja.
- Menampilkan dependency antar task.
- Membantu melihat keterlambatan rencana.
- Membantu dosen/user memahami hubungan milestone, task, dan jadwal.

Walaupun ada Gantt Chart gratis di luar, fitur ini tetap penting karena:

```text
Gantt di sistem ini terhubung langsung dengan data project, task, dependencies, progress, dan EVM.
```

Jadi bukan hanya gambar jadwal, tetapi bagian dari logic sistem.

## 10. EVM Effort-Based

EVM effort-based menghitung performa berdasarkan effort/jam kerja.

File backend:

```text
D:\TA 2\task-management-copy\app\Http\Controllers\EvmController.php
D:\TA 2\task-management-copy\app\Services\Implementations\EvmService.php
```

Endpoint:

```http
GET /api/projects/{project}/evm?date=YYYY-MM-DD&baseline_id=ID
```

### 10.1 Data sumber

EVM effort-based memakai:

- Jika baseline dipilih, `task_baselines.planned_effort_hours` sebagai planned effort snapshot.
- Jika baseline tidak dipilih, `task_assignments.estimated_effort_hours` sebagai planned effort current.
- `duration_planned * 8 jam` sebagai fallback terakhir jika effort assignment kosong.
- `time_entries.hours` sebagai actual effort.
- `task_progress_entries.percent_complete` sebagai progress historis sampai tanggal EVM.
- `tasks.percent_complete` hanya dipakai sebagai fallback untuk query hari ini jika progress history belum ada.

### 10.2 Rumus effort-based

```text
Planned Effort = estimated_effort_hours
PV = Planned Effort * Planned Fraction
EV = Planned Effort * Percent Complete
AC = Actual Hours

SV = EV - PV
SPI = EV / PV
CV = EV - AC
CPI = EV / AC
```

Jika baseline dipilih:

```text
Planned Effort = task_baselines.planned_effort_hours
Start/Duration = start_planned_base dan duration_planned_base
```

Jika baseline tidak dipilih:

```text
Planned Effort = sum(task_assignments.estimated_effort_hours)
Fallback = duration_planned * 8 jam
```

Di code:

```php
$pv = $plannedEffort * $fraction;
$ev = $plannedEffort * ($pct / 100);
$ac = (float) ($acSums[$taskId] ?? 0.0);

$sv = $totalEV - $totalPV;
$spi = ($totalPV > 0.0) ? ($totalEV / $totalPV) : null;
$cv = $totalEV - $totalAC;
$cpi = ($totalAC > 0.0) ? ($totalEV / $totalAC) : null;
```

### 10.3 Planned fraction

Rumus planned fraction:

```text
elapsedDays = tanggal_as_of - start_planned + 1
fraction = elapsedDays / duration_planned
fraction dibatasi 0 sampai 1
```

Kenapa +1?

```text
Agar pada hari pertama task, PV tidak 0.
```

## 11. EVM Cost-Based atau IDR

EVM cost-based adalah EVM berbasis uang/rupiah.

File backend:

```text
D:\TA 2\task-management-copy\app\Http\Controllers\EvmCostController.php
D:\TA 2\task-management-copy\app\Services\Implementations\EvmCostService.php
```

Endpoint:

```http
GET /api/projects/{project}/evm-cost?as_of=YYYY-MM-DD&baseline_id=ID
```

File frontend widget:

```text
D:\TA 2\fe-task-management-copy\src\components\evm\EvmCostWidget.tsx
```

### 11.1 Data sumber cost-based

EVM cost-based memakai:

- Jika baseline dipilih, BAC/PV/EV memakai snapshot `task_baselines.budget_cost_base`.
- Jika baseline tidak dipilih, BAC memakai `projects.value_amount` jika tersedia, atau total `tasks.budget_cost`.
- Jika baseline tidak dipilih, PV dan EV memakai `tasks.budget_cost`.
- `task_cost_entries.amount` untuk AC.
- `task_progress_entries.percent_complete` untuk EV historis.
- `task_baselines.start_planned_base` dan `duration_planned_base` untuk jadwal baseline jika baseline dipilih.

### 11.2 Rumus utama

```text
BAC = Budget at Completion
PV = Planned Value
EV = Earned Value
AC = Actual Cost

SV = EV - PV
CV = EV - AC
SPI = EV / PV
CPI = EV / AC
EAC = BAC / CPI
ETC = EAC - AC
```

Di code:

```php
$pv = $budgetCost * $fraction;
$ev = $budgetCost * ($pct / 100);
$ac = (float) ($acSums[$taskId] ?? 0.0);

$sv = $totalEV - $totalPV;
$spi = ($totalPV > 0.0) ? ($totalEV / $totalPV) : null;
$cv = $totalEV - $totalAC;
$cpi = ($totalAC > 0.0) ? ($totalEV / $totalAC) : null;

$bac = $projectValue > 0 ? $projectValue : $sumBudgetCost;

if ($cpi !== null && $cpi > 0.0) {
    $eac = $bac / $cpi;
    $etc = $eac - $totalAC;
}
```

Untuk baseline:

```text
BAC = sum(task_baselines.budget_cost_base)
PV/EV = task_baselines.budget_cost_base
```

Saat baseline baru dibuat, `budget_cost_base` diambil dari budget task saat itu. Nilainya tidak diskalakan lagi ke `projects.value_amount`, sehingga jika total budget task terbaru Rp 1.300.000 maka BAC baseline baru juga Rp 1.300.000.

### 11.3 Arti angka EVM

```text
SPI < 1  = jadwal terlambat
SPI = 1  = sesuai jadwal
SPI > 1  = lebih cepat dari jadwal

CPI < 1  = biaya boros
CPI = 1  = biaya sesuai rencana
CPI > 1  = biaya lebih efisien

SV negatif = progress tertinggal dari rencana
CV negatif = biaya aktual lebih besar dari nilai pekerjaan yang didapat
```

### 11.4 Contoh sederhana

Misal:

```text
BAC = Rp 13.000.000
PV  = Rp 9.500.000
EV  = Rp 8.200.000
AC  = Rp 6.500.000
```

Maka:

```text
SV = EV - PV
SV = 8.200.000 - 9.500.000
SV = -1.300.000
```

Artinya progress tertinggal dari rencana.

```text
SPI = EV / PV
SPI = 8.200.000 / 9.500.000
SPI = 0,86
```

Artinya project berjalan lebih lambat dari rencana.

```text
CV = EV - AC
CV = 8.200.000 - 6.500.000
CV = 1.700.000
```

Artinya dari sisi biaya masih efisien.

```text
CPI = EV / AC
CPI = 8.200.000 / 6.500.000
CPI = 1,26
```

Artinya setiap Rp 1 biaya aktual menghasilkan sekitar Rp 1,26 nilai pekerjaan.

## 12. Cost Entries

Actual cost tidak diambil dari budget, tetapi dari ledger biaya aktual.

File frontend:

```text
D:\TA 2\fe-task-management-copy\src\components\tasks\TaskCostEntriesSection.tsx
```

File backend:

```text
D:\TA 2\task-management-copy\app\Http\Controllers\TaskCostEntryController.php
D:\TA 2\task-management-copy\app\Services\Implementations\TaskCostEntryService.php
D:\TA 2\task-management-copy\app\Repositories\Eloquent\TaskCostEntryRepository.php
D:\TA 2\task-management-copy\app\Models\TaskCostEntry.php
```

Alur create cost entry:

```text
POST /api/tasks/{task}/cost-entries
  -> TaskCostEntryController::store()
  -> TaskCostEntryStoreRequest validasi input
  -> TaskCostEntryService::createCostEntry()
  -> TaskCostEntryRepository::createCostEntry()
  -> TaskHistoryLogger mencatat aktivitas cost entry ke status_histories
  -> TaskCostEntryResource mengembalikan response JSON
```

Tabel:

```text
task_cost_entries
```

Kolom penting:

- `task_id`
- `incurred_on`
- `amount`
- `category`
- `note`

Di EVM cost-based:

```php
$acSums = TaskCostEntry::query()
    ->whereIn('task_id', $taskIds)
    ->whereDate('incurred_on', '<=', $asOfDate)
    ->selectRaw('task_id, COALESCE(SUM(amount),0) as sum_amount')
    ->groupBy('task_id')
    ->pluck('sum_amount', 'task_id')
    ->toArray();
```

Rumus:

```text
AC = sum(task_cost_entries.amount sampai tanggal as_of)
```

## 13. Time Entries

Time entry menyimpan jam kerja aktual.

Tabel:

```text
time_entries
```

Dipakai untuk:

- tracking jam kerja task
- EVM effort-based sebagai AC

Rumus:

```text
AC effort-based = sum(time_entries.hours sampai tanggal EVM)
```

## 14. Baseline

Baseline adalah snapshot rencana awal.

Tabel:

```text
project_baselines
task_baselines
```

File service:

```text
D:\TA 2\task-management-copy\app\Services\Implementations\ProjectBaselineService.php
D:\TA 2\task-management-copy\app\Services\Implementations\TaskBaselineService.php
```

Baseline menyimpan:

- start planned base
- end planned base
- duration planned base
- planned effort hours
- budget cost base
- value amount base
- weight

Kenapa baseline penting?

```text
Kalau task berubah jadwal, sistem masih bisa membandingkan progress sekarang dengan rencana awal.
```

Project create otomatis membuat baseline awal.

Baseline lama bersifat immutable. Task baru dan update task tidak otomatis mengubah baseline lama. Jika task baru atau perubahan budget/effort/tanggal ingin menjadi rencana baru, user membuat baseline baru.

Aturan baseline yang dipakai sistem:

```text
Create task setelah baseline dibuat = masuk current plan, tidak masuk baseline lama.
Update task setelah baseline dibuat = mengubah current plan, tidak mengubah task_baselines lama.
Create baseline baru = mengambil snapshot task aktif terbaru, termasuk budget dan effort terbaru.
```

Untuk EVM:

```text
Baseline lama = hitung dari task_baselines lama.
Current plan = hitung dari tasks dan task_assignments terbaru.
Baseline baru = hitung dari snapshot terbaru saat baseline dibuat.
```

## 15. Reporting Period dan KPI Snapshot

Reporting period adalah tanggal pelaporan progress.

KPI snapshot menyimpan ringkasan metrik pada periode tertentu.

Tabel:

```text
reporting_periods
kpi_snapshots
```

File:

```text
D:\TA 2\task-management-copy\app\Http\Controllers\ReportingPeriodController.php
D:\TA 2\task-management-copy\app\Http\Controllers\KpiSnapshotController.php
D:\TA 2\task-management-copy\app\Services\Implementations\KpiSnapshotService.php
```

Dipakai untuk laporan project, seperti:

- total task
- task done
- average cycle time
- progress per periode

## 16. Archive, Restore, dan Permanent Delete

### 16.1 Soft delete

Soft delete dipakai untuk:

- project
- milestone
- task

Alasan:

```text
Data project management tidak boleh langsung hilang karena masih berguna untuk audit, laporan, progress, dan histori.
```

### 16.2 Restore

Restore menghapus nilai `deleted_at`, sehingga data kembali aktif.

```php
$project->restore();
```

Restore child juga memperhatikan parent:

```text
Restore milestone ditolak jika project parent masih archived.
Restore task ditolak jika project atau milestone parent masih archived.
```

Tujuannya agar data child tidak aktif sendiri ketika parent masih berada di archive.

### 16.3 Permanent delete

Permanent delete hanya untuk data archive.

Rumus:

```text
Permanent delete project =
hapus comment/attachment polymorphic project
+ hapus comment/attachment polymorphic milestone
+ hapus comment/attachment polymorphic task
+ forceDelete project
+ cascade database menghapus relasi lain
```

Cascade database menghapus:

- milestones
- tasks
- task dependencies
- task assignments
- status histories
- time entries
- task baselines
- task cost entries
- task progress entries
- project baselines
- reporting periods
- KPI snapshots

### 16.4 Dampak archive ke perhitungan

Archive tidak hanya memengaruhi tampilan list, tetapi juga data aktif untuk perhitungan.

Aturannya:

```text
Project archived = project tidak tampil di list aktif dan child-nya tidak tampil sebagai data aktif.
Milestone archived = milestone tidak tampil di list aktif dan task di bawah milestone tersebut tidak dihitung sebagai task aktif.
Task archived = task tidak tampil di list aktif dan tidak dihitung dalam EVM/KPI aktif.
```

Untuk EVM dan KPI:

```text
Task yang berada di bawah milestone archived dikeluarkan dari perhitungan EVM, SPI effort, EVM cost, dan KPI snapshot.
Task yang di-archive langsung juga dikeluarkan dari perhitungan aktif.
```

Penjelasan ke dosen:

```text
Archive dianggap sebagai data nonaktif. Karena itu data archived tidak ikut dihitung pada metrik aktif agar total task, progress, EVM, dan KPI sesuai dengan kondisi project yang sedang berjalan.
Jika archive mengubah scope pekerjaan, user bisa membuat baseline baru agar EVM berikutnya memakai scope terbaru.
```

Frontend warning ada di:

```text
D:\TA 2\fe-task-management-copy\src\app\dashboard\projects\archive\page.tsx
```

Pesan konfirmasi menjelaskan bahwa data tidak bisa di-restore.

## 17. Activity Log

Beberapa service menulis activity log saat data dibuat, diubah, archived, restored, atau completed.

Contoh di project service:

```php
activity('projects')
    ->performedOn($project)
    ->withProperties($properties)
    ->log('archived');
```

Manfaat:

- mengetahui siapa yang melakukan aksi
- audit perubahan sistem
- membantu admin memantau aktivitas

Frontend activity log:

```text
D:\TA 2\fe-task-management-copy\src\app\dashboard\activity-log\page.tsx
```

## 18. Notification dan Email

Sistem memiliki notifikasi internal dashboard dan notifikasi email.

File backend:

```text
D:\TA 2\task-management-copy\app\Notifications\TaskActivityNotification.php
D:\TA 2\task-management-copy\app\Http\Controllers\NotificationController.php
D:\TA 2\task-management-copy\app\Console\Commands\SendTaskDeadlineNotifications.php
D:\TA 2\task-management-copy\routes\console.php
D:\TA 2\task-management-copy\config\notifications.php
```

File frontend:

```text
D:\TA 2\fe-task-management-copy\src\app\dashboard\notifications\page.tsx
D:\TA 2\fe-task-management-copy\src\contexts\notification-context.tsx
D:\TA 2\fe-task-management-copy\src\lib\api\notifications.ts
```

### 18.1 Alur notification

Alur umumnya:

```text
Event terjadi di backend
  -> backend memanggil notify(new TaskActivityNotification)
  -> notification masuk ke tabel notifications
  -> jika mail aktif, notification juga dikirim ke email user
  -> frontend mengambil data dari GET /api/me/notifications
  -> sidebar menampilkan unread count
  -> user membuka halaman notifications
```

Channel default di notification:

```php
$channels = ['database'];
```

Jika email notification aktif:

```php
if (config('notifications.mail_enabled') && !empty($notifiable->email)) {
    $channels[] = 'mail';
}
```

Artinya:

```text
Setiap notifikasi tetap masuk dashboard.
Jika NOTIFICATIONS_MAIL_ENABLED=true dan user punya email, notifikasi juga dikirim ke Gmail/email.
```

### 18.2 Event yang dikirim

Event notification yang sudah didukung:

```text
task_assigned
comment_added
attachment_uploaded
attachment_approved
attachment_rejected
task_status_changed
task_progress_updated
task_due_soon
task_overdue
```

Penjelasan:

- `task_assigned`: user mendapat assignment task baru.
- `comment_added`: ada komentar baru pada task.
- `attachment_uploaded`: ada lampiran baru yang diunggah.
- `attachment_approved`: lampiran disetujui.
- `attachment_rejected`: lampiran ditolak.
- `task_status_changed`: status task berubah.
- `task_progress_updated`: progress task berubah.
- `task_due_soon`: task mendekati deadline.
- `task_overdue`: task sudah melewati deadline.

### 18.3 Deadline notification

Command deadline:

```bash
php artisan notifications:task-deadlines --days=3
```

Command ini mencari task dengan kondisi:

```text
end_planned tidak kosong
status bukan Done atau Cancelled
tanggal deadline <= hari ini + jumlah days
project parent masih aktif
milestone parent masih aktif jika task berada di milestone
```

Jika `end_planned` sudah lewat dari hari ini:

```text
event = task_overdue
```

Jika `end_planned` masih dalam rentang `days`:

```text
event = task_due_soon
```

Penerima deadline notification:

```text
assignee task
owner project
semua user yang terlibat dalam project lewat task_assignments
Admin
Super Admin
```

Command dijadwalkan di:

```text
D:\TA 2\task-management-copy\routes\console.php
```

Schedule:

```php
Schedule::command('notifications:task-deadlines --days=3')
    ->dailyAt('08:00')
    ->withoutOverlapping();
```

Catatan production:

```text
Schedule ini terdaftar di Laravel, tetapi server tetap harus menjalankan schedule runner seperti php artisan schedule:run setiap menit.
```

### 18.4 Environment email

Env penting:

```env
NOTIFICATIONS_MAIL_ENABLED=true
FRONTEND_URL=https://task-centralsaga.web.id

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=email_pengirim@gmail.com
MAIL_PASSWORD="app password gmail"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=email_pengirim@gmail.com
MAIL_FROM_NAME="Central Saga Task Management"
```

Setelah mengubah `.env`, jalankan:

```bash
php artisan config:clear
```

Kalimat sidang:

```text
Sistem menggunakan Laravel Notification. Notifikasi selalu disimpan di database untuk dashboard. Jika konfigurasi email aktif, notification yang sama juga dikirim melalui SMTP Gmail ke email user penerima.
```

## 19. Seeder Demo Project Management

File:

```text
D:\TA 2\task-management-copy\database\seeders\DemoProjectManagementSeeder.php
```

Seeder ini membuat data demo:

- project
- milestone
- task
- task assignment
- dependencies
- progress
- actual cost
- baseline
- reporting period
- KPI snapshot
- comment dan attachment

Tujuannya:

```text
Saat migrate fresh dan seed, sistem langsung punya data project management yang realistis untuk demo EVM, Gantt, dependency, dan archive.
```

User demo:

- Admin sebagai pemilik project
- Ayu Pradnya sebagai manager
- Gustra, Gung Aria, Dwiki sebagai tim software

## 20. Cara Menjelaskan ke Dosen

### 20.1 Penjelasan arsitektur

Kalimat sederhana:

```text
Sistem ini memakai arsitektur berlapis. Frontend hanya mengirim request dan menampilkan data. Backend memproses request melalui route, controller, service, repository, lalu database. Logic bisnis seperti permission, dependency, archive, dan EVM ditempatkan di backend agar tetap aman walaupun frontend dimanipulasi.
```

### 20.2 Penjelasan role permission

```text
Permission dipisah berdasarkan CRUD. Misalnya melihat project hanya untuk view, membuat project untuk create, mengubah project untuk edit, dan menghapus project untuk archive. Dengan begitu tombol di frontend bisa disembunyikan sesuai permission, dan backend tetap memvalidasi lewat middleware.
```

### 20.3 Penjelasan archive

```text
Data tidak langsung dihapus permanen. Saat delete dari halaman utama, sistem menjalankan soft delete sehingga data masuk archive. Admin masih bisa restore. Permanent delete hanya tersedia di halaman archive dengan konfirmasi, agar data penting tidak terhapus tidak sengaja.
```

### 20.4 Penjelasan dependency

```text
Task dependency digunakan untuk mengatur urutan kerja. Sistem mendukung FS, SS, FF, dan SF. Lag day digunakan sebagai jeda hari dari task pendahulu. Backend menolak perubahan status atau jadwal task jika dependency belum terpenuhi.
```

### 20.5 Penjelasan EVM

```text
EVM digunakan untuk mengukur performa project. Sistem menghitung PV, EV, AC, SV, SPI, CV, CPI, EAC, dan ETC. Untuk cost-based EVM, PV dan EV berasal dari budget task, AC berasal dari biaya aktual, dan BAC berasal dari nilai project atau total budget task.
Jika baseline dipilih, EVM memakai snapshot task_baselines agar perubahan task setelah baseline tidak mengubah rencana lama. Jika ingin rencana baru, user membuat baseline baru.
```

### 20.6 Penjelasan Gantt

```text
Gantt Chart menampilkan timeline task dan dependency berdasarkan current plan. Fitur ini bukan sekadar chart, tetapi terhubung langsung dengan data task, milestone, dependency, dan progress terbaru. Baseline tetap dipakai untuk pembanding EVM, sedangkan Gantt dipakai untuk memantau jadwal berjalan.
```

## 21. Peta File Cepat

Backend:

```text
routes/api.php
app/Http/Controllers/AuthController.php
app/Http/Controllers/ProjectController.php
app/Http/Controllers/TaskController.php
app/Http/Controllers/EvmController.php
app/Http/Controllers/EvmCostController.php
app/Services/Implementations/ProjectService.php
app/Services/Implementations/TaskService.php
app/Services/Implementations/EvmService.php
app/Services/Implementations/EvmCostService.php
app/Services/Implementations/TaskCostEntryService.php
app/Repositories/Eloquent/ProjectRepository.php
app/Repositories/Eloquent/TaskRepository.php
app/Repositories/Eloquent/TaskCostEntryRepository.php
app/Notifications/TaskActivityNotification.php
app/Console/Commands/SendTaskDeadlineNotifications.php
app/Models/User.php
app/Models/Project.php
app/Models/Task.php
routes/console.php
database/seeders/RolePermissionSeeder.php
database/seeders/DemoProjectManagementSeeder.php
```

Frontend:

```text
src/app/auth/login/page.tsx
src/contexts/auth-context.tsx
src/lib/api.ts
src/components/app-sidebar.tsx
src/config/menu.ts
src/app/dashboard/projects/page.tsx
src/app/dashboard/projects/archive/page.tsx
src/app/dashboard/projects/[id]/page.tsx
src/app/dashboard/projects/[id]/gantt/page.tsx
src/components/gantt/GanttChart.tsx
src/components/evm/EvmCostWidget.tsx
src/components/evm/EvmWidget.tsx
src/components/tasks/TaskDependencyEditor.tsx
src/app/dashboard/notifications/page.tsx
src/contexts/notification-context.tsx
src/lib/api/notifications.ts
src/app/dashboard/tasks/[id]/edit/page.tsx
```

## 22. Checklist Demo Sistem

Urutan demo yang bagus:

1. Login sebagai Admin.
2. Tunjukkan sidebar dan menu sesuai role.
3. Buka Project Dashboard.
4. Tunjukkan create/edit/detail project.
5. Buka detail project dan lihat tab milestone/task/EVM.
6. Buka task dan tunjukkan assignment.
7. Edit task dependency FS/SS/FF/SF dan lag day.
8. Coba ubah status task yang belum memenuhi dependency, sistem menolak.
9. Buka Gantt Chart dan tunjukkan garis dependency.
10. Buka EVM cost-based dan jelaskan PV, EV, AC, SPI, CPI.
11. Buka halaman Notifications dan jelaskan notif dashboard serta email.
12. Jalankan atau jelaskan command deadline notification.
13. Archive project dari halaman project.
14. Buka Project Archive.
15. Restore project.
16. Archive lagi, lalu tunjukkan tombol Permanent Delete dan peringatannya.

## 23. Catatan Penting

- Frontend permission hanya untuk tampilan dan UX.
- Backend permission adalah keamanan utama.
- Frontend dependency validation hanya membantu user.
- Backend `TaskService` adalah validasi dependency final.
- Soft delete dipakai untuk menjaga data audit.
- Permanent delete hanya untuk data yang sudah archived.
- EVM cost-based lebih cocok untuk presentasi project management karena memakai rupiah.
- EVM effort-based tetap berguna jika ingin mengukur performa berbasis jam kerja.
