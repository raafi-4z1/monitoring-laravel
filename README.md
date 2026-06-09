# Monitoring Laravel

Admin panel monitoring berbasis **Laravel 12** + **MoonShine v4** yang mengintegrasikan data dari **Elasticsearch** ke database MySQL, dilengkapi dengan dashboard, laporan harian, scheduler otomatis, chart interaktif, dan export Excel.

---

## Fitur

- **Engine Notif Report** — laporan harian Engine Notif dari Elasticsearch
- **mTeleplus Report** — laporan harian mTeleplus dari Elasticsearch
- **App Metrics** — input manual metrik server (CPU, Memory, Disk, dll.) dengan grafik per jenis metrik
- **Chart Interaktif** — LineChart & DonutChart via ApexCharts, ikut filter DateRange
- **Scheduler Otomatis** — fetch data dari Elasticsearch setiap hari otomatis
- **Fetch Manual** — ambil data rentang tanggal tertentu langsung dari admin panel
- **Filter Tanggal** — filter data berdasarkan rentang tanggal dengan `DateRange`
- **Pagination & Sort** — navigasi data dengan dropdown per page dan pengurutan kolom
- **Export Excel** — export data ke file `.xlsx` bawaan MoonShine
- **User Management** — manajemen user dengan CRUD lengkap

---

## Struktur Proyek

```
monitoring-laravel/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── FetchEngineNotifReport.php       # Artisan command fetch Engine Notif
│   │       └── FetchMteleplusReport.php          # Artisan command fetch mTeleplus
│   ├── Models/
│   │   ├── User.php
│   │   ├── EngineNotifReport.php                # Model + accessor kalkulasi
│   │   └── MteleplusReport.php                  # Model + accessor kalkulasi
│   ├── MoonShine/
│   │   ├── Layouts/
│   │   │   └── MoonShineLayout.php              # Layout & konfigurasi menu
│   │   ├── Pages/
│   │   │   └── Dashboard.php
│   │   └── Resources/
│   │       ├── EngineNotifReport/
│   │       │   ├── Pages/
│   │       │   │   ├── EngineNotifReportIndexPage.php  # Table + chart + filter
│   │       │   │   └── EngineNotifReportFetchPage.php  # Form fetch manual
│   │       │   └── EngineNotifReportResource.php
│   │       ├── MteleplusReport/
│   │       │   ├── Pages/
│   │       │   │   ├── MteleplusReportIndexPage.php    # Table + chart + filter
│   │       │   │   └── MteleplusReportFetchPage.php    # Form fetch manual
│   │       │   └── MteleplusReportResource.php
│   │       ├── AppMetric/
│   │       │   ├── Pages/
│   │       │   │   ├── AppMetricIndexPage.php      # Table + grafik per metrik
│   │       │   │   └── AppMetricFormPage.php       # Form input manual metrik
│   │       │   └── AppMetricResource.php
│   │       ├── MoonShineUser/
│   │       │   │   ├── MoonShineUserFormPage.php
│   │       │   │   └── MoonShineUserIndexPage.php
│   │       │   └── MoonShineUserResource.php
│   │       ├── MoonShineUserRole/
│   │       │   ├── Pages/
│   │       │   │   ├── MoonShineUserRoleFormPage.php
│   │       │   │   └── MoonShineUserRoleIndexPage.php
│   │       │   └── MoonShineUserRoleResource.php
│   │       └── User/
│   │           ├── Pages/
│   │           │   ├── UserDetailPage.php
│   │           │   ├── UserFormPage.php
│   │           │   └── UserIndexPage.php
│   │           └── UserResource.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── MoonShineServiceProvider.php
│   └── Services/
│       ├── ElasticsearchService.php             # Query ke Elasticsearch
│       ├── EngineNotifReportService.php          # Fetch & simpan Engine Notif
│       └── MteleplusReportService.php            # Fetch & simpan mTeleplus
├── config/
│   └── elasticsearch.php
├── database/
│   └── migrations/
│       ├── 0001_01_01_000000_create_users_table.php
│       ├── 0001_01_01_000001_create_cache_table.php
│       ├── 0001_01_01_000002_create_jobs_table.php
│       ├── 2020_10_04_115514_create_moonshine_roles_table.php
│       ├── 2020_10_05_173148_create_moonshine_tables.php
│       ├── 2026_05_22_014556_create_notifications_table.php
│       ├── 2026_05_26_033044_create_engine_notif_reports_table.php
│       ├── 2026_06_04_140613_create_mteleplus_reports_table.php
│       ├── 2026_06_09_000001_create_app_metrics_table.php
│       └── 2026_06_09_000002_update_app_metrics_recorded_at_microseconds.php
└── routes/
    └── console.php                              # Definisi scheduler
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

Edit `.env` dan sesuaikan:

```env
# Database MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=monitoring_laravel
DB_USERNAME=root
DB_PASSWORD=

# Elasticsearch
ES_HOST=https://192.168.0.1:88
ES_USERNAME=app
ES_PASSWORD=app
```

### 4. Migrasi Database

```bash
php artisan migrate
```

### 5. Buat Admin MoonShine

```bash
php artisan moonshine:user
```

### 6. Jalankan Server

```bash
php artisan serve
```

Akses admin panel di: `http://127.0.0.1:8000/admin`

---

## Scheduler

Scheduler didefinisikan di `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('report:fetch-engine-notif')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/engine-notif-fetch.log'));

Schedule::command('report:fetch-mteleplus')
    ->dailyAt('00:07')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/mteleplus-fetch.log'));
```

### Menjalankan Scheduler

**Development — Terminal:**

```bash
php artisan schedule:work
```

**Development (Windows) — Task Scheduler:**

```
Program  : C:\laragon\bin\php\php-8.2\php.exe
Arguments: artisan schedule:run
Start in : C:\path\to\monitoring-laravel
Repeat   : Every 1 minute
```

**Production (Linux) — Crontab:**

```bash
* * * * * cd /var/www/monitoring-laravel && php artisan schedule:run >> /dev/null 2>&1
```

---

## Artisan Commands

```bash
# Fetch Engine Notif kemarin dari Elasticsearch
php artisan report:fetch-engine-notif

# Fetch mTeleplus kemarin dari Elasticsearch
php artisan report:fetch-mteleplus

# Jalankan scheduler manual
php artisan schedule:run

# Lihat semua scheduled jobs
php artisan schedule:list

# Clear cache
php artisan optimize:clear
```

---

## Alur Data

```
Elasticsearch
     │
     ├── Otomatis: scheduler harian
     │       ├── 00:05 → report:fetch-engine-notif
     │       └── 00:07 → report:fetch-mteleplus
     │
     └── Manual: dari panel (form fetch per rentang tanggal)
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
                         ▼
               MoonShine Panel
                    ├── Table (filter, sort, pagination, export)
                    └── Chart (Fragment async + withQueryParams)
                         ├── ValueMetric
                         ├── LineChartMetric
                         └── DonutChartMetric
```

---

## Struktur Tabel Database

### `engine_notif_reports`

> Kolom total **tidak disimpan di DB** — dihitung via **Eloquent Accessor**.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `report_date` | date | Tanggal laporan (unique) |
| `mvrk_success` | bigint | MVRK berhasil |
| `mvrk_fail` | bigint | MVRK gagal |
| `sms_success` | bigint | SMS berhasil |
| `sms_fail` | bigint | SMS gagal |
| `email_success` | bigint | Email berhasil |
| `email_fail` | bigint | Email gagal |
| `avg_response_time` | decimal(10,2) | Rata-rata response time (detik) |
| `avg_lifespan` | decimal(10,2) | Rata-rata lifespan (milidetik) |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

**Accessor (tidak di DB):**

| Accessor | Kalkulasi |
|---|---|
| `mvrk_total` | `mvrk_success + mvrk_fail` |
| `sms_total` | `sms_success + sms_fail` |
| `email_total` | `email_success + email_fail` |
| `total_success` | `mvrk_success + sms_success + email_success` |
| `total_fail` | `mvrk_fail + sms_fail + email_fail` |

### `app_metrics`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `recorded_at` | timestamp(6) | Waktu pencatatan (microsecond precision, auto-unique) |
| `nama_aplikasi` | varchar | Nama aplikasi — disimpan **UPPERCASE** otomatis |
| `metric` | varchar | Jenis metrik (CPU, MEMORY, dll.) — **UPPERCASE** otomatis |
| `value` | varchar | Nilai metrik (mis. `75`, `2.4`) |
| `satuan` | varchar | Satuan metrik (mis. `%`, `GB`, `ms`) |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

> **Catatan:** Second dan microsecond pada `recorded_at` diisi otomatis dari waktu saat menyimpan — user hanya perlu memilih tanggal, jam, dan menit.

### `mteleplus_reports`

> Kolom total **tidak disimpan di DB** — dihitung via **Eloquent Accessor**.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `report_date` | date | Tanggal laporan (unique) |
| `akt_success` | bigint | AKT berhasil |
| `akt_fail` | bigint | AKT gagal |
| `rpin_success` | bigint | RPIN berhasil |
| `rpin_fail` | bigint | RPIN gagal |
| `total_incoming` | bigint | Total incoming |
| `total_outgoing` | bigint | Total outgoing |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

**Accessor (tidak di DB):**

| Accessor | Kalkulasi |
|---|---|
| `akt_total` | `akt_success + akt_fail` |
| `rpin_total` | `rpin_success + rpin_fail` |
| `total_success` | `akt_success + rpin_success` |
| `total_fail` | `akt_fail + rpin_fail` |

---

## Halaman Admin Panel

### Engine Notif Reports (`/MoonShine/resource/engine-notif-report-resource`)

- Tabel harian
- Dropdown per page (5/10/20/50/100) + sort per kolom + column selection
- **Chart** (Fragment async, ikut filter)
- **Fetch Manual** — form ambil data dari ES (maks 90 hari)
- **Export Excel** — export sesuai filter aktif

### mTeleplus Reports (`/MoonShine/resource/mteleplus-report-resource`)

- Tabel harian
- Dropdown per page (5/10/20/50/100) + sort per kolom + column selection
- **Chart** (Fragment async, ikut filter)
- **Fetch Manual** — form ambil data dari ES (maks 90 hari)
- **Export Excel** — export sesuai filter aktif

### App Metrics (`/MoonShine/resource/app-metric-resource`)

- Input manual metrik server (CPU, Memory, Disk, Response Time, dll.)
- Timestamp otomatis dengan presisi microsecond (`TIMESTAMP(6)`) — tidak akan pernah duplikat meskipun input di menit yang sama
- Nama aplikasi & metrik disimpan **uppercase** otomatis
- **Grafik per jenis metrik** — setiap metrik (CPU, MEMORY, dll.) mendapat LineChart sendiri, satu garis per aplikasi
- Filter DateRange (default 7 hari terakhir) + filter aplikasi & metrik
- Dropdown per page + column selection

---

## Dependencies Utama

```json
{
    "php": "^8.2",
    "laravel/framework": "^12.0",
    "moonshine/moonshine": "^4.13",
    "moonshine/apexcharts": "^3.1",
    "moonshine/import-export": "2.0.0"
}
```
