# Monitoring Laravel

Admin panel monitoring berbasis **Laravel 12** + **MoonShine v4** yang mengintegrasikan data dari **Elasticsearch** ke database MySQL, dilengkapi dengan dashboard, laporan harian, scheduler otomatis, dan export Excel.

---

## Tech Stack

| Teknologi | Versi |
|---|---|
| PHP | 8.2+ |
| Laravel | 12.x |
| MoonShine | 4.x |
| moonshine/import-export | 2.x |
| MySQL | 8.x |
| Elasticsearch | 7.x / 8.x |

---

## Fitur

- **Dashboard** — 
- **Engine Notif Report** — laporan harian notifikasi (MVRK, SMS, Email) dari Elasticsearch
- **Scheduler Otomatis** — fetch data dari Elasticsearch setiap hari pukul 00:05
- **Fetch Manual** — ambil data rentang tanggal tertentu langsung dari admin panel
- **Filter Tanggal** — filter data berdasarkan rentang tanggal dengan `DateRange`
- **Pagination & Sort** — navigasi data dengan dropdown per page dan pengurutan kolom
- **Export Excel** — export data ke file `.xlsx` bawaan MoonShine
- **User Management** — manajemen user dengan CRUD lengkap

---

## Struktur Proyek

```
app/
├── Console/
│   └── Commands/
│       └── FetchEngineNotifReport.php        # Artisan command fetch ES
├── Models/
│   ├── User.php
│   └── EngineNotifReport.php                 # Model + accessor kalkulasi
├── MoonShine/
│   ├── Layouts/
│   │   └── MoonShineLayout.php               # Layout & konfigurasi menu
│   ├── Pages/
│   │   └── Dashboard.php                     # Halaman dashboard
│   └── Resources/
│       ├── User/
│       │   ├── UserResource.php
│       │   └── Pages/
│       │       ├── UserIndexPage.php
│       │       ├── UserFormPage.php
│       │       └── UserDetailPage.php
│       └── EngineNotifReport/
│           ├── EngineNotifReportResource.php
│           └── Pages/
│               ├── EngineNotifReportIndexPage.php  # Table + fetch manual
│               └── EngineNotifReportDetailPage.php
├── Providers/
│   └── MoonShineServiceProvider.php
routes/
│   └── console.php                           # Definisi scheduler
└── Services/
    ├── ElasticsearchService.php              # Query ke Elasticsearch
    └── EngineNotifReportService.php          # Fetch & simpan ke DB
```

---

## Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/username/monitoring-laravel.git
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

Edit `.env` dan sesuaikan konfigurasi berikut:

```env
# Database MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=89
DB_DATABASE=monitoring_laravel
DB_USERNAME=root
DB_PASSWORD=

# Elasticsearch
ES_HOST=https://192.168.0.1:88
ES_USERNAME=app
ES_PASSWORD=app
```

### 4. Buat Config Elasticsearch

Buat file `config/elasticsearch.php`:

```php
return [
    'host'     => env('ES_HOST', 'https://192.168.0.1:88'),
    'username' => env('ES_USERNAME', 'app'),
    'password' => env('ES_PASSWORD', 'app'),
];
```

### 5. Migrasi Database

```bash
php artisan migrate
```

### 6. Buat Admin MoonShine

```bash
php artisan moonshine:user
```

### 7. Jalankan Server

```bash
php artisan serve
```

Akses admin panel di: `http://127.0.0.1:8000/admin`

---

## Scheduler

### Konfigurasi (Laravel 12)

Scheduler didefinisikan di `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

// Fetch data Elasticsearch setiap hari jam 00:05
Schedule::command('report:fetch-engine-notif')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/engine-notif-fetch.log'));
```

### Menjalankan Scheduler

**Development (Windows/Laragon) — Task Scheduler:**

```
Program  : C:\laragon\bin\php\php-8.2\php.exe
Arguments: artisan schedule:run
Start in : C:\path\to\monitoring-laravel
Repeat   : Every 1 minute
```

**Development — Terminal:**

```bash
php artisan schedule:work
```

**Production (Linux) — Crontab:**

```bash
* * * * * cd /var/www/monitoring-laravel && php artisan schedule:run >> /dev/null 2>&1
```

---

## Artisan Commands

```bash
# Fetch data kemarin dari Elasticsearch (dijalankan scheduler otomatis)
php artisan report:fetch-engine-notif

# Fetch data tanggal tertentu
php artisan report:fetch-engine-notif --date=2026-05-25

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
     ├─── Otomatis: scheduler setiap hari 00:05
     │         │
     │         ▼
     │    FetchEngineNotifReport (Artisan Command)
     │
     └─── Manual: dari admin panel (rentang tanggal)
               │
               ▼
     EngineNotifReportService::fetchAndStore()
               │
               ▼
     Database MySQL (engine_notif_reports)
               │
               ▼
     MoonShine Admin Panel
          ├── Filter DateRange
          ├── Pagination & Sort
          ├── Per Page (5/10/20/50/100)
          └── Export Excel (.xlsx)
```

---

## Struktur Tabel Database

### `engine_notif_reports`

> Kolom total (`mvrk_total`, `sms_total`, `email_total`, `total_success`, `total_fail`) **tidak disimpan di DB** — dihitung otomatis via **Eloquent Accessor** di model untuk menjaga normalisasi data.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `report_date` | date | Tanggal laporan (unique) |
| `mvrk_success` | bigint | Jumlah MVRK berhasil |
| `mvrk_fail` | bigint | Jumlah MVRK gagal |
| `sms_success` | bigint | Jumlah SMS berhasil |
| `sms_fail` | bigint | Jumlah SMS gagal |
| `email_success` | bigint | Jumlah Email berhasil |
| `email_fail` | bigint | Jumlah Email gagal |
| `avg_response_time` | decimal(10,2) | Rata-rata response time (detik) |
| `created_at` | timestamp | Waktu dibuat |
| `updated_at` | timestamp | Waktu diupdate |

**Kolom kalkulasi via Accessor (tidak di DB):**

| Accessor | Kalkulasi |
|---|---|
| `mvrk_total` | `mvrk_success + mvrk_fail` |
| `sms_total` | `sms_success + sms_fail` |
| `email_total` | `email_success + email_fail` |
| `total_success` | `mvrk_success + sms_success + email_success` |
| `total_fail` | `mvrk_fail + sms_fail + email_fail` |

---

## Halaman Admin Panel

### Dashboard (`/admin`)
- 

### Engine Notif Report (`/admin/resource/engine-notif-report-resource`)
- Filter data berdasarkan rentang tanggal (`DateRange`)
- Default filter: 2 minggu terakhir
- Tabel laporan harian dengan pagination & sort per kolom
- Dropdown per page (5 / 10 / 20 / 50 / 100)
- Tombol **Export Excel** untuk download data sesuai filter
- Form **Fetch Manual** — ambil data dari Elasticsearch berdasarkan rentang tanggal dan simpan ke DB
- Info alert data terakhir yang tersimpan

### Clients / Users (`/admin/resource/user-resource`)
- CRUD user lengkap
- Filter dan pencarian by name & email
- Metrics: Total User, User Baru Bulan Ini, User Baru Hari Ini
- Dropdown per page
- Export Excel

---

## Dependencies Utama

```json
{
    "php": "^8.2",
    "laravel/framework": "^12.0",
    "moonshine/moonshine": "^4.13",
    "moonshine/import-export": "^2.0"
}
```
