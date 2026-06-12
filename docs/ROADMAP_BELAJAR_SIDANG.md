# Roadmap Belajar Sidang Task Management Central Saga

Dokumen ini dipakai sebagai urutan belajar sebelum sidang. Fokusnya bukan menghafal semua file, tetapi memahami alur sistem dari database, backend, frontend, sampai fitur project management seperti archive, notification, EVM, KPI, dan Gantt Chart.

Repo yang dipakai:

- Backend Laravel: `D:\TA 2\task-management-copy`
- Frontend Next.js: `D:\TA 2\fe-task-management-copy`

## 1. Gambaran Besar Sistem

Pelajari dulu arsitektur utama:

```text
User di browser
  -> Frontend Next.js page/component
  -> API helper
  -> Laravel route
  -> Controller
  -> Form Request
  -> Service
  -> Repository
  -> Model / Database
  -> Resource response
  -> Frontend render ulang data
```

Yang harus bisa dijelaskan:

- Frontend bertugas menampilkan UI, form, tabel, modal, toast, dan memanggil API.
- Backend memegang validasi, permission, business logic, query database, notifikasi, dan perhitungan.
- Service menyimpan logic bisnis.
- Repository menyimpan query database.
- Resource mengatur bentuk response JSON ke frontend.

File yang dibaca:

- `routes/api.php`
- `app/Providers/AppServiceProvider.php`
- `src/lib/api.ts` di repo frontend

Kalimat sidang:

```text
Sistem ini memakai arsitektur berlapis. Request dari frontend masuk ke route Laravel, diproses controller, divalidasi request class, lalu business logic dijalankan di service. Service memakai repository untuk akses database, dan response dikembalikan melalui resource agar format JSON konsisten.
```

## 2. Relasi Database dan ERD

Ini bagian yang wajib kuat karena semua fitur bergantung pada relasi tabel.

Relasi utama:

```text
Division 1..n User
User 1..n Project sebagai division owner
Project 1..n Milestone
Project 1..n Task
Milestone 1..n Task
Task n..m User lewat TaskAssignment
Task n..m Task lewat TaskDependency
Task 1..n TimeEntry
Task 1..n TaskCostEntry
Task 1..n TaskProgressEntry
Project 1..n ProjectBaseline
ProjectBaseline 1..n TaskBaseline
Project 1..n ReportingPeriod
ReportingPeriod 1..n KpiSnapshot
User 1..n Notification
```

File yang dibaca:

- `docs/ERD.md`
- `database/migrations`
- `app/Models/Project.php`
- `app/Models/Milestone.php`
- `app/Models/Task.php`
- `app/Models/TaskAssignment.php`
- `app/Models/TaskDependency.php`
- `app/Models/User.php`

Yang harus bisa dijawab:

- Kenapa `task_assignments` dipakai? Karena satu task bisa punya banyak user, dan satu user bisa mengerjakan banyak task.
- Kenapa `task_dependencies` mengarah ke tabel task yang sama? Karena dependency adalah hubungan antar task.
- Kenapa `comments` dan `attachments` polymorphic? Karena komentar/lampiran bisa ditempel ke task, project, atau milestone.
- Kenapa ada baseline? Untuk menyimpan snapshot rencana awal agar bisa dibandingkan dengan progress sekarang.
- Kenapa project, milestone, task memakai soft delete? Agar data bisa masuk archive dan masih bisa direstore.

## 3. Auth, Role, dan Permission

Alur login:

```text
User submit login
  -> Frontend memanggil POST /api/login
  -> Backend cek email, password, status aktif
  -> Backend membuat token Sanctum
  -> Frontend menyimpan token
  -> Request berikutnya membawa Bearer token
  -> Backend cek auth, status user, dan permission route
```

File yang dibaca:

- Backend:
  - `app/Http/Controllers/AuthController.php`
  - `app/Models/User.php`
  - `routes/api.php`
  - `database/seeders/RolePermissionSeeder.php`
- Frontend:
  - `src/app/auth/login/page.tsx`
  - `src/contexts/auth-context.tsx`
  - `src/lib/api.ts`
  - `middleware.ts`
  - `src/config/menu.ts`

Konsep penting:

- Token login disimpan di frontend.
- `api.ts` otomatis menambahkan `Authorization: Bearer token`.
- Route backend memakai middleware `auth:sanctum`, `active`, dan `permission`.
- Permission frontend hanya untuk UX seperti hide/show tombol.
- Permission backend adalah keamanan utama.

Contoh permission:

```text
melihat project
membuat project
mengubah project
menghapus project
melihat tugas
membuat tugas
mengubah tugas
menghapus tugas
```

Kalimat sidang:

```text
Frontend menyembunyikan menu dan tombol berdasarkan permission agar UI lebih rapi, tetapi keamanan utamanya tetap di backend karena setiap route dilindungi middleware permission.
```

## 4. Struktur Backend Laravel

Struktur folder penting:

```text
app/Http/Controllers       menerima request dan mengembalikan response
app/Http/Requests          validasi input
app/Http/Resources         format output JSON
app/Models                 representasi tabel dan relasi
app/Services/Contracts     interface service
app/Services/Implementations logic bisnis
app/Repositories/Contracts interface repository
app/Repositories/Eloquent  query database
app/Notifications          logic notifikasi database/email
app/Console/Commands       command artisan custom
database/migrations        struktur tabel
database/seeders           data awal/demo
routes/api.php             endpoint API
routes/console.php         schedule command
```

Contoh alur create task:

```text
POST /api/tasks
  -> TaskController::store()
  -> TaskStoreRequest::rules()
  -> TaskService::createTask()
  -> TaskRepository::createTask()
  -> Task model disimpan
  -> TaskResource membentuk JSON
```

File contoh yang dibaca berurutan:

- `routes/api.php`
- `app/Http/Controllers/TaskController.php`
- `app/Http/Requests/TaskStoreRequest.php`
- `app/Services/Implementations/TaskService.php`
- `app/Repositories/Eloquent/TaskRepository.php`
- `app/Models/Task.php`
- `app/Http/Resources/TaskResource.php`

## 5. Project, Milestone, dan Task

Pusat sistem adalah project management.

Alur data:

```text
Project
  -> Milestone
  -> Task
  -> Assignment
  -> Time Entry
  -> Cost Entry
  -> Progress Entry
```

Yang harus dipahami:

- Project adalah container utama pekerjaan.
- Milestone adalah tahapan atau target besar dalam project.
- Task adalah pekerjaan detail.
- Task bisa punya milestone atau langsung berada di project.
- Assignment menentukan siapa yang mengerjakan task.
- Progress menentukan persentase pekerjaan.
- Time entry menyimpan jam kerja.
- Cost entry menyimpan biaya aktual.

File backend:

- `app/Http/Controllers/ProjectController.php`
- `app/Http/Controllers/MilestoneController.php`
- `app/Http/Controllers/TaskController.php`
- `app/Services/Implementations/ProjectService.php`
- `app/Services/Implementations/MilestoneService.php`
- `app/Services/Implementations/TaskService.php`

File frontend:

- `src/app/dashboard/projects/page.tsx`
- `src/app/dashboard/projects/[id]/page.tsx`
- `src/app/dashboard/milestones/page.tsx`
- `src/app/dashboard/tasks/page.tsx`
- `src/app/dashboard/tasks/[id]/page.tsx`
- `src/lib/api/tasks.ts`
- `src/lib/api/milestones.ts`

## 6. Archive, Restore, dan Permanent Delete

Konsep:

```text
Archive = soft delete = mengisi deleted_at
Restore = mengosongkan deleted_at
Permanent delete = force delete = hapus permanen dari database
```

Aturan sistem:

- Project, milestone, dan task memakai soft delete.
- Archive project membuat project tidak tampil di list aktif.
- Archive milestone membuat task di bawah milestone tersebut tidak dihitung sebagai data aktif.
- Restore task/milestone diblokir jika parent project/milestone masih archived.
- Permanent delete hanya tersedia untuk project archived.
- Permanent delete project menghapus project dan relasi turunannya.

File backend:

- `app/Repositories/Eloquent/ProjectRepository.php`
- `app/Repositories/Eloquent/MilestoneRepository.php`
- `app/Repositories/Eloquent/TaskRepository.php`
- `tests/Feature/ArchiveBehaviorTest.php`

File frontend:

- `src/app/dashboard/projects/archive/page.tsx`
- `src/app/dashboard/milestones/archive/page.tsx`
- `src/app/dashboard/tasks/archive/page.tsx`

Kalimat sidang:

```text
Data tidak langsung dihapus permanen. Saat user menekan delete dari halaman utama, sistem menjalankan soft delete sehingga data masuk archive. Data masih bisa direstore. Permanent delete hanya tersedia di halaman archive untuk mengurangi risiko kehilangan data penting.
```

## 7. Task Assignment dan Dependency

Assignment:

```text
TaskAssignment menghubungkan task dengan user.
Kolom penting: task_id, user_id, role_on_task, estimated_effort_hours, assigned_at.
```

Dipakai untuk:

- PIC/member task.
- Notifikasi task assigned.
- Perhitungan EVM effort-based.

Dependency:

```text
TaskDependency menghubungkan task dengan task lain.
task_id = task penerus
depends_on_task_id = task pendahulu
```

Tipe dependency:

```text
FS = Finish to Start
SS = Start to Start
FF = Finish to Finish
SF = Start to Finish
```

Rumus sederhana:

```text
FS: successor.start  >= predecessor.finish + lag
SS: successor.start  >= predecessor.start  + lag
FF: successor.finish >= predecessor.finish + lag
SF: successor.finish >= predecessor.start  + lag
```

File:

- `app/Services/Implementations/TaskService.php`
- `app/Models/TaskDependency.php`
- `src/components/tasks/TaskDependencyEditor.tsx`
- `src/app/dashboard/tasks/[id]/edit/page.tsx`

Kalimat sidang:

```text
Frontend membantu user memilih dependency, tetapi validasi utama tetap di backend. Backend menolak perubahan jadwal atau status jika dependency belum terpenuhi.
```

## 8. Notification dan Email

Alur notifikasi:

```text
Event terjadi
  -> Backend memanggil notify(new TaskActivityNotification)
  -> Notifikasi disimpan ke database
  -> Jika NOTIFICATIONS_MAIL_ENABLED=true, notifikasi juga dikirim ke email
  -> Frontend mengambil notifikasi dari GET /api/me/notifications
  -> User melihat notifikasi di dashboard
```

Event yang sudah masuk notification:

- Task assigned.
- Comment added.
- Attachment uploaded.
- Attachment approved.
- Attachment rejected.
- Task status changed.
- Task progress updated.
- Task due soon.
- Task overdue.

File backend:

- `app/Notifications/TaskActivityNotification.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Console/Commands/SendTaskDeadlineNotifications.php`
- `routes/console.php`
- `config/notifications.php`

File frontend:

- `src/app/dashboard/notifications/page.tsx`
- `src/contexts/notification-context.tsx`
- `src/lib/api/notifications.ts`

Deadline notification:

```text
Command notifications:task-deadlines --days=3
  -> mencari task yang deadline-nya dekat atau lewat
  -> mengirim notif ke assignee task
  -> owner project
  -> semua member yang terlibat dalam project
  -> Admin dan Super Admin
```

Cara test:

```bash
./vendor/bin/sail artisan notifications:task-deadlines --days=3 --date=2026-06-12
```

Catatan production:

```text
Schedule sudah didaftarkan di routes/console.php, tetapi server tetap harus menjalankan Laravel scheduler.
```

## 9. Gantt Chart

Tujuan Gantt:

- Menampilkan timeline project.
- Menampilkan milestone dan task.
- Menampilkan dependency antar task.
- Membantu membaca keterlambatan secara visual.

File frontend:

- `src/app/dashboard/projects/[id]/gantt/page.tsx`
- `src/components/gantt/GanttChart.tsx`
- `src/lib/api/tasks.ts`
- `src/lib/api/milestones.ts`

Alur:

```text
Gantt page
  -> ambil project
  -> ambil milestone
  -> ambil task include dependencies
  -> komponen GanttChart menggambar bar task dan garis dependency
```

Kalimat sidang:

```text
Gantt Chart di sistem ini bukan hanya visual statis, tetapi terhubung dengan data task, milestone, jadwal, progress, dan dependency yang tersimpan di database.
```

## 10. Baseline

Baseline adalah snapshot rencana.

Dipakai untuk:

- Menyimpan jadwal awal project.
- Menyimpan jadwal awal task.
- Menjadi pembanding ketika jadwal task berubah.
- Mendukung perhitungan EVM.

Tabel:

```text
project_baselines
task_baselines
```

File:

- `app/Services/Implementations/ProjectBaselineService.php`
- `app/Services/Implementations/TaskBaselineService.php`
- `app/Http/Controllers/ProjectBaselineController.php`
- `app/Http/Controllers/TaskBaselineController.php`
- `src/app/dashboard/projects/[id]/baselines/page.tsx`

Kalimat sidang:

```text
Baseline dibutuhkan agar sistem tetap bisa membandingkan progress sekarang dengan rencana awal, walaupun jadwal task sudah pernah diubah.
```

## 11. EVM Effort-Based

EVM effort-based menghitung performa berdasarkan jam kerja.

Data yang dipakai:

- Planned effort dari `task_assignments.estimated_effort_hours`.
- Actual effort dari `time_entries.hours`.
- Percent complete dari `tasks.percent_complete`.

Rumus:

```text
PV = Planned Effort * Planned Fraction
EV = Planned Effort * Percent Complete
AC = Actual Hours
SV = EV - PV
SPI = EV / PV
CV = EV - AC
CPI = EV / AC
```

File:

- `app/Http/Controllers/EvmController.php`
- `app/Services/Implementations/EvmService.php`
- `src/components/evm/EvmWidget.tsx`
- `src/lib/api/evm.ts`

Interpretasi:

```text
SPI < 1 = terlambat dari jadwal
SPI = 1 = sesuai jadwal
SPI > 1 = lebih cepat
```

## 12. EVM Cost-Based

EVM cost-based menghitung performa berdasarkan biaya/rupiah.

Data yang dipakai:

- BAC dari `projects.value_amount` atau total `tasks.budget_cost`.
- PV dan EV dari `tasks.budget_cost`.
- AC dari `task_cost_entries.amount`.
- Progress historis dari `task_progress_entries`.

Rumus:

```text
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

File:

- `app/Http/Controllers/EvmCostController.php`
- `app/Services/Implementations/EvmCostService.php`
- `src/components/evm/EvmCostWidget.tsx`
- `src/lib/api/evm.ts`

Interpretasi:

```text
SPI < 1 = jadwal tertinggal
CPI < 1 = biaya boros
CPI > 1 = biaya efisien
SV negatif = nilai pekerjaan lebih kecil dari rencana
CV negatif = biaya aktual lebih besar dari nilai pekerjaan
```

## 13. KPI Snapshot

KPI Snapshot menyimpan ringkasan performa project pada tanggal/periode tertentu.

Data yang dihitung:

- Total task.
- Task selesai.
- Overdue task.
- Average cycle time.

File:

- `app/Http/Controllers/KpiSnapshotController.php`
- `app/Services/Implementations/KpiSnapshotService.php`
- `app/Models/KpiSnapshot.php`
- `app/Models/ReportingPeriod.php`
- `src/lib/api/kpi-snapshots.ts`

Kalimat sidang:

```text
KPI Snapshot menyimpan hasil ringkasan metrik pada periode tertentu, sehingga laporan project bisa dibaca berdasarkan histori waktu, bukan hanya kondisi saat ini.
```

## 14. Struktur Frontend Next.js

Struktur penting:

```text
src/app                      routing halaman App Router
src/app/dashboard            halaman setelah login
src/components               komponen reusable
src/contexts                 global state auth dan notification
src/lib/api.ts               pusat request HTTP
src/lib/api/*.ts             API wrapper per fitur
src/types                    definisi type TypeScript
src/config/menu.ts           konfigurasi sidebar menu
middleware.ts                guard halaman dashboard
```

Alur umum page:

```text
Page component
  -> useEffect fetch data
  -> apiRequest memanggil backend
  -> data disimpan di useState
  -> render table/card/form
  -> action button memanggil API lagi
  -> tampilkan toast
```

File yang wajib dipahami:

- `src/app/layout.tsx`
- `src/app/dashboard/layout.tsx`
- `src/app/auth/login/page.tsx`
- `src/contexts/auth-context.tsx`
- `src/contexts/notification-context.tsx`
- `src/lib/api.ts`
- `src/lib/config.ts`
- `middleware.ts`
- `src/components/app-sidebar.tsx`
- `src/config/menu.ts`

## 15. Alur Frontend yang Harus Bisa Dijelaskan

Login:

```text
login page
  -> AuthProvider.login()
  -> apiRequest POST /api/login
  -> simpan token, user, roles, permissions
  -> redirect dashboard
```

List task:

```text
tasks/page.tsx
  -> GET /api/tasks
  -> map response ke row table
  -> render DataTable
```

Archive task:

```text
Klik archive
  -> confirm dialog
  -> DELETE /api/tasks/{id}
  -> refresh list
  -> toast berhasil
```

Notification:

```text
NotificationProvider
  -> GET /api/me/notifications
  -> simpan unread count
  -> sidebar menampilkan badge
```

## 16. Deployment dan Environment

Yang harus dipahami:

- Backend lokal memakai Laravel Sail/Docker.
- Frontend lokal memakai Next.js.
- Production dipush ke GitHub lalu Dokploy deploy.
- `.env` tidak boleh dipush.
- `.env.example` boleh dipush sebagai template.

Env penting backend:

```env
APP_URL
CORS_ALLOWED_ORIGINS
FRONTEND_URL
DB_CONNECTION
DB_HOST
NOTIFICATIONS_MAIL_ENABLED
MAIL_MAILER
MAIL_HOST
MAIL_USERNAME
MAIL_PASSWORD
```

Env penting frontend:

```env
NEXT_PUBLIC_API_BASE_URL
NEXT_PUBLIC_API_URL
NEXT_PUBLIC_USE_SANCTUM
NEXT_PUBLIC_PROXY_API
```

Kalimat sidang:

```text
Konfigurasi sensitif seperti database password dan Gmail app password disimpan di .env, bukan di repository. Repository hanya menyimpan .env.example sebagai contoh konfigurasi.
```

## 17. Urutan Belajar 7 Hari

Hari 1:

- Baca arsitektur umum.
- Pahami route, controller, service, repository.
- Ikuti satu alur `GET /api/tasks`.

Hari 2:

- Baca ERD.
- Hafalkan relasi project, milestone, task, user, assignment, dependency.
- Buka migration dan model terkait.

Hari 3:

- Pelajari login, token, role, permission.
- Coba jelaskan bedanya permission frontend dan backend.

Hari 4:

- Pelajari CRUD project, milestone, task.
- Pelajari archive, restore, permanent delete.

Hari 5:

- Pelajari task assignment, dependency, status history.
- Pelajari Gantt Chart dan hubungan dependency.

Hari 6:

- Pelajari baseline, EVM effort, EVM cost, KPI snapshot.
- Latihan menjelaskan rumus PV, EV, AC, SPI, CPI.

Hari 7:

- Pelajari notification, email, scheduler.
- Latihan demo dari login sampai laporan.
- Siapkan jawaban untuk pertanyaan dosen.

## 18. Checklist Demo Sidang

Urutan demo yang disarankan:

1. Login sebagai admin.
2. Tunjukkan sidebar dan permission.
3. Buka dashboard project.
4. Tunjukkan project, milestone, task.
5. Buka detail project.
6. Buka task dan tunjukkan assignee.
7. Tunjukkan dependency task.
8. Buka Gantt Chart.
9. Tunjukkan EVM cost dan jelaskan SPI/CPI.
10. Tunjukkan KPI/report.
11. Tunjukkan notification.
12. Tunjukkan archive dan restore.
13. Jelaskan hard delete hanya untuk project archived.

## 19. Pertanyaan Dosen yang Mungkin Muncul

### Kenapa memakai service dan repository?

Jawaban:

```text
Agar controller tidak terlalu penuh. Controller hanya menerima request dan mengembalikan response. Business logic ditempatkan di service, sedangkan query database ditempatkan di repository. Dengan begitu struktur code lebih rapi dan mudah diuji.
```

### Kenapa validasi tidak cukup di frontend?

Jawaban:

```text
Frontend bisa dimanipulasi lewat browser atau API client. Karena itu validasi utama harus ada di backend, terutama untuk permission, dependency task, status, dan relasi data.
```

### Kenapa pakai soft delete?

Jawaban:

```text
Data project management penting untuk audit dan histori. Soft delete membuat data masuk archive sehingga masih bisa direstore. Permanent delete hanya dilakukan dari halaman archive dengan konfirmasi.
```

### Bagaimana task terlambat terdeteksi?

Jawaban:

```text
Command deadline notification mencari task yang punya end_planned, statusnya belum Done atau Cancelled, dan tanggal deadline-nya sudah dekat atau lewat. Jika lewat dari tanggal hari ini, event-nya menjadi task_overdue.
```

### Bagaimana email notification bekerja?

Jawaban:

```text
Semua notifikasi activity memakai TaskActivityNotification. Channel default-nya database. Jika NOTIFICATIONS_MAIL_ENABLED=true dan user punya email, channel mail juga ditambahkan sehingga notifikasi dikirim ke Gmail.
```

### Apa arti SPI dan CPI?

Jawaban:

```text
SPI mengukur performa jadwal, rumusnya EV dibagi PV. Jika SPI di bawah 1 berarti terlambat. CPI mengukur performa biaya, rumusnya EV dibagi AC. Jika CPI di bawah 1 berarti biaya boros.
```

### Kenapa ada baseline?

Jawaban:

```text
Baseline menyimpan rencana awal. Jika jadwal task berubah, sistem tetap bisa membandingkan progress saat ini dengan rencana awal.
```

## 20. File Prioritas untuk Dibaca

Backend prioritas:

```text
routes/api.php
routes/console.php
app/Providers/AppServiceProvider.php
app/Models/User.php
app/Models/Project.php
app/Models/Milestone.php
app/Models/Task.php
app/Models/TaskAssignment.php
app/Models/TaskDependency.php
app/Http/Controllers/AuthController.php
app/Http/Controllers/ProjectController.php
app/Http/Controllers/TaskController.php
app/Services/Implementations/ProjectService.php
app/Services/Implementations/TaskService.php
app/Services/Implementations/EvmService.php
app/Services/Implementations/EvmCostService.php
app/Services/Implementations/KpiSnapshotService.php
app/Notifications/TaskActivityNotification.php
app/Console/Commands/SendTaskDeadlineNotifications.php
```

Frontend prioritas:

```text
src/app/layout.tsx
src/app/dashboard/layout.tsx
src/app/auth/login/page.tsx
src/contexts/auth-context.tsx
src/contexts/notification-context.tsx
src/lib/api.ts
src/lib/config.ts
middleware.ts
src/config/menu.ts
src/components/app-sidebar.tsx
src/app/dashboard/projects/page.tsx
src/app/dashboard/projects/[id]/page.tsx
src/app/dashboard/tasks/page.tsx
src/app/dashboard/tasks/[id]/page.tsx
src/app/dashboard/notifications/page.tsx
src/components/gantt/GanttChart.tsx
src/components/evm/EvmWidget.tsx
src/components/evm/EvmCostWidget.tsx
```

## 21. Target Akhir Belajar

Setelah mengikuti roadmap ini, kamu minimal harus bisa menjelaskan:

- Struktur database dan relasinya.
- Alur request dari frontend sampai database.
- Kenapa backend memakai controller, request, service, repository, resource.
- Cara auth, role, dan permission bekerja.
- Cara project, milestone, dan task dikelola.
- Cara archive dan restore bekerja.
- Cara dependency task divalidasi.
- Cara notification masuk web dan email.
- Cara Gantt mengambil dan menampilkan data.
- Cara EVM dan KPI dihitung.
- Cara deployment dan env bekerja.

Jika semua poin ini bisa dijelaskan, kamu sudah punya pegangan yang kuat untuk sidang.
