# ERD Gaya Chen - Task Management Central Saga

Dokumen ini menjelaskan ERD dengan pendekatan Chen:

- Entity digambar sebagai persegi panjang.
- Relationship digambar sebagai diamond.
- Attribute digambar sebagai oval.
- Primary key diberi garis bawah.
- Foreign key tetap dicatat sebagai atribut implementasi database.
- Associative entity dipakai untuk relasi many-to-many yang punya atribut sendiri.

Dokumen ini bisa dipakai sebagai bahan menggambar ulang di draw.io, diagrams.net, PowerPoint, atau alat ERD lain.

## Notasi Kardinalitas

| Notasi | Arti |
| --- | --- |
| 1 : N | satu data berhubungan dengan banyak data |
| M : N | banyak data berhubungan dengan banyak data |
| 0..1 | boleh kosong atau maksimal satu |
| 0..N | boleh kosong atau banyak |
| 1..N | minimal satu dan bisa banyak |

## Kelompok Entity

Sistem ini bisa dibagi menjadi 7 kelompok besar:

1. User, divisi, role, permission.
2. Project, milestone, task.
3. Assignment dan dependency task.
4. Progress, time entry, actual cost, status history.
5. Baseline dan EVM.
6. Reporting period dan KPI snapshot.
7. Comment, attachment, notification, activity log.

## Entity Utama dan Atribut

### 1. Division

Entity: `DIVISION`

Atribut:

- __id__
- code
- name
- description
- status
- created_at
- updated_at

Makna:

> Menyimpan divisi perusahaan, misalnya Software dan Creative.

### 2. User

Entity: `USER`

Atribut:

- __id__
- division_id
- name
- email
- email_verified_at
- password_hash
- job_title
- is_active
- last_login_at
- status
- remember_token
- created_at
- updated_at

Makna:

> Menyimpan akun pengguna sistem, baik admin, manager, maupun member.

### 3. Role

Entity: `ROLE`

Atribut:

- __id__
- name
- guard_name
- status
- created_at
- updated_at

Makna:

> Menyimpan role sistem, misalnya Admin, Manager, dan Member.

### 4. Permission

Entity: `PERMISSION`

Atribut:

- __id__
- name
- guard_name
- status
- created_at
- updated_at

Makna:

> Menyimpan hak akses granular, misalnya melihat tugas, membuat tugas, melihat laporan pribadi, dan mengelola lampiran.

### 5. Model Has Role

Entity: `MODEL_HAS_ROLE`

Atribut:

- __role_id__
- __model_type__
- __model_id__

Makna:

> Associative entity dari package Spatie Permission. Menghubungkan user dengan role secara polymorphic.

Untuk sistem ini, `model_type` umumnya berisi `App\Models\User`.

### 6. Model Has Permission

Entity: `MODEL_HAS_PERMISSION`

Atribut:

- __permission_id__
- __model_type__
- __model_id__

Makna:

> Associative entity untuk memberi permission langsung ke user/model tertentu.

### 7. Role Has Permission

Entity: `ROLE_HAS_PERMISSION`

Atribut:

- __role_id__
- __permission_id__

Makna:

> Associative entity untuk relasi many-to-many antara role dan permission.

### 8. Project

Entity: `PROJECT`

Atribut:

- __id__
- division_owner_id
- name
- client_name
- value_amount
- scope
- objective
- start_planned
- end_planned
- status
- deleted_at
- created_at
- updated_at

Makna:

> Menyimpan data project. `division_owner_id` menunjuk ke user yang bertanggung jawab sebagai owner/project manager.

### 9. Milestone

Entity: `MILESTONE`

Atribut:

- __id__
- project_id
- name
- due_planned
- due_actual
- status
- deleted_at
- created_at
- updated_at

Makna:

> Menyimpan fase atau target besar dalam project. Satu project bisa punya banyak milestone.

### 10. Task

Entity: `TASK`

Atribut:

- __id__
- project_id
- milestone_id
- title
- description
- priority
- status
- start_planned
- end_planned
- duration_planned
- start_actual
- end_actual
- duration_actual
- percent_complete
- budget_cost
- deleted_at
- created_at
- updated_at

Makna:

> Menyimpan pekerjaan detail. Task wajib berada pada project, tetapi milestone boleh kosong.

### 11. Task Assignment

Entity: `TASK_ASSIGNMENT`

Atribut:

- __id__
- task_id
- user_id
- role_on_task
- estimated_effort_hours
- assigned_at
- created_at
- updated_at

Makna:

> Associative entity antara task dan user. Relasi ini punya atribut tambahan seperti role di task dan estimasi effort.

Catatan:

> Ini yang membuat relasi Task dan User menjadi M:N.

### 12. Task Dependency

Entity: `TASK_DEPENDENCY`

Atribut:

- __id__
- task_id
- depends_on_task_id
- type
- lag_days
- created_at
- updated_at

Makna:

> Associative entity untuk relasi task dengan task lain. `task_id` adalah task yang bergantung, sedangkan `depends_on_task_id` adalah task prasyarat.

Jenis dependency:

- FS: Finish to Start.
- SS: Start to Start.
- FF: Finish to Finish.
- SF: Start to Finish.

### 13. Status History

Entity: `STATUS_HISTORY`

Atribut:

- __id__
- task_id
- from_status
- to_status
- changed_by
- note
- created_at
- updated_at

Makna:

> Menyimpan riwayat perubahan status task. Dipakai juga untuk KPI snapshot agar status historis tidak salah.

### 14. Time Entry

Entity: `TIME_ENTRY`

Atribut:

- __id__
- task_id
- user_id
- date
- hours
- note
- created_at
- updated_at

Makna:

> Menyimpan jam kerja aktual user pada task. Dipakai sebagai AC pada EVM effort-based.

### 15. Task Cost Entry

Entity: `TASK_COST_ENTRY`

Atribut:

- __id__
- task_id
- incurred_on
- amount
- category
- note
- created_at
- updated_at

Makna:

> Menyimpan biaya aktual pada task. Dipakai sebagai AC pada EVM cost-based.

### 16. Task Progress Entry

Entity: `TASK_PROGRESS_ENTRY`

Atribut:

- __id__
- task_id
- progress_date
- percent_complete
- changed_by
- created_at
- updated_at

Makna:

> Menyimpan histori progress task per tanggal. Dipakai untuk menghitung EV sesuai tanggal laporan.

### 17. Project Baseline

Entity: `PROJECT_BASELINE`

Atribut:

- __id__
- project_id
- baseline_name
- taken_at
- note
- start_planned_base
- end_planned_base
- value_amount_base
- created_at
- updated_at

Makna:

> Menyimpan snapshot rencana project pada waktu tertentu.

### 18. Task Baseline

Entity: `TASK_BASELINE`

Atribut:

- __id__
- baseline_id
- task_id
- start_planned_base
- end_planned_base
- duration_planned_base
- weight
- planned_effort_hours
- budget_cost_base
- created_at
- updated_at

Makna:

> Menyimpan snapshot rencana tiap task dalam baseline project. Dipakai oleh EVM baseline.

### 19. Reporting Period

Entity: `REPORTING_PERIOD`

Atribut:

- __id__
- project_id
- period_date
- note
- created_at
- updated_at

Makna:

> Menyimpan tanggal/periode laporan KPI.

### 20. KPI Snapshot

Entity: `KPI_SNAPSHOT`

Atribut:

- __id__
- project_id
- period_id
- tasks_total
- tasks_done
- overdue_count
- avg_cycle_time_days
- created_at
- updated_at

Makna:

> Menyimpan hasil ringkasan KPI project pada reporting period tertentu.

### 21. Comment

Entity: `COMMENT`

Atribut:

- __id__
- entity_type
- entity_id
- user_id
- content
- created_at
- updated_at

Makna:

> Menyimpan komentar. Komentar memakai polymorphic relationship, sehingga bisa melekat ke Project, Milestone, atau Task.

### 22. Attachment

Entity: `ATTACHMENT`

Atribut:

- __id__
- entity_type
- entity_id
- uploaded_by
- filename
- mime
- storage_path
- size
- uploaded_at
- status
- verified_by
- verified_at
- created_at
- updated_at

Makna:

> Menyimpan file/lampiran. Attachment juga polymorphic, sehingga bisa melekat ke Project, Milestone, atau Task.

### 23. Notification

Entity: `NOTIFICATION`

Atribut:

- __id__
- type
- notifiable_type
- notifiable_id
- data
- read_at
- created_at
- updated_at

Makna:

> Menyimpan notifikasi user. `notifiable_type` dan `notifiable_id` adalah polymorphic target penerima notifikasi. Pada sistem ini umumnya penerima adalah User.

### 24. Personal Access Token

Entity: `PERSONAL_ACCESS_TOKEN`

Atribut:

- __id__
- tokenable_type
- tokenable_id
- name
- token
- abilities
- last_used_at
- expires_at
- created_at
- updated_at

Makna:

> Menyimpan token autentikasi Laravel Sanctum.

### 25. Activity Log

Entity: `ACTIVITY_LOG`

Atribut:

- __id__
- log_name
- description
- subject_type
- subject_id
- causer_type
- causer_id
- properties
- event
- batch_uuid
- created_at
- updated_at

Makna:

> Menyimpan log aktivitas sistem. `subject` adalah objek yang dikenai aktivitas, `causer` adalah pelaku aktivitas.

## Relationship Chen

Bagian ini adalah daftar relationship yang digambar sebagai diamond.

### A. User, Division, Role, Permission

#### 1. Division - User

Relationship: `MEMILIKI`

```text
DIVISION (1) -- MEMILIKI -- (0..N) USER
```

Makna:

- Satu division bisa punya banyak user.
- Satu user berada pada satu division atau bisa kosong.

Implementasi:

- FK: `users.division_id`

#### 2. User - Project

Relationship: `MENJADI_OWNER`

```text
USER (1) -- MENJADI_OWNER -- (0..N) PROJECT
```

Makna:

- Satu user bisa menjadi owner banyak project.
- Satu project punya satu owner user melalui `division_owner_id`.

Implementasi:

- FK: `projects.division_owner_id`

Catatan:

> Nama kolomnya `division_owner_id`, tetapi isinya adalah `users.id`, bukan `divisions.id`.

#### 3. User - Role

Relationship: `MEMILIKI_ROLE`

```text
USER (M) -- MEMILIKI_ROLE -- (N) ROLE
```

Associative entity:

- `MODEL_HAS_ROLE`

Makna:

- Satu user bisa punya banyak role.
- Satu role bisa dimiliki banyak user.

Implementasi:

- `model_has_roles.model_id`
- `model_has_roles.model_type`
- `model_has_roles.role_id`

#### 4. Role - Permission

Relationship: `MEMILIKI_PERMISSION`

```text
ROLE (M) -- MEMILIKI_PERMISSION -- (N) PERMISSION
```

Associative entity:

- `ROLE_HAS_PERMISSION`

Makna:

- Satu role punya banyak permission.
- Satu permission bisa dipakai banyak role.

#### 5. User - Permission

Relationship: `MEMILIKI_PERMISSION_LANGSUNG`

```text
USER (M) -- MEMILIKI_PERMISSION_LANGSUNG -- (N) PERMISSION
```

Associative entity:

- `MODEL_HAS_PERMISSION`

Makna:

- User bisa diberi permission langsung tanpa lewat role.
- Dalam pemakaian utama sistem, permission biasanya datang dari role.

### B. Project, Milestone, Task

#### 6. Project - Milestone

Relationship: `MEMILIKI_MILESTONE`

```text
PROJECT (1) -- MEMILIKI_MILESTONE -- (0..N) MILESTONE
```

Makna:

- Satu project bisa punya banyak milestone.
- Satu milestone wajib milik satu project.

Implementasi:

- FK: `milestones.project_id`

#### 7. Project - Task

Relationship: `MEMILIKI_TASK`

```text
PROJECT (1) -- MEMILIKI_TASK -- (0..N) TASK
```

Makna:

- Satu project bisa punya banyak task.
- Satu task wajib milik satu project.

Implementasi:

- FK: `tasks.project_id`

#### 8. Milestone - Task

Relationship: `MENGELOMPOKKAN_TASK`

```text
MILESTONE (1) -- MENGELOMPOKKAN_TASK -- (0..N) TASK
```

Makna:

- Satu milestone bisa punya banyak task.
- Satu task boleh tidak punya milestone.

Implementasi:

- FK nullable: `tasks.milestone_id`

Catatan:

> Karena `milestone_id` nullable, task sederhana tetap bisa dibuat langsung di level project.

### C. Assignment dan Dependency

#### 9. Task - User melalui Task Assignment

Relationship: `DITUGASKAN_KE`

```text
TASK (M) -- DITUGASKAN_KE -- (N) USER
```

Associative entity:

- `TASK_ASSIGNMENT`

Atribut relationship:

- role_on_task
- estimated_effort_hours
- assigned_at

Makna:

- Satu task bisa dikerjakan banyak user.
- Satu user bisa mendapat banyak task.
- Estimated effort dipakai untuk EVM effort-based.

#### 10. Task - Task melalui Task Dependency

Relationship: `BERGANTUNG_PADA`

```text
TASK (M) -- BERGANTUNG_PADA -- (N) TASK
```

Associative entity:

- `TASK_DEPENDENCY`

Atribut relationship:

- type
- lag_days

Makna:

- Satu task bisa bergantung pada banyak task lain.
- Satu task bisa menjadi prasyarat bagi banyak task lain.

Contoh:

> Task B baru boleh dimulai jika Task A selesai. Maka `task_id = B`, `depends_on_task_id = A`, `type = FS`.

### D. Riwayat, Progress, Time, Cost

#### 11. Task - Status History

Relationship: `MENCATAT_STATUS`

```text
TASK (1) -- MENCATAT_STATUS -- (0..N) STATUS_HISTORY
```

Makna:

- Satu task punya banyak riwayat status.
- Satu status history hanya milik satu task.

#### 12. User - Status History

Relationship: `MENGUBAH_STATUS`

```text
USER (1) -- MENGUBAH_STATUS -- (0..N) STATUS_HISTORY
```

Makna:

- Satu user bisa mengubah status banyak kali.
- Satu status history dicatat oleh satu user.

Implementasi:

- FK: `status_histories.changed_by`

#### 13. Task - Task Progress Entry

Relationship: `MEMILIKI_PROGRESS`

```text
TASK (1) -- MEMILIKI_PROGRESS -- (0..N) TASK_PROGRESS_ENTRY
```

Makna:

- Satu task punya banyak entry progress.
- Progress dipakai sebagai sumber EV.

#### 14. User - Task Progress Entry

Relationship: `MENGUBAH_PROGRESS`

```text
USER (1) -- MENGUBAH_PROGRESS -- (0..N) TASK_PROGRESS_ENTRY
```

Implementasi:

- FK: `task_progress_entries.changed_by`

#### 15. Task - Time Entry

Relationship: `MEMILIKI_TIME_ENTRY`

```text
TASK (1) -- MEMILIKI_TIME_ENTRY -- (0..N) TIME_ENTRY
```

Makna:

- Satu task punya banyak catatan waktu.
- Time entry dipakai sebagai AC pada EVM effort.

#### 16. User - Time Entry

Relationship: `MENGISI_WAKTU`

```text
USER (1) -- MENGISI_WAKTU -- (0..N) TIME_ENTRY
```

Makna:

- Satu user bisa mengisi banyak time entry.
- Satu time entry diisi oleh satu user.

#### 17. Task - Task Cost Entry

Relationship: `MEMILIKI_BIAYA_AKTUAL`

```text
TASK (1) -- MEMILIKI_BIAYA_AKTUAL -- (0..N) TASK_COST_ENTRY
```

Makna:

- Satu task punya banyak catatan biaya aktual.
- Cost entry dipakai sebagai AC pada EVM cost.

### E. Baseline dan EVM

#### 18. Project - Project Baseline

Relationship: `MEMILIKI_BASELINE`

```text
PROJECT (1) -- MEMILIKI_BASELINE -- (0..N) PROJECT_BASELINE
```

Makna:

- Satu project bisa punya banyak baseline.
- Satu baseline milik satu project.

#### 19. Project Baseline - Task Baseline

Relationship: `MEMUAT_TASK_BASELINE`

```text
PROJECT_BASELINE (1) -- MEMUAT_TASK_BASELINE -- (0..N) TASK_BASELINE
```

Makna:

- Satu project baseline berisi snapshot banyak task.
- Satu task baseline berada dalam satu project baseline.

#### 20. Task - Task Baseline

Relationship: `DISNAPSHOT_DALAM_BASELINE`

```text
TASK (1) -- DISNAPSHOT_DALAM_BASELINE -- (0..N) TASK_BASELINE
```

Makna:

- Satu task bisa punya banyak snapshot baseline.
- Satu task baseline menunjuk satu task.

### F. Reporting dan KPI

#### 21. Project - Reporting Period

Relationship: `MEMILIKI_PERIODE_LAPORAN`

```text
PROJECT (1) -- MEMILIKI_PERIODE_LAPORAN -- (0..N) REPORTING_PERIOD
```

Makna:

- Satu project punya banyak periode laporan.
- Satu reporting period milik satu project.

#### 22. Project - KPI Snapshot

Relationship: `MEMILIKI_KPI_SNAPSHOT`

```text
PROJECT (1) -- MEMILIKI_KPI_SNAPSHOT -- (0..N) KPI_SNAPSHOT
```

Makna:

- Satu project punya banyak KPI snapshot.
- Satu KPI snapshot milik satu project.

#### 23. Reporting Period - KPI Snapshot

Relationship: `MENGHASILKAN_KPI`

```text
REPORTING_PERIOD (1) -- MENGHASILKAN_KPI -- (0..N) KPI_SNAPSHOT
```

Makna:

- Satu reporting period bisa menghasilkan KPI snapshot.
- Kombinasi `project_id + period_id` dibuat unik, sehingga untuk satu project dan satu period idealnya hanya satu snapshot.

### G. Comment dan Attachment Polymorphic

#### 24. User - Comment

Relationship: `MENULIS_KOMENTAR`

```text
USER (1) -- MENULIS_KOMENTAR -- (0..N) COMMENT
```

Makna:

- Satu user bisa menulis banyak komentar.
- Satu komentar ditulis oleh satu user.

#### 25. Project/Milestone/Task - Comment

Relationship: `DIKOMENTARI`

```text
PROJECT   (1) -- DIKOMENTARI -- (0..N) COMMENT
MILESTONE (1) -- DIKOMENTARI -- (0..N) COMMENT
TASK      (1) -- DIKOMENTARI -- (0..N) COMMENT
```

Implementasi:

- `comments.entity_type`
- `comments.entity_id`

Makna:

- Comment memakai polymorphic relationship.
- Comment bisa melekat ke project, milestone, atau task.

#### 26. User - Attachment

Relationship: `MENGUPLOAD_ATTACHMENT`

```text
USER (1) -- MENGUPLOAD_ATTACHMENT -- (0..N) ATTACHMENT
```

Implementasi:

- FK: `attachments.uploaded_by`

#### 27. User - Attachment Verification

Relationship: `MEMVERIFIKASI_ATTACHMENT`

```text
USER (1) -- MEMVERIFIKASI_ATTACHMENT -- (0..N) ATTACHMENT
```

Implementasi:

- FK nullable: `attachments.verified_by`

Makna:

- User dengan permission pengelolaan attachment bisa approve/reject attachment.

#### 28. Project/Milestone/Task - Attachment

Relationship: `MEMILIKI_ATTACHMENT`

```text
PROJECT   (1) -- MEMILIKI_ATTACHMENT -- (0..N) ATTACHMENT
MILESTONE (1) -- MEMILIKI_ATTACHMENT -- (0..N) ATTACHMENT
TASK      (1) -- MEMILIKI_ATTACHMENT -- (0..N) ATTACHMENT
```

Implementasi:

- `attachments.entity_type`
- `attachments.entity_id`

Makna:

- Attachment bisa melekat ke project, milestone, atau task.

### H. Notification, Token, Activity Log

#### 29. User - Notification

Relationship: `MENERIMA_NOTIFIKASI`

```text
USER (1) -- MENERIMA_NOTIFIKASI -- (0..N) NOTIFICATION
```

Implementasi:

- `notifications.notifiable_type`
- `notifications.notifiable_id`

Makna:

- Notifikasi bersifat polymorphic.
- Pada sistem ini penerimanya umumnya `App\Models\User`.

#### 30. User - Personal Access Token

Relationship: `MEMILIKI_TOKEN`

```text
USER (1) -- MEMILIKI_TOKEN -- (0..N) PERSONAL_ACCESS_TOKEN
```

Implementasi:

- `personal_access_tokens.tokenable_type`
- `personal_access_tokens.tokenable_id`

Makna:

- Token dipakai untuk autentikasi API.

#### 31. User - Activity Log

Relationship: `MELAKUKAN_AKTIVITAS`

```text
USER (1) -- MELAKUKAN_AKTIVITAS -- (0..N) ACTIVITY_LOG
```

Implementasi:

- `activity_log.causer_type`
- `activity_log.causer_id`

Makna:

- User menjadi pelaku aktivitas.

#### 32. Entity Domain - Activity Log

Relationship: `DICATAT_DALAM_LOG`

```text
PROJECT/MILESTONE/TASK/ATTACHMENT/COMMENT/... (1) -- DICATAT_DALAM_LOG -- (0..N) ACTIVITY_LOG
```

Implementasi:

- `activity_log.subject_type`
- `activity_log.subject_id`

Makna:

- Activity log bersifat polymorphic, bisa menunjuk ke berbagai entity.

## Relationship Ringkas untuk Digambar

Jika ingin menggambar Chen ERD tanpa terlalu penuh, gunakan daftar relationship berikut:

| No | Entity A | Relationship | Entity B | Kardinalitas |
| --- | --- | --- | --- | --- |
| 1 | Division | Memiliki | User | 1 : N |
| 2 | User | Menjadi Owner | Project | 1 : N |
| 3 | Project | Memiliki | Milestone | 1 : N |
| 4 | Project | Memiliki | Task | 1 : N |
| 5 | Milestone | Mengelompokkan | Task | 1 : N, optional di Task |
| 6 | Task | Ditugaskan ke | User | M : N lewat Task Assignment |
| 7 | Task | Bergantung pada | Task | M : N lewat Task Dependency |
| 8 | Task | Memiliki | Status History | 1 : N |
| 9 | User | Mengubah | Status History | 1 : N |
| 10 | Task | Memiliki | Task Progress Entry | 1 : N |
| 11 | User | Mengubah | Task Progress Entry | 1 : N |
| 12 | Task | Memiliki | Time Entry | 1 : N |
| 13 | User | Mengisi | Time Entry | 1 : N |
| 14 | Task | Memiliki | Task Cost Entry | 1 : N |
| 15 | Project | Memiliki | Project Baseline | 1 : N |
| 16 | Project Baseline | Memuat | Task Baseline | 1 : N |
| 17 | Task | Disnapshot | Task Baseline | 1 : N |
| 18 | Project | Memiliki | Reporting Period | 1 : N |
| 19 | Project | Memiliki | KPI Snapshot | 1 : N |
| 20 | Reporting Period | Menghasilkan | KPI Snapshot | 1 : N |
| 21 | User | Menulis | Comment | 1 : N |
| 22 | Project/Milestone/Task | Dikomentari | Comment | 1 : N polymorphic |
| 23 | User | Mengupload | Attachment | 1 : N |
| 24 | User | Memverifikasi | Attachment | 1 : N optional |
| 25 | Project/Milestone/Task | Memiliki | Attachment | 1 : N polymorphic |
| 26 | User | Memiliki Role | Role | M : N lewat Model Has Role |
| 27 | Role | Memiliki Permission | Permission | M : N lewat Role Has Permission |
| 28 | User | Memiliki Permission Langsung | Permission | M : N lewat Model Has Permission |
| 29 | User | Menerima | Notification | 1 : N polymorphic |
| 30 | User | Memiliki | Personal Access Token | 1 : N polymorphic |
| 31 | User | Melakukan | Activity Log | 1 : N polymorphic causer |
| 32 | Entity Domain | Dicatat dalam | Activity Log | 1 : N polymorphic subject |

## Cara Menggambar Chen ERD di Draw.io

### Langkah 1: Gambar Entity Inti

Mulai dari entity pusat:

```text
USER
PROJECT
MILESTONE
TASK
```

Lalu tambahkan entity pendukung:

```text
TASK_ASSIGNMENT
TASK_DEPENDENCY
STATUS_HISTORY
TIME_ENTRY
TASK_PROGRESS_ENTRY
TASK_COST_ENTRY
```

Kemudian tambahkan reporting:

```text
PROJECT_BASELINE
TASK_BASELINE
REPORTING_PERIOD
KPI_SNAPSHOT
```

Terakhir tambahkan access dan komunikasi:

```text
DIVISION
ROLE
PERMISSION
COMMENT
ATTACHMENT
NOTIFICATION
ACTIVITY_LOG
PERSONAL_ACCESS_TOKEN
```

### Langkah 2: Gambar Relationship Diamond

Contoh:

```text
[PROJECT] -- <MEMILIKI_TASK> -- [TASK]
```

Untuk M:N, taruh associative entity di tengah:

```text
[TASK] -- <DITUGASKAN_KE> -- [TASK_ASSIGNMENT] -- <UNTUK_USER> -- [USER]
```

Atau dalam gaya Chen klasik:

```text
[TASK] -- <DITUGASKAN_KE> -- [USER]
```

Lalu atribut relationship:

- role_on_task
- estimated_effort_hours
- assigned_at

bisa ditempel sebagai oval pada diamond `DITUGASKAN_KE`.

### Langkah 3: Tandai Polymorphic

Untuk polymorphic, lebih mudah digambar sebagai relationship terpisah:

```text
[PROJECT] -- <MEMILIKI_ATTACHMENT> -- [ATTACHMENT]
[MILESTONE] -- <MEMILIKI_ATTACHMENT> -- [ATTACHMENT]
[TASK] -- <MEMILIKI_ATTACHMENT> -- [ATTACHMENT]
```

Lalu beri catatan:

```text
Implementasi database memakai entity_type + entity_id.
```

### Langkah 4: Tandai Weak/Associative Entity

Entity berikut sebaiknya ditandai sebagai associative entity:

- `TASK_ASSIGNMENT`
- `TASK_DEPENDENCY`
- `TASK_BASELINE`
- `ROLE_HAS_PERMISSION`
- `MODEL_HAS_ROLE`
- `MODEL_HAS_PERMISSION`

Alasan:

> Entity tersebut muncul untuk menghubungkan dua entity lain dan menyimpan atribut tambahan relasi.

## Jawaban Sidang yang Bisa Dipakai

### Relasi User dan Project

> Relasi user dan project adalah one-to-many. Satu user bisa menjadi owner banyak project, dan satu project memiliki satu owner melalui `division_owner_id`. Walaupun namanya division owner, kolom ini mengarah ke `users.id`.

### Relasi Task dan User

> Relasi task dan user adalah many-to-many melalui `task_assignments`. Karena assignment punya atribut tambahan seperti `role_on_task` dan `estimated_effort_hours`, maka saya jadikan associative entity.

### Relasi Task dan Dependency

> Dependency task adalah self-referencing many-to-many. Satu task bisa bergantung pada banyak task lain, dan satu task bisa menjadi prasyarat untuk banyak task. Relasinya disimpan di `task_dependencies`.

### Relasi Comment dan Attachment

> Comment dan attachment memakai polymorphic relationship. Jadi satu tabel bisa dipakai untuk Project, Milestone, dan Task dengan kolom `entity_type` dan `entity_id`.

### Relasi Role dan Permission

> Role dan permission memakai many-to-many dari package Spatie. User mendapat role lewat `model_has_roles`, role mendapat permission lewat `role_has_permissions`, dan user juga bisa mendapat permission langsung lewat `model_has_permissions`.

### Relasi KPI Snapshot

> KPI snapshot adalah hasil rekap dari project pada reporting period tertentu. Satu project punya banyak reporting period, dan setiap period bisa menghasilkan KPI snapshot berisi total task, task selesai, overdue, dan rata-rata cycle time.

## Entity yang Paling Penting untuk Diagram Utama

Kalau halaman diagram terlalu penuh, prioritaskan entity ini:

1. `USER`
2. `DIVISION`
3. `PROJECT`
4. `MILESTONE`
5. `TASK`
6. `TASK_ASSIGNMENT`
7. `TASK_DEPENDENCY`
8. `STATUS_HISTORY`
9. `TIME_ENTRY`
10. `TASK_PROGRESS_ENTRY`
11. `TASK_COST_ENTRY`
12. `PROJECT_BASELINE`
13. `TASK_BASELINE`
14. `REPORTING_PERIOD`
15. `KPI_SNAPSHOT`
16. `COMMENT`
17. `ATTACHMENT`
18. `ROLE`
19. `PERMISSION`
20. `NOTIFICATION`

Entity sistem seperti `PERSONAL_ACCESS_TOKEN` dan `ACTIVITY_LOG` bisa dimasukkan di diagram pendukung jika diagram utama terlalu padat.

