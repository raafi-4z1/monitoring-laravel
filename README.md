# Monitoring Laravel

Admin panel monitoring berbasis **Laravel 12** + **MoonShine v4** yang mengintegrasikan data dari **Elasticsearch** ke database MySQL, dilengkapi dengan dashboard, laporan per jam, scheduler otomatis, chart interaktif, dan export Excel/CSV.

---

## Fitur

- **Engine Notif Report** — laporan per jam Engine Notif dari Elasticsearch
- **mTeleplus Report** — laporan per jam mTeleplus dari Elasticsearch
- **TrxPBI Limit Report** — laporan per jam transaksi WIC PBI Cek Limit, dikelompokkan per mata uang
- **TrxPBI Settlement Report** — laporan per jam transaksi WIC PBI Settlement, dikelompokkan per mata uang
- **TrxPBI Loader** — laporan per jam batch job loader: durasi, record diproses, throughput (row/detik), dan status job (success/failed)
- **WIC DB Metric** — laporan per jam metrik server WIC DB: CPU, Memory, Disk
- **WIC APP Metric** — laporan per jam metrik server WIC APP: CPU, Memory, Disk
- **App Metrics** — input manual metrik server (CPU, Memory, Disk, dll.) dengan grafik per jenis metrik
- **Master Aplikasi** — manajemen daftar nama aplikasi (CRUD + soft-delete, khusus Admin)
- **Master Metrik** — manajemen daftar jenis metrik beserta satuan default (CRUD + soft-delete, khusus Admin)
- **Report Sources** — konfigurasi metadata sumber data per layanan, khusus Admin
- **Chart Interaktif** — LineChart & DonutChart via ApexCharts, dikelompokkan per mata uang / per jenis metrik, ikut filter DateRange & filter tipe metrik
- **Reactive Form** — saat memilih metrik, kolom satuan otomatis terisi dari `satuan_default` master metrik
- **Scheduler Otomatis** — fetch data dari Elasticsearch setiap hari otomatis
- **Fetch Manual** — ambil data rentang tanggal tertentu langsung dari admin panel (maks 90 hari)
- **Filter Tanggal** — filter data berdasarkan rentang tanggal dengan `DateRange`
- **Pagination & Sort** — navigasi data dengan dropdown per page dan pengurutan kolom
- **Export Excel & CSV** — export data ke file `.xlsx` atau `.csv` dengan format kolom lengkap termasuk metadata report source
- **Auto Export CSV TrxPBI** — setelah fetch harian selesai, data TrxPBI Limit & Settlement kemarin diekspor otomatis ke satu file CSV
- **Auto Export CSV WIC Metric** — setelah fetch WIC APP selesai, data WIC DB + WIC APP kemarin diekspor otomatis ke satu file CSV
- **Auto Export CSV TrxPBI Loader** — setelah fetch TrxPBI Loader selesai, data batch job kemarin diekspor otomatis ke CSV
- **Role-based Access (Dinamis)** — Admin selalu akses penuh; role lain diatur per-resource lewat halaman Hak Akses Role (checkbox matrix, tersimpan di database, default tertutup)

---

## Struktur Proyek

```
monitoring-laravel/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── FetchEngineNotifReport.php
│   │       ├── FetchMteleplusReport.php
│   │       ├── FetchTrxPbiLimitReport.php
│   │       ├── FetchTrxPbiSettlementReport.php
│   │       ├── ExportTrxPbiCsv.php              # Export gabungan TrxPBI Limit+Settlement ke CSV
│   │       ├── FetchTrxPbiLoaderReport.php       # Fetch batch job TrxPBI Loader
│   │       ├── ExportTrxPbiLoaderCsv.php         # Export TrxPBI Loader ke CSV
│   │       ├── FetchWicMetricReport.php          # Fetch WIC DB Metric
│   │       ├── FetchWicAppMetricReport.php       # Fetch WIC APP Metric
│   │       └── ExportWicMetricCsv.php            # Export gabungan WIC DB+APP ke CSV
│   ├── Enums/
│   │   └── MetricUnit.php
│   ├── Models/
│   │   ├── AppMetric.php
│   │   ├── MasterAplikasi.php
│   │   ├── MasterMetrik.php
│   │   ├── ReportSource.php
│   │   ├── ResourcePermission.php               # Daftar resource yang bisa diatur per role
│   │   ├── EngineNotifReport.php
│   │   ├── MteleplusReport.php
│   │   ├── TrxPbiLimitReport.php
│   │   ├── TrxPbiSettlementReport.php
│   │   ├── TrxPbiLoaderReport.php                # Batch job per jam per status (success/failed)
│   │   ├── WicDbMetricReport.php                 # Metrik WIC DB per jam per tipe (cpu/memory/disk)
│   │   └── WicAppMetricReport.php                # Metrik WIC APP per jam per tipe (cpu/memory/disk)
│   ├── MoonShine/
│   │   ├── Concerns/
│   │   │   └── GuardsFetchPageAccess.php         # Guard permission untuk halaman Fetch Manual
│   │   ├── Handlers/
│   │   │   └── GuardedExportHandler.php          # Guard permission + disk privat untuk export Excel/CSV
│   │   ├── Middleware/
│   │   │   └── GuardResourcePermission.php       # Guard permission global untuk semua route ber-resource
│   │   ├── Layouts/
│   │   │   └── MoonShineLayout.php
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   └── RolePermissionsPage.php           # Halaman Hak Akses Role
│   │   └── Resources/
│   │       ├── AppMetric/
│   │       ├── MasterAplikasi/
│   │       ├── MasterMetrik/
│   │       ├── ReportSource/
│   │       ├── EngineNotifReport/
│   │       ├── MteleplusReport/
│   │       ├── TrxPbiLimitReport/
│   │       ├── TrxPbiSettlementReport/
│   │       ├── TrxPbiLoaderReport/
│   │       ├── WicDbMetricReport/
│   │       │   ├── Pages/
│   │       │   │   ├── WicDbMetricReportIndexPage.php  # Table + chart CPU/Memory/Disk
│   │       │   │   └── WicDbMetricReportFetchPage.php
│   │       │   └── WicDbMetricReportResource.php
│   │       ├── WicAppMetricReport/
│   │       │   ├── Pages/
│   │       │   │   ├── WicAppMetricReportIndexPage.php # Table + chart CPU/Memory/Disk
│   │       │   │   └── WicAppMetricReportFetchPage.php
│   │       │   └── WicAppMetricReportResource.php
│   │       ├── MoonShineUser/
│   │       └── MoonShineUserRole/
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── MoonShineServiceProvider.php          # Registrasi resource, page, & authorization rules
│   └── Services/
│       ├── ElasticsearchService.php              # Query & parse per index (termasuk WIC Metric)
│       ├── EngineNotifReportService.php
│       ├── MteleplusReportService.php
│       ├── TrxPbiLimitReportService.php
│       ├── TrxPbiSettlementReportService.php
│       ├── TrxPbiLoaderReportService.php
│       ├── WicDbMetricReportService.php
│       └── WicAppMetricReportService.php
├── config/
│   └── elasticsearch.php
├── database/
│   ├── migrations/
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── MasterMetrikSeeder.php
│       ├── ReportSourceSeeder.php
│       └── ResourcePermissionSeeder.php          # Seed resource yang bisa diatur per role
└── routes/
    ├── web.php
    └── console.php
```

---

## Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/raafi-4z1/monitoring-laravel.git
cd monitoring-laravel
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Konfigurasi Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` dan sesuaikan koneksi database MySQL serta koneksi Elasticsearch (host, username, password) sesuai lingkungan masing-masing.

Opsional — folder tujuan file export CSV (default: `storage/app/exports` kalau dikosongkan):

```env
TRX_PBI_EXPORT_PATH=
WIC_METRIC_EXPORT_PATH=
TRX_PBI_LOADER_EXPORT_PATH=
```

Opsional — prefix URL admin panel (default: `admin` kalau dikosongkan):

```env
MOONSHINE_ROUTE_PREFIX=monitoring
```

> **Catatan path Windows:** Gunakan forward slash `/` atau double backslash `\\`. Backslash tunggal `\` akan menyebabkan error parsing `.env`.

### 4. Migrasi & Seed Database

```bash
php artisan migrate --seed
```

Perintah `--seed` akan mengisi data awal: metrik default (CPU, MEMORY, DISK, dll.), konfigurasi report source, dan daftar resource yang bisa diatur per role.

### 5. Buat Admin Panel

```bash
php artisan moonshine:user
```

Perintah ini membuat akun pertama dengan role **Admin** untuk login ke panel. Jalankan sekali saat fresh install.

### 6. Jalankan Server

```bash
php artisan serve
```

Akses admin panel di: `http://127.0.0.1:8000/{MOONSHINE_ROUTE_PREFIX}` (default `/admin` kalau tidak diatur).

> Root URL `/` otomatis redirect ke prefix yang dikonfigurasi.

### Akses via LAN (HTTPS)

Untuk mengakses dari perangkat lain dalam satu jaringan (mis. via Laragon), perlu setup SSL/HTTPS tambahan di web server dan penyesuaian `APP_URL`. Tanyakan tim development untuk detail konfigurasinya.

---

## Scheduler

Scheduler didefinisikan di `routes/console.php`, menjalankan fetch data dari Elasticsearch setiap hari secara otomatis, lalu auto-export CSV setelah fetch tertentu selesai.

Alur harian (ringkas):

| Waktu | Aksi |
|---|---|
| Dini hari | Fetch Engine Notif dari Elasticsearch |
| Dini hari | Fetch mTeleplus dari Elasticsearch |
| Dini hari | Fetch TrxPBI Limit dari Elasticsearch |
| Dini hari | Fetch TrxPBI Settlement → **auto export** TrxPBI CSV |
| Dini hari | Fetch WIC DB Metric dari Elasticsearch |
| Dini hari | Fetch WIC APP Metric → **auto export** WIC Metric CSV |
| Dini hari | Fetch TrxPBI Loader → **auto export** TrxPBI Loader CSV |

File CSV disimpan di folder yang dikonfigurasi di `.env` (`TRX_PBI_EXPORT_PATH` / `WIC_METRIC_EXPORT_PATH` / `TRX_PBI_LOADER_EXPORT_PATH`), terstruktur per tahun/bulan/tanggal. Nama file dibedakan lewat `kode_prefix` di tabel `report_sources` (mis. `BP` untuk TrxPBI, `SPB` untuk TrxPBI Loader, `SPI` untuk WIC Metric), sehingga aman berdampingan dalam satu folder.

### Menjalankan Scheduler

**Development — Terminal (polling tiap menit):**

```bash
php artisan schedule:work
```

**Windows — Windows Task Scheduler:**

Jadwalkan `php artisan schedule:run` berjalan tiap menit dari direktori project.

**Production (Linux) — Crontab:**

```bash
* * * * * cd /var/www/monitoring-laravel && php artisan schedule:run >> /dev/null 2>&1
```

---

## Artisan Commands

```bash
# Fetch data kemarin dari Elasticsearch
php artisan report:fetch-engine-notif
php artisan report:fetch-mteleplus
php artisan report:fetch-trx-pbi-limit
php artisan report:fetch-trx-pbi-settlement
php artisan report:fetch-trx-pbi-loader
php artisan report:fetch-trx-pbi-loader --date=YYYY-MM-DD    # tanggal tertentu
php artisan report:fetch-wic-metric
php artisan report:fetch-wic-app-metric

# Export CSV
php artisan report:export-trx-pbi-csv                          # TrxPBI kemarin
php artisan report:export-trx-pbi-csv --date=YYYY-MM-DD         # TrxPBI tanggal tertentu
php artisan report:export-trx-pbi-loader-csv                    # TrxPBI Loader kemarin
php artisan report:export-trx-pbi-loader-csv --date=YYYY-MM-DD  # TrxPBI Loader tanggal tertentu
php artisan report:export-wic-metric-csv                        # WIC Metric kemarin
php artisan report:export-wic-metric-csv --date=YYYY-MM-DD      # WIC Metric tanggal tertentu

# Utilitas
php artisan schedule:run
php artisan schedule:list
php artisan optimize:clear
```

---

## Alur Data

```
Elasticsearch (beberapa index sumber data)
     │
     ├── Otomatis: scheduler harian (lihat tabel Scheduler di atas)
     └── Manual: dari panel (form fetch per rentang tanggal, maks 90 hari)
               │
               ▼
     Service::fetchAndStore(Carbon $date)
               │
               ├── ElasticsearchService::query...()
               └── Model::updateOrCreate()
                         │
                         ▼
               Database MySQL
                         │
                         ├── MoonShine Panel
                         │        ├── Table (filter, sort, pagination, export Excel/CSV)
                         │        └── Chart (Fragment async + filter tipe metrik)
                         │
                         └── Auto Export CSV (TrxPBI, TrxPBI Loader & WIC Metric)
```

---

## Role Panel & Hak Akses

Sistem role bersifat dinamis, dikelola dari database — bukan hardcode.

- **Admin** selalu memiliki akses penuh ke semua resource, tidak bisa dibatasi. Admin juga tidak bisa menurunkan role sendiri, atau mengubah/menghapus akun Admin lain (self & cross-admin protection).
- **Role lain** aksesnya diatur per-resource lewat halaman **Manajemen → Hak Akses Role**:
  - Tab **Kelola Resource** — daftar resource yang bisa diatur per role, bisa tambah/hapus dari resource yang tersedia.
  - Tab **Atur Akses per Role** — matrix checkbox Role × Resource. Ada checkbox "select all" per baris (role) dan per kolom (resource).
- **Default tertutup**: resource yang belum ditambahkan ke "Kelola Resource", atau role yang belum dicentang untuk suatu resource, otomatis **tidak dapat diakses** (fail-closed) — kecuali oleh Admin.
- Resource sistem (Users, Roles, Master Aplikasi, Master Metrik, Report Sources) selalu admin-only secara permanen, tidak bisa dipindah ke role lain.
- Menu sidebar, halaman resource, halaman Fetch Manual, export Excel/CSV, dan Dashboard semuanya mengikuti permission yang sama secara otomatis — tidak perlu ubah kode saat admin mengubah permission dari UI.
- Permission ditegakkan di level middleware (bukan cuma tampilan menu), jadi resource yang tidak diizinkan tetap tidak bisa diakses walau URL diketik langsung. File hasil export juga disimpan di storage privat (tidak bisa diunduh langsung tanpa login).

---

## Halaman Admin Panel

### Menu: Manajemen (khusus Admin)

**Users** — kelola akun panel

**Roles** — kelola daftar role

**Hak Akses Role** — atur resource apa saja yang bisa diakses tiap role (lihat bagian Role Panel & Hak Akses)

### Menu: App Metric

**Data Metric** — input manual metrik aplikasi, dengan grafik per jenis metrik

**Master Aplikasi** — daftar nama aplikasi (khusus Admin)

**Master Metrik** — daftar jenis metrik & satuan default (khusus Admin)

**Report Sources** — konfigurasi metadata sumber data (khusus Admin)

### Menu: Elastic

**Engine Notif Reports** — tabel per jam, chart, fetch manual, export Excel & CSV

**Mteleplus Reports** — tabel per jam, chart, fetch manual, export Excel & CSV

**TrxPBI Limit** — tabel per jam per mata uang, chart (ValueMetric + LineChart + DonutChart), fetch manual, export

**TrxPBI Settlement** — tabel per jam per mata uang, chart (ValueMetric + LineChart + DonutChart), fetch manual, export

**TrxPBI Loader** — tabel batch job per jam per status; chart Record Processed, Throughput (row/detik), dan Durasi (success vs failed); filter status job; fetch manual; export Excel & CSV

### Menu: WIC Metric

**WIC DB** — metrik server WIC DB per jam; chart CPU (Max/Avg/Min %), Memory (Max/Avg/Min %), Disk Usage (% semua disk dalam satu chart); filter tipe metrik; export Excel & CSV

**WIC APP** — identik dengan WIC DB namun data dari server WIC APP

---

## Dependencies Utama

```json
{
    "php": "^8.2",
    "laravel/framework": "^12.0",
    "moonshine/moonshine": "^4.13",
    "moonshine/apexcharts": "^3.1",
    "moonshine/import-export": "2.0.0",
    "rap2hpoutre/fast-excel": "^2.0"
}
```
