# Monitoring Laravel

Admin panel monitoring berbasis **Laravel 12** + **MoonShine v4** yang mengintegrasikan data dari **Elasticsearch** ke database MySQL, dilengkapi dengan dashboard, laporan harian, scheduler otomatis, dan export Excel.

---

## Tech Stack

| Teknologi | Versi |
|---|---|
| PHP | 8.2+ |
| Laravel | 12.x |
| MoonShine | 4.x |
| MySQL | 8.x |
| Elasticsearch | 7.x / 8.x |

---

## Fitur

- **Dashboard** — ringkasan statistik user dan tabel data terbaru
- **Engine Notif Report** — laporan harian notifikasi (MVRK, SMS, Email) dari Elasticsearch
- **Scheduler Otomatis** — fetch data dari Elasticsearch setiap hari pukul 00:05
- **Fetch Manual** — ambil data hari tertentu langsung dari admin panel
- **Filter Tanggal** — filter data berdasarkan rentang tanggal
- **Pagination & Sort** — navigasi data dengan pagination dan pengurutan kolom
- **Export Excel** — export data ke file `.xlsx` bawaan MoonShine
- **User Management** — manajemen user dengan CRUD lengkap

---

## Struktur Proyek

```
app/
├── Console/
│   └── Commands/
│       └── FetchEngineNotifReport.php   # Artisan command fetch ES
├── Models/
│   ├── User.php
│   └── EngineNotifReport.php            # Model laporan harian
├── MoonShine/
│   ├── Layouts/
│   │   └── MoonShineLayout.php          # Layout & menu konfigurasi
│   ├── Pages/
│   │   └── Dashboard.php                # Halaman dashboard
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
│               ├── EngineNotifReportIndexPage.php
│               └── EngineNotifReportDetailPage.php
├── Providers/
│   └── MoonShineServiceProvider.php
└── Services/
    ├── ElasticsearchService.php         # Query ke Elasticsearch
    └── EngineNotifReportService.php     # Fetch & simpan ke DB
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

## Konfigurasi Elasticsearch

Buat file `config/elasticsearch.php`:

```php
return [
    'host'     => env('ES_HOST', 'https://192.168.0.1:88'),
    'username' => env('ES_USERNAME', 'app'),
    'password' => env('ES_PASSWORD', 'app'),
];
```

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
Program : C:\laragon\bin\php\php-8.2\php.exe
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
# Fetch data kemarin dari Elasticsearch
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
     ▼  (setiap hari 00:05 via scheduler)
FetchEngineNotifReport (Artisan Command)
     │
     ▼
EngineNotifReportService::fetchAndStore()
     │
     ▼
Database MySQL (engine_notif_reports)
     │
     ▼
MoonShine Admin Panel
     ├── Filter tanggal
     ├── Pagination & Sort
     └── Export Excel (.xlsx)
```

---

## Struktur Tabel Database

### `engine_notif_reports`

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
| `avg_response_time` | decimal | Rata-rata response time (detik) |
| `created_at` | timestamp | Waktu dibuat |
| `updated_at` | timestamp | Waktu diupdate |

---

## Halaman Admin Panel

### Dashboard (`/admin`)
- 

### Engine Notif Report (`/admin/resource/engine-notif-report-resource`)
- Filter data berdasarkan tanggal
- Tabel laporan harian dengan pagination & sort per kolom
- Tombol **Export Excel** untuk download data

### Clients / Users (`/admin/resource/user-resource`)
- CRUD user lengkap
- Filter dan pencarian
- Metrics: Total User, User Baru Bulan Ini, User Baru Hari Ini
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
