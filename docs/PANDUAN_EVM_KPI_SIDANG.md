# Panduan EVM dan KPI Snapshot untuk Sidang

Dokumen ini menjelaskan tiga bagian laporan utama di sistem:

- EVM Effort-Based: mengukur performa jadwal dan pemakaian jam kerja.
- EVM Cost-Based: mengukur performa jadwal dan biaya dalam IDR.
- KPI Snapshot: menyimpan ringkasan kondisi tugas pada tanggal/periode tertentu.

Tujuan dokumen ini adalah membantu menjawab pertanyaan sidang seperti:

- Data ini buat apa?
- Datanya berasal dari tabel mana?
- Rumusnya bagaimana?
- Kode yang menghitungnya ada di mana?
- Kenapa angka bisa 0 atau kosong?

## Ringkasan Cepat

| Fitur | Fungsi | Sumber utama | Output utama | Lokasi kode |
| --- | --- | --- | --- | --- |
| EVM Effort-Based | Mengukur kinerja proyek berdasarkan effort/jam kerja | task, assignment, progress, time entry, baseline | PV, EV, AC, SV, SPI, CV, CPI dalam satuan jam | `app/Services/Implementations/EvmService.php` |
| EVM Cost-Based | Mengukur kinerja proyek berdasarkan biaya | task budget, cost entry, progress, baseline | BAC, PV, EV, AC, SV, SPI, CV, CPI, EAC, ETC dalam IDR | `app/Services/Implementations/EvmCostService.php` |
| KPI Snapshot | Menyimpan ringkasan status task per periode | task, status history, reporting period | total task, task done, overdue, avg cycle time | `app/Services/Implementations/KpiSnapshotService.php` |

## Istilah Dasar EVM

### BAC

BAC adalah Budget at Completion, yaitu total anggaran rencana untuk menyelesaikan proyek.

Di sistem ini BAC dipakai pada EVM cost-based.

Sumber BAC:

- Jika memakai baseline dan baseline punya budget task, BAC = total `task_baselines.budget_cost_base`.
- Jika tidak memakai baseline, BAC = `projects.value_amount` jika ada.
- Jika `projects.value_amount` kosong/0, fallback ke total `tasks.budget_cost`.

Kode:

- `app/Services/Implementations/EvmCostService.php`
- Bagian pemilihan BAC ada di akhir fungsi `computeForProjectDate()`.

Catatan sidang:

> BAC dipakai untuk melihat estimasi total biaya proyek sampai selesai. Pada sistem ini, jika baseline tersedia maka sistem memakai snapshot baseline agar perhitungan tidak berubah saat data task berubah.

### PV

PV adalah Planned Value. Artinya nilai pekerjaan yang seharusnya sudah tercapai sampai tanggal tertentu.

Dalam sistem ini PV dihitung proporsional berdasarkan:

- tanggal mulai rencana task,
- durasi rencana task,
- tanggal laporan atau `as_of`,
- planned effort atau budget task.

Contoh sederhana:

- Task punya planned effort 40 jam.
- Durasi rencana 5 hari.
- Pada hari ke-2, pekerjaan seharusnya berjalan 2/5.
- PV = 40 x 2/5 = 16 jam.

Untuk cost-based:

- Task punya budget Rp1.000.000.
- Durasi rencana 5 hari.
- Pada hari ke-2, PV = Rp1.000.000 x 2/5 = Rp400.000.

Kode:

- Effort: `app/Services/Implementations/EvmService.php`
- Cost: `app/Services/Implementations/EvmCostService.php`

Catatan sidang:

> PV menunjukkan target rencana sampai tanggal yang dipilih. Jika PV tinggi tapi EV rendah, berarti progress aktual tertinggal dari rencana.

### EV

EV adalah Earned Value. Artinya nilai pekerjaan yang benar-benar sudah diperoleh berdasarkan progress aktual.

Rumus umum:

```text
EV = total rencana task x percent_complete
```

Untuk effort-based:

```text
EV = planned_effort_hours x percent_complete
```

Untuk cost-based:

```text
EV = budget_cost x percent_complete
```

Sumber progress:

- Utama: `task_progress_entries.percent_complete` yang tanggalnya <= tanggal laporan.
- Jika tanggal laporan adalah hari ini dan belum ada progress history, sistem fallback ke `tasks.percent_complete`.
- Untuk tanggal masa lalu tanpa history, progress dianggap 0 supaya tidak memakai progress masa depan.

Kode:

- Effort: `EvmService::computeForProjectDate()`
- Cost: `EvmCostService::computeForProjectDate()`

Catatan sidang:

> EV menunjukkan hasil kerja yang benar-benar sudah dicapai. Sistem mengambil progress historis agar laporan tanggal lama tetap sesuai kondisi pada tanggal tersebut.

### AC

AC adalah Actual Cost. Artinya biaya atau effort aktual yang sudah digunakan sampai tanggal laporan.

Di sistem ada dua versi:

#### AC pada EVM Effort-Based

Satuan AC adalah jam.

Sumber data:

- `time_entries.hours`
- Difilter sampai tanggal laporan:

```text
time_entries.date <= as_of
```

Kode:

- `app/Services/Implementations/EvmService.php`

#### AC pada EVM Cost-Based

Satuan AC adalah IDR.

Sumber data:

- `task_cost_entries.amount`
- Difilter sampai tanggal laporan:

```text
task_cost_entries.incurred_on <= as_of
```

Kode:

- `app/Services/Implementations/EvmCostService.php`

Catatan sidang:

> AC menunjukkan berapa effort atau biaya yang benar-benar sudah keluar. Effort-based memakai jam kerja dari time entry, sedangkan cost-based memakai biaya aktual dari cost entry.

### SV

SV adalah Schedule Variance.

Rumus:

```text
SV = EV - PV
```

Arti:

- SV > 0: pekerjaan lebih cepat dari rencana.
- SV = 0: sesuai rencana.
- SV < 0: tertinggal dari rencana.

Kode:

- `EvmService.php`
- `EvmCostService.php`

Catatan sidang:

> SV dipakai untuk melihat selisih antara progress aktual dan progress yang seharusnya dicapai.

### SPI

SPI adalah Schedule Performance Index.

Rumus:

```text
SPI = EV / PV
```

Arti:

- SPI > 1: lebih cepat dari rencana.
- SPI = 1: tepat sesuai rencana.
- SPI < 1: terlambat dari rencana.

Jika PV masih 0, SPI bernilai `null` karena pembagian tidak aman.

Kode:

- `EvmService.php`
- `EvmCostService.php`

Catatan sidang:

> SPI adalah indikator efisiensi jadwal. Nilai 1 berarti progress aktual sama dengan rencana.

### CV

CV adalah Cost Variance.

Rumus:

```text
CV = EV - AC
```

Pada effort-based, CV berarti selisih effort:

- EV dalam jam.
- AC dalam jam.

Pada cost-based, CV berarti selisih biaya:

- EV dalam IDR.
- AC dalam IDR.

Arti:

- CV > 0: lebih efisien dari aktual yang keluar.
- CV = 0: sesuai.
- CV < 0: pemakaian aktual lebih besar dari nilai pekerjaan.

Kode:

- `EvmService.php`
- `EvmCostService.php`

### CPI

CPI adalah Cost Performance Index.

Rumus:

```text
CPI = EV / AC
```

Arti:

- CPI > 1: efisien.
- CPI = 1: sesuai.
- CPI < 1: boros atau tidak efisien.

Jika AC masih 0, CPI bernilai `null`.

Kode:

- `EvmService.php`
- `EvmCostService.php`

Catatan sidang:

> CPI menunjukkan efisiensi pemakaian effort atau biaya aktual. Semakin besar dari 1, semakin efisien.

### EAC

EAC adalah Estimate at Completion. Ini estimasi biaya akhir proyek berdasarkan performa CPI saat ini.

Rumus di sistem:

```text
EAC = BAC / CPI
```

Syarat:

- Hanya dihitung jika CPI ada dan lebih dari 0.

Kode:

- `app/Services/Implementations/EvmCostService.php`

Catatan sidang:

> EAC hanya ada di cost-based karena tujuannya memprediksi total biaya akhir proyek.

### ETC

ETC adalah Estimate to Complete. Ini estimasi sisa biaya yang masih dibutuhkan sampai proyek selesai.

Rumus di sistem:

```text
ETC = EAC - AC
```

Kode:

- `app/Services/Implementations/EvmCostService.php`

Catatan sidang:

> ETC membantu melihat kira-kira sisa biaya yang masih perlu dikeluarkan.

## EVM Effort-Based

### Tujuan

EVM effort-based digunakan untuk menjawab:

- Apakah progress pekerjaan sesuai dengan rencana jam kerja?
- Apakah effort aktual yang dicatat tim masih efisien?
- Apakah proyek secara jadwal lebih cepat atau terlambat?

Satuan utamanya adalah jam.

### Endpoint

Route:

```php
GET /api/projects/{project}/evm?date=YYYY-MM-DD&baseline_id=...
```

Lokasi route:

- `routes/api.php`

Controller:

- `app/Http/Controllers/EvmController.php`

Service:

- `app/Services/Implementations/EvmService.php`

### Sumber Data

| Data | Tabel/model | Dipakai untuk |
| --- | --- | --- |
| Task proyek | `tasks` | daftar pekerjaan yang dihitung |
| Assignment effort | `task_assignments.estimated_effort_hours` | planned effort utama |
| Baseline task | `task_baselines.planned_effort_hours` | planned effort snapshot jika baseline dipakai |
| Progress task | `task_progress_entries.percent_complete` | EV |
| Time entry | `time_entries.hours` | AC effort |
| Project baseline | `project_baselines` | menentukan baseline terbaru atau baseline pilihan |

### Urutan Planned Effort

EVM effort-based memilih planned effort dengan prioritas:

1. `task_baselines.planned_effort_hours`, jika baseline dipilih dan nilainya ada.
2. Total `task_assignments.estimated_effort_hours`.
3. Fallback `duration_planned x 8`.

Kode:

- `EvmService.php`
- Konstanta fallback:

```php
private const HOURS_PER_DAY = 8;
```

Catatan penting:

> Input manual `estimated_effort_hours` aman dan justru lebih bagus karena planned effort menjadi eksplisit. Jika kosong, sistem bisa fallback ke durasi x 8, tetapi fallback itu tidak selalu tersimpan sebagai nilai assignment di database.

### Alur Hitung

1. Terima `project_id`, `date`, dan optional `baseline_id`.
2. Cari baseline:
   - jika `baseline_id` dikirim, validasi baseline milik project tersebut;
   - jika tidak dikirim, pakai baseline terbaru project.
3. Ambil task aktif di project.
4. Jika baseline dipakai, hanya task yang tercatat pada baseline tersebut yang dihitung.
5. Ambil total planned effort assignment per task.
6. Ambil snapshot baseline task jika ada.
7. Ambil progress terakhir per task sampai tanggal laporan.
8. Ambil total jam aktual dari time entry sampai tanggal laporan.
9. Hitung PV, EV, AC per task.
10. Jumlahkan seluruh task.
11. Hitung SV, SPI, CV, CPI.

### Rumus Effort

```text
planned_effort = baseline_effort atau assignment_effort atau duration_planned x 8
planned_fraction = elapsed_days / duration_planned
PV = planned_effort x planned_fraction
EV = planned_effort x percent_complete
AC = total time_entries.hours sampai tanggal laporan
SV = EV - PV
SPI = EV / PV
CV = EV - AC
CPI = EV / AC
```

### Cara Menjawab Saat Sidang

Jika ditanya:

> Data EVM effort ini dari mana?

Jawaban:

> Data EVM effort berasal dari task proyek, assignment effort sebagai rencana jam kerja, progress task sebagai earned value, dan time entry sebagai actual effort. Jika ada baseline, sistem memakai snapshot baseline agar perhitungan tetap konsisten dengan rencana awal.

Jika ditanya:

> Kenapa effort harus diinput manual?

Jawaban:

> Karena estimated effort adalah rencana jam kerja yang lebih akurat daripada asumsi otomatis durasi x 8. Fallback durasi x 8 tetap ada, tetapi input manual membuat data EVM lebih valid dan mudah dipertanggungjawabkan.

## EVM Cost-Based

### Tujuan

EVM cost-based digunakan untuk menjawab:

- Apakah progress pekerjaan sesuai dengan rencana biaya?
- Apakah biaya aktual masih efisien?
- Berapa estimasi biaya akhir proyek?
- Berapa sisa estimasi biaya untuk menyelesaikan proyek?

Satuan utamanya adalah IDR.

### Endpoint

Route:

```php
GET /api/projects/{project}/evm-cost?as_of=YYYY-MM-DD&baseline_id=...
```

Lokasi route:

- `routes/api.php`

Controller:

- `app/Http/Controllers/EvmCostController.php`

Service:

- `app/Services/Implementations/EvmCostService.php`

### Sumber Data

| Data | Tabel/model | Dipakai untuk |
| --- | --- | --- |
| Project value | `projects.value_amount` | BAC jika tidak memakai baseline |
| Task budget | `tasks.budget_cost` | PV dan EV cost |
| Task baseline budget | `task_baselines.budget_cost_base` | PV, EV, BAC jika baseline dipakai |
| Progress task | `task_progress_entries.percent_complete` | EV |
| Actual cost | `task_cost_entries.amount` | AC cost |
| Task schedule | `tasks.start_planned`, `tasks.duration_planned` | planned fraction untuk PV |

### Alur Hitung

1. Terima `project_id`, `as_of`, dan optional `baseline_id`.
2. Validasi baseline jika dikirim.
3. Ambil task project.
4. Jika baseline dipakai, hitung hanya task yang masuk baseline.
5. Ambil progress terakhir sampai tanggal laporan.
6. Ambil budget baseline jika ada, jika tidak pakai `tasks.budget_cost`.
7. Ambil actual cost dari `task_cost_entries`.
8. Hitung PV, EV, AC per task.
9. Jumlahkan semua task.
10. Hitung SV, SPI, CV, CPI.
11. Tentukan BAC.
12. Hitung EAC dan ETC jika CPI valid.

### Rumus Cost

```text
budget = baseline_budget atau tasks.budget_cost
planned_fraction = elapsed_days / duration_planned
PV = budget x planned_fraction
EV = budget x percent_complete
AC = total task_cost_entries.amount sampai tanggal laporan
SV = EV - PV
SPI = EV / PV
CV = EV - AC
CPI = EV / AC
BAC = total baseline budget atau projects.value_amount atau total tasks.budget_cost
EAC = BAC / CPI
ETC = EAC - AC
```

### Catatan BAC

Di kode saat ini, BAC tanpa baseline lebih memprioritaskan `projects.value_amount`.

Urutan:

1. Jika baseline dipakai dan ada budget baseline: `sum(task_baselines.budget_cost_base)`.
2. Jika tidak ada baseline: `projects.value_amount`.
3. Jika `projects.value_amount` kosong/0: `sum(tasks.budget_cost)`.

Catatan sidang:

> Pada cost-based EVM, `tasks.budget_cost` dipakai untuk PV dan EV per task. Untuk BAC total proyek, sistem bisa memakai nilai proyek sebagai kontrak/anggaran total, atau baseline budget jika baseline dipilih.

### Cara Menjawab Saat Sidang

Jika ditanya:

> Data actual cost berasal dari mana?

Jawaban:

> Actual cost berasal dari `task_cost_entries`, yaitu catatan biaya aktual per task. Data ini dijumlahkan sampai tanggal laporan.

Jika ditanya:

> Kenapa CPI bisa null?

Jawaban:

> CPI membutuhkan AC sebagai pembagi. Jika belum ada actual cost, AC masih 0, jadi sistem mengembalikan null untuk menghindari pembagian nol.

## KPI Snapshot

### Tujuan

KPI Snapshot digunakan untuk menyimpan kondisi proyek pada tanggal tertentu. Berbeda dari EVM yang menghitung value, KPI Snapshot lebih fokus ke ringkasan status task.

KPI Snapshot menjawab:

- Berapa total task pada tanggal laporan?
- Berapa task yang sudah selesai?
- Berapa task terlambat?
- Berapa rata-rata cycle time task selesai?

### Endpoint

Route utama:

```php
GET /api/projects/{project}/kpi-snapshots
POST /api/projects/{project}/kpi-snapshots/generate
GET /api/projects/{project}/kpi-snapshots/average-cycle-time
```

Lokasi route:

- `routes/api.php`

Controller:

- `app/Http/Controllers/KpiSnapshotController.php`

Service:

- `app/Services/Implementations/KpiSnapshotService.php`

### Sumber Data

| Data | Tabel/model | Dipakai untuk |
| --- | --- | --- |
| Reporting period | `reporting_periods` | tanggal/periode snapshot |
| Task | `tasks` | total task, overdue, cycle time |
| Status history | `status_histories` | status task pada tanggal snapshot |
| KPI snapshot | `kpi_snapshots` | hasil rekap yang disimpan |

### Output KPI Snapshot

| Field | Arti |
| --- | --- |
| `tasks_total` | jumlah task yang sudah ada sampai tanggal snapshot |
| `tasks_done` | jumlah task yang selesai sampai tanggal snapshot |
| `overdue_count` | jumlah task yang melewati `end_planned` dan belum selesai pada tanggal snapshot |
| `avg_cycle_time_days` | rata-rata durasi aktual dari `start_actual` ke `end_actual` untuk task selesai |

### Alur Generate KPI

Fungsi utama:

```php
generateForProjectAndDate($projectId, $periodDate, ?string $note = null)
```

Lokasi:

- `app/Services/Implementations/KpiSnapshotService.php`

Alur:

1. Terima `project_id` dan tanggal periode.
2. Cari `reporting_periods` untuk project dan tanggal tersebut.
3. Jika belum ada, buat reporting period baru.
4. Ambil task project yang sudah dibuat sampai akhir tanggal snapshot.
5. Task yang sudah di-archive setelah tanggal snapshot tetap bisa dihitung untuk snapshot lama.
6. Task yang milestone-nya valid pada tanggal tersebut ikut dihitung.
7. Ambil status task berdasarkan `status_histories` sampai tanggal snapshot.
8. Hitung total task.
9. Hitung task done.
10. Hitung overdue.
11. Hitung rata-rata cycle time.
12. Simpan atau update `kpi_snapshots`.

### Cara Menghitung Task Done

Task dianggap done jika:

- status pada tanggal snapshot adalah `Done` atau `Selesai`;
- dan jika punya `end_actual`, tanggal selesai aktual tidak melewati tanggal snapshot.

Kode:

- `KpiSnapshotService::isDoneStatus()`
- `KpiSnapshotService::taskStatusesAsOf()`

### Cara Menghitung Overdue

Task dianggap overdue jika:

- punya `end_planned`;
- `end_planned` lebih kecil dari tanggal snapshot;
- task belum done pada tanggal snapshot.

Kode:

- `KpiSnapshotService::generateForProjectAndDate()`

### Cara Menghitung Average Cycle Time

Cycle time dihitung dari:

```text
end_actual - start_actual
```

Hanya task yang punya `start_actual` dan `end_actual` yang dihitung.

Rumus:

```text
avg_cycle_time_days = rata-rata semua cycle time task selesai
```

Kode:

- `KpiSnapshotService::generateForProjectAndDate()`

### Kenapa Menggunakan Status History?

Kalau sistem hanya memakai `tasks.status` saat ini, snapshot tanggal lama bisa salah. Misalnya:

- Pada 20 Juni task masih `In Progress`.
- Pada 25 Juni task sudah `Done`.
- Kalau generate laporan untuk 20 Juni setelah tanggal 25, status sekarang sudah `Done`.
- Tanpa status history, snapshot 20 Juni akan salah.

Karena itu sistem membaca `status_histories` untuk menebak status task pada tanggal snapshot.

Catatan sidang:

> KPI snapshot memakai status history agar laporan per tanggal tidak terpengaruh perubahan status di masa depan.

## Hubungan Baseline dengan EVM

Baseline adalah snapshot rencana proyek pada satu waktu.

Baseline menyimpan:

- start planned awal,
- end planned awal,
- duration planned awal,
- planned effort awal,
- budget cost awal.

Tabel penting:

- `project_baselines`
- `task_baselines`

Kenapa baseline penting:

- Tanpa baseline, EVM memakai data task saat ini.
- Dengan baseline, EVM memakai rencana awal yang dibekukan.
- Ini membuat perbandingan rencana vs aktual lebih fair.

Contoh jawaban sidang:

> Baseline dipakai agar perhitungan EVM tidak berubah ketika task diedit. Jadi jika jadwal atau budget berubah, laporan berbasis baseline tetap memakai rencana yang sudah disimpan sebelumnya.

## Hubungan Progress, Time Entry, dan Cost Entry

### Progress Entry

Tabel:

- `task_progress_entries`

Dipakai untuk:

- EV effort.
- EV cost.
- melihat progress historis sampai tanggal laporan.

### Time Entry

Tabel:

- `time_entries`

Dipakai untuk:

- AC pada EVM effort-based.

Contoh:

> Jika member mencatat kerja 3 jam pada task, maka 3 jam tersebut menjadi actual effort.

### Task Cost Entry

Tabel:

- `task_cost_entries`

Dipakai untuk:

- AC pada EVM cost-based.

Contoh:

> Jika ada biaya operasional Rp200.000 untuk task, biaya tersebut menjadi actual cost.

## Kenapa Ada Dua EVM?

Sistem memiliki dua EVM karena sudut pandangnya berbeda.

### EVM Effort-Based

Fokus:

- jam kerja,
- produktivitas tim,
- effort yang direncanakan vs effort aktual.

Cocok untuk menjawab:

> Apakah pekerjaan selesai dengan jam kerja yang efisien?

### EVM Cost-Based

Fokus:

- biaya,
- budget,
- cost efficiency,
- estimasi biaya akhir.

Cocok untuk menjawab:

> Apakah proyek masih efisien dari sisi biaya?

## Kenapa Angka Bisa 0 atau Null?

### PV 0

Kemungkinan:

- tanggal laporan sebelum `start_planned`;
- task tidak punya `duration_planned`;
- tidak ada task yang masuk perhitungan;
- baseline dipilih tetapi task tidak masuk baseline.

### EV 0

Kemungkinan:

- progress task masih 0;
- belum ada `task_progress_entries` sampai tanggal laporan;
- tanggal laporan masa lalu dan tidak ada history progress.

### AC 0

Kemungkinan:

- effort-based: belum ada `time_entries`;
- cost-based: belum ada `task_cost_entries`.

### SPI null

Penyebab:

- PV = 0, sehingga `EV / PV` tidak bisa dihitung.

### CPI null

Penyebab:

- AC = 0, sehingga `EV / AC` tidak bisa dihitung.

### Planned effort kosong

Kemungkinan:

- `task_assignments.estimated_effort_hours` kosong/null;
- task belum masuk baseline;
- `duration_planned` kosong sehingga fallback durasi x 8 tidak bisa berjalan.

## Catatan Khusus tentang Estimated Effort

Di create task, estimated effort sebaiknya wajib minimal 1 jam.

Alasannya:

- data planned effort masuk eksplisit ke `task_assignments`;
- EVM effort lebih stabil;
- tidak bergantung fallback durasi x 8;
- lebih mudah dijelaskan saat sidang.

Penjelasan sidang:

> Estimated effort adalah rencana jam kerja per assignee. Nilai ini dipakai sebagai planned effort pada EVM effort-based. Jika kosong, sistem punya fallback durasi x 8, tetapi input manual lebih akurat karena merepresentasikan estimasi kerja yang sebenarnya.

## Letak Tampilan Frontend

### Detail Project

File:

- `src/app/dashboard/projects/[id]/page.tsx`

Tab penting:

- `Schedule Performance (Baseline)` untuk EVM effort.
- `EVM (Cost-Based / IDR)` untuk EVM cost.
- `Laporan KPI` untuk KPI snapshot.
- `Project Tasks` untuk melihat task dan planned effort.

### Reports Page

File:

- `src/app/dashboard/reports/page.tsx`

Fungsi:

- Menampilkan ringkasan EVM dan KPI lintas halaman laporan.

### Komponen EVM

File:

- `src/components/evm/EvmWidget.tsx`
- `src/components/evm/EvmCostWidget.tsx`

Fungsi:

- Memanggil endpoint EVM.
- Menampilkan angka PV, EV, AC, SPI, CPI, dan metrik terkait.

## Checklist Debug Cepat

Jika EVM effort terlihat 0:

1. Cek task ada di project yang benar.
2. Cek task punya `start_planned` dan `end_planned`.
3. Cek `duration_planned` terisi.
4. Cek assignment punya `estimated_effort_hours`.
5. Cek progress ada di `task_progress_entries`.
6. Cek time entry ada di `time_entries`.
7. Jika pakai baseline, cek task sudah masuk `task_baselines`.

Jika EVM cost terlihat 0:

1. Cek task punya `budget_cost`.
2. Cek progress ada.
3. Cek actual cost ada di `task_cost_entries`.
4. Jika pakai baseline, cek `task_baselines.budget_cost_base`.
5. Cek tanggal `as_of` tidak sebelum task mulai.

Jika KPI snapshot tidak berubah:

1. Generate ulang snapshot untuk tanggal yang sama.
2. Cek `reporting_periods`.
3. Cek status history task.
4. Cek task sudah dibuat sebelum tanggal snapshot.
5. Cek task tidak dihapus sebelum tanggal snapshot.

## Contoh Narasi Sidang

### Menjelaskan EVM Effort

> EVM effort-based menghitung performa proyek berdasarkan jam kerja. Planned Value dihitung dari planned effort dan jadwal rencana. Earned Value dihitung dari planned effort dikali progress task. Actual Cost dalam konteks effort adalah total jam dari time entry. Dari situ sistem menghitung SPI untuk jadwal dan CPI untuk efisiensi effort.

### Menjelaskan EVM Cost

> EVM cost-based menggunakan satuan rupiah. PV dan EV berasal dari budget task atau baseline budget, sedangkan AC berasal dari task cost entry. Sistem juga menghitung BAC, EAC, dan ETC untuk memperkirakan total biaya akhir dan sisa biaya proyek.

### Menjelaskan KPI Snapshot

> KPI Snapshot adalah rekap kondisi task pada periode tertentu. Sistem menyimpan total task, task selesai, task terlambat, dan rata-rata cycle time. Snapshot memakai status history agar laporan tanggal lama tetap akurat walaupun status task berubah setelahnya.

## File Kode Utama

Backend:

- `app/Services/Implementations/EvmService.php`
- `app/Services/Implementations/EvmCostService.php`
- `app/Services/Implementations/KpiSnapshotService.php`
- `app/Http/Controllers/EvmController.php`
- `app/Http/Controllers/EvmCostController.php`
- `app/Http/Controllers/KpiSnapshotController.php`
- `routes/api.php`

Frontend:

- `src/app/dashboard/projects/[id]/page.tsx`
- `src/app/dashboard/reports/page.tsx`
- `src/components/evm/EvmWidget.tsx`
- `src/components/evm/EvmCostWidget.tsx`

Database/model penting:

- `tasks`
- `task_assignments`
- `task_progress_entries`
- `time_entries`
- `task_cost_entries`
- `project_baselines`
- `task_baselines`
- `reporting_periods`
- `kpi_snapshots`
- `status_histories`

## Potongan Kode Penting

Bagian ini berisi function inti yang bisa ditunjukkan saat menjelaskan alur sistem.

### 1. EVM Effort-Based

Function utama:

```php
// app/Services/Implementations/EvmService.php
public function computeForProjectDate(int $projectId, $date, ?int $baselineId = null): array
```

Function ini menerima:

- `projectId`: project yang dihitung.
- `date`: tanggal laporan/as-of.
- `baselineId`: optional, jika ingin menghitung berdasarkan baseline tertentu.

Potongan kode pemilihan planned effort:

```php
$plannedEffort = null;
$baseRow = $taskBaselineMap[$taskId] ?? null;
$startPlanned = $baseRow['start_planned_base'] ?? $task->start_planned;
$durationPlanned = (int) ($baseRow['duration_planned_base'] ?? $task->duration_planned ?? 0);

$tbEffort = isset($baseRow['planned_effort_hours']) ? (float) $baseRow['planned_effort_hours'] : 0.0;
if ($baselineId && $tbEffort > 0) {
    $plannedEffort = $tbEffort;
    $baselineEffortRows++;
}

$assignEffort = (float) ($assignSums[$taskId] ?? 0);
if (($plannedEffort === null || $plannedEffort <= 0) && $assignEffort > 0) {
    $plannedEffort = $assignEffort;
}

if (($plannedEffort === null || $plannedEffort <= 0) && $durationPlanned > 0) {
    $plannedEffort = $durationPlanned * self::HOURS_PER_DAY;
}
```

Maknanya:

- Prioritas pertama: effort dari baseline.
- Prioritas kedua: effort dari assignment.
- Prioritas ketiga: fallback durasi x 8 jam.

Potongan kode PV, EV, dan AC effort:

```php
$pv = $plannedEffort * $fraction;
$ev = $plannedEffort * ($pct / 100);
$ac = (float) ($acSums[$taskId] ?? 0.0);

$totalPV += $pv;
$totalEV += $ev;
$totalAC += $ac;
```

Potongan kode metrik akhir:

```php
$sv = $totalEV - $totalPV;
$spi = ($totalPV > 0.0) ? ($totalEV / $totalPV) : null;
$cv = $totalEV - $totalAC;
$cpi = ($totalAC > 0.0) ? ($totalEV / $totalAC) : null;
```

Return output:

```php
return [
    'project_id' => $projectId,
    'date' => $asOfDate,
    'baseline_id' => $baselineId,
    'pv' => round((float) $totalPV, 2),
    'ev' => round((float) $totalEV, 2),
    'ac' => round((float) $totalAC, 2),
    'sv' => round((float) $sv, 2),
    'spi' => $spi !== null ? round((float) $spi, 4) : null,
    'cv' => round((float) $cv, 2),
    'cpi' => $cpi !== null ? round((float) $cpi, 4) : null,
];
```

### 2. EVM Cost-Based

Function utama:

```php
// app/Services/Implementations/EvmCostService.php
public function computeForProjectDate(int $projectId, $date, ?int $baselineId = null): array
```

Function ini mirip EVM effort, tetapi satuannya IDR.

Potongan kode budget task:

```php
$currentBudgetCost = (float) ($task->budget_cost ?? 0);
if ($currentBudgetCost < 0) $currentBudgetCost = 0;
$sumBudgetCost += $currentBudgetCost;

$baseRow = $taskBaselineMap[$taskId] ?? null;
$hasBaselineBudget = $baseRow !== null
    && array_key_exists('budget_cost_base', $baseRow)
    && $baseRow['budget_cost_base'] !== null;

$budgetCost = $hasBaselineBudget
    ? (float) $baseRow['budget_cost_base']
    : $currentBudgetCost;
```

Maknanya:

- Jika baseline dipakai dan baseline punya budget, sistem pakai budget baseline.
- Jika tidak, sistem pakai `tasks.budget_cost`.

Potongan kode PV, EV, dan AC cost:

```php
$pv = $budgetCost * $fraction;
$ev = $budgetCost * ($pct / 100);
$ac = (float) ($acSums[$taskId] ?? 0.0);

$totalPV += $pv;
$totalEV += $ev;
$totalAC += $ac;
```

Potongan kode BAC:

```php
$projectValue = (float) ($project->value_amount ?? 0);
if ($baselineId && $baselineBudgetRows > 0) {
    $bac = $sumEvmBudgetCost;
    $bacSource = 'sum(task_baselines.budget_cost_base)';
    $pvEvSource = 'task_baselines.budget_cost_base';
} else {
    $bac = $projectValue > 0 ? $projectValue : $sumBudgetCost;
    $bacSource = $projectValue > 0 ? 'projects.value_amount' : 'sum(tasks.budget_cost)';
    $pvEvSource = 'tasks.budget_cost';
}
```

Maknanya:

- Dengan baseline: BAC dari total budget baseline.
- Tanpa baseline: BAC dari nilai project.
- Jika nilai project kosong: BAC dari total budget task.

Potongan kode EAC dan ETC:

```php
$eac = null;
$etc = null;
if ($cpi !== null && $cpi > 0.0) {
    $eac = $bac / $cpi;
    $etc = $eac - $totalAC;
}
```

Maknanya:

- EAC adalah estimasi biaya akhir.
- ETC adalah estimasi sisa biaya.

### 3. Planned Fraction

Function penting:

```php
// app/Services/Implementations/EvmCostService.php
protected function plannedFractionInclusiveDays(Carbon $asOf, $startPlanned, int $durationDays): float
```

Potongan kode:

```php
if (! $startPlanned || $durationDays <= 0) {
    return 0.0;
}

$start = Carbon::parse($startPlanned);

if ($asOf->lt($start)) {
    return 0.0;
}

$elapsed = $start->diffInDays($asOf) + 1;
if ($elapsed < 0) $elapsed = 0;
if ($elapsed > $durationDays) $elapsed = $durationDays;

return $durationDays > 0 ? ($elapsed / $durationDays) : 0.0;
```

Maknanya:

- Kalau tanggal laporan sebelum task mulai, PV = 0.
- Kalau task sudah berjalan, progress rencana dihitung berdasarkan hari berjalan.
- Perhitungan bersifat inclusive, jadi hari pertama task sudah dihitung.

Catatan:

> Di `EvmService.php`, logic planned fraction ditulis langsung di dalam loop. Di `EvmCostService.php`, logic-nya dibuat function terpisah.

### 4. KPI Snapshot

Function utama:

```php
// app/Services/Implementations/KpiSnapshotService.php
public function generateForProjectAndDate($projectId, $periodDate, ?string $note = null)
```

Function ini membuat atau memperbarui KPI snapshot untuk project dan tanggal tertentu.

Potongan kode membuat reporting period:

```php
$period = ReportingPeriod::where('project_id', $projectId)
    ->whereDate('period_date', $date->toDateString())
    ->first();

if (!$period) {
    $period = ReportingPeriod::create([
        'project_id' => $projectId,
        'period_date' => $date->toDateString(),
        'note' => $note,
    ]);
} elseif ($note !== null) {
    $period->note = $note;
    $period->save();
}
```

Maknanya:

- Kalau periode belum ada, sistem membuat reporting period.
- Kalau sudah ada, sistem bisa update note.

Potongan kode mengambil task yang valid pada tanggal snapshot:

```php
$asOfEnd = $date->copy()->endOfDay();
$tasks = Task::withTrashed()
    ->where('project_id', $projectId)
    ->where('created_at', '<=', $asOfEnd)
    ->where(function ($query) use ($asOfEnd) {
        $query->whereNull('deleted_at')
            ->orWhere('deleted_at', '>', $asOfEnd);
    })
    ->get();
```

Maknanya:

- Task yang dibuat setelah tanggal snapshot tidak dihitung.
- Task yang di-archive setelah tanggal snapshot masih bisa dihitung untuk snapshot lama.
- Ini menjaga laporan historis tetap masuk akal.

Potongan kode menghitung task selesai:

```php
$tasksDone = $tasks
    ->filter(function ($task) use ($asOfEnd, $statusAsOf) {
        $status = $statusAsOf[$task->id] ?? $task->status;
        $endActual = $task->end_actual ? Carbon::parse($task->end_actual)->endOfDay() : null;

        return $this->isDoneStatus($status)
            && ($endActual === null || $endActual->lessThanOrEqualTo($asOfEnd));
    })
    ->count();
```

Maknanya:

- Task dihitung selesai jika status pada tanggal snapshot adalah Done/Selesai.
- Kalau ada `end_actual`, tanggal selesai tidak boleh lewat tanggal snapshot.

Potongan kode menghitung overdue:

```php
$overdueCount = $tasks
    ->filter(function ($task) use ($date, $asOfEnd, $statusAsOf) {
        if (empty($task->end_planned)) {
            return false;
        }

        $plannedEnd = Carbon::parse($task->end_planned);
        $status = $statusAsOf[$task->id] ?? $task->status;
        $endActual = $task->end_actual ? Carbon::parse($task->end_actual)->endOfDay() : null;
        $doneByAsOf = $this->isDoneStatus($status)
            && ($endActual === null || $endActual->lessThanOrEqualTo($asOfEnd));

        return $plannedEnd->lessThan($date) && ! $doneByAsOf;
    })
    ->count();
```

Maknanya:

- Task overdue kalau `end_planned` sudah lewat.
- Tetapi task yang sudah selesai pada tanggal snapshot tidak dihitung overdue.

Potongan kode menghitung average cycle time:

```php
$cycleTimes = $tasks
    ->filter(function ($task) use ($asOfEnd) {
        if (! $task->start_actual || ! $task->end_actual) {
            return false;
        }

        return Carbon::parse($task->end_actual)->endOfDay()->lessThanOrEqualTo($asOfEnd);
    })
    ->map(function ($task) {
        $start = Carbon::parse($task->start_actual);
        $end = Carbon::parse($task->end_actual);

        return max(0, $start->diffInDays($end));
    });

$avgCycle = $cycleTimes->isNotEmpty() ? round($cycleTimes->avg(), 2) : 0;
```

Maknanya:

- Cycle time hanya dihitung untuk task yang punya `start_actual` dan `end_actual`.
- Rata-ratanya disimpan sebagai `avg_cycle_time_days`.

Potongan kode menyimpan snapshot:

```php
$snap = KpiSnapshot::updateOrCreate(
    [
        'project_id' => $projectId,
        'period_id' => $period->id,
    ],
    [
        'tasks_total' => $tasksTotal,
        'tasks_done' => $tasksDone,
        'overdue_count' => $overdueCount,
        'avg_cycle_time_days' => $avgCycle,
    ]
);
```

Maknanya:

- Jika snapshot untuk project dan period sudah ada, sistem update.
- Jika belum ada, sistem create.

### 5. Status History untuk KPI

Function penting:

```php
// app/Services/Implementations/KpiSnapshotService.php
protected function taskStatusesAsOf(array $taskIds, Carbon $asOfEnd): array
```

Potongan kode:

```php
$histories = StatusHistory::query()
    ->whereIn('task_id', $taskIds)
    ->orderBy('task_id')
    ->orderBy('created_at')
    ->orderBy('id')
    ->get(['task_id', 'from_status', 'to_status', 'created_at']);
```

Maknanya:

- Sistem mengambil riwayat perubahan status task.
- Ini dipakai untuk mengetahui status task pada tanggal snapshot.

Potongan kode penentuan status:

```php
if ($latestBefore) {
    $result[(int) $taskId] = $latestBefore->to_status;
} elseif ($firstAfter) {
    $result[(int) $taskId] = $firstAfter->from_status;
}
```

Maknanya:

- Kalau ada status history sebelum tanggal snapshot, pakai status terakhir sebelum tanggal itu.
- Kalau belum ada history sebelum tanggal snapshot tetapi ada history setelahnya, pakai `from_status` dari history pertama setelah tanggal itu.

### 6. Controller dan Route

EVM effort route:

```php
// routes/api.php
Route::get('projects/{project}/evm', [EvmController::class, 'projectEvm']);
```

Controller:

```php
// app/Http/Controllers/EvmController.php
public function projectEvm(EvmQueryRequest $request, $project)
{
    $data = $this->service->computeForProjectDate(
        (int) $project,
        $request->validated('date'),
        $request->validated('baseline_id')
    );

    return response()->json($data);
}
```

EVM cost route:

```php
// routes/api.php
Route::get('projects/{project}/evm-cost', [EvmCostController::class, 'projectEvmCost']);
```

Controller:

```php
// app/Http/Controllers/EvmCostController.php
public function projectEvmCost(EvmCostQueryRequest $request, Project $project)
{
    $data = $this->service->computeForProjectDate(
        $project->id,
        $request->validated('as_of'),
        $request->validated('baseline_id')
    );

    return response()->json($data);
}
```

KPI snapshot generate route:

```php
// routes/api.php
Route::post('projects/{project}/kpi-snapshots/generate', [KpiSnapshotController::class, 'generateForProject']);
```

Controller:

```php
// app/Http/Controllers/KpiSnapshotController.php
public function generateForProject(Request $request, $projectId)
```

Maknanya:

- Frontend memanggil controller.
- Controller validasi request.
- Controller memanggil service.
- Service menghitung data dan mengembalikan response.

### 7. Frontend Pemanggil EVM

Komponen utama:

```text
src/components/evm/EvmWidget.tsx
src/components/evm/EvmCostWidget.tsx
```

Halaman yang menampilkan:

```text
src/app/dashboard/projects/[id]/page.tsx
src/app/dashboard/reports/page.tsx
```

Di detail project, tab EVM memanggil widget:

```tsx
{activeTab === "evm" && data && (
  <EvmWidget projectId={data.id} />
)}

{activeTab === "evm_cost" && data && (
  <EvmCostWidget projectId={data.id} />
)}
```

Maknanya:

- User membuka detail project.
- Pilih tab EVM.
- FE render widget.
- Widget memanggil API EVM.
- API mengembalikan PV, EV, AC, SPI, CPI, dan data lain.

### 8. Frontend Planned Effort di Project Tasks

Function:

```tsx
// src/app/dashboard/projects/[id]/page.tsx
function getTaskPlannedEffort(task: Task): { hours: number; source: "assignment" | "fallback" | "none" }
```

Potongan kode:

```tsx
const assignments = Array.isArray(raw?.assignments) ? raw.assignments : [];
const assignmentHours = assignments.reduce((sum: number, assignment: any) => {
  const hours = Number(assignment?.estimated_effort_hours ?? 0);
  return sum + (Number.isFinite(hours) ? hours : 0);
}, 0);

if (assignmentHours > 0) {
  return { hours: assignmentHours, source: "assignment" };
}

const duration = Number(raw?.duration_planned ?? 0);
if (Number.isFinite(duration) && duration > 0) {
  return { hours: duration * 8, source: "fallback" };
}

return { hours: 0, source: "none" };
```

Maknanya:

- Tampilan Project Tasks menampilkan effort dari assignment jika ada.
- Jika tidak ada assignment effort, tampilan fallback ke durasi x 8.
- Jika durasi juga tidak ada, tampil 0.

