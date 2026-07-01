# Monitoring Laravel

Admin panel monitoring berbasis **Laravel 12** + **MoonShine v4** yang mengintegrasikan data dari **Elasticsearch** ke database MySQL, dilengkapi dengan dashboard, laporan harian, scheduler otomatis, chart interaktif, dan export Excel.

---

## Fitur

- **Engine Notif Report** — laporan harian Engine Notif dari Elasticsearch
- **mTeleplus Report** — laporan harian mTeleplus dari Elasticsearch
- **TrxPBI Limit Report** — laporan per jam transaksi WIC PBI Cek Limit (index `wic-trx-pbi-ceklimit*`), dikelompokkan per CCY2
- **TrxPBI Settlement Report** — laporan per jam transaksi WIC PBI Settlement (index `log-wic-trx-pbi*`), dikelompokkan per CCY2
- **App Metrics** — input manual metrik server (CPU, Memory, Disk, dll.) dengan grafik per jenis metrik
- **Master Aplikasi** — manajemen daftar nama aplikasi (CRUD + soft-delete, khusus Admin)
- **Master Metrik** — manajemen daftar jenis metrik beserta satuan default (CRUD + soft-delete, khusus Admin)
- **Chart Interaktif** — LineChart & DonutChart via ApexCharts, dikelompokkan per CCY2 / per jenis metrik, ikut filter DateRange
- **Reactive Form** — saat memilih metrik, kolom satuan otomatis terisi dari `satuan_default` master metrik
- **Scheduler Otomatis** — fetch data dari Elasticsearch setiap hari otomatis
- **Fetch Manual** — ambil data rentang tanggal tertentu langsung dari admin panel (maks 90 hari)
- **Filter Tanggal** — filter data berdasarkan rentang tanggal dengan `DateRange`
- **Pagination & Sort** — navigasi data dengan dropdown per page dan pengurutan kolom
- **Export Excel** — export data ke file `.xlsx` bawaan MoonShine
- **Role-based Access** — dua role panel: **Admin** (akses penuh termasuk manajemen user, role, dan master data) dan **User** (hanya akses laporan & app metrics)

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
│   │       └── FetchTrxPbiSettlementReport.php
│   ├── Enums/
│   │   └── MetricUnit.php                       # Enum satuan metrik (%, GB, MB/s, ms, dst.)
│   ├── Models/
│   │   ├── AppMetric.php                        # Relasi ke MasterAplikasi & MasterMetrik
│   │   ├── MasterAplikasi.php                   # Soft-delete, nama auto-UPPERCASE
│   │   ├── MasterMetrik.php                     # Soft-delete, nama auto-UPPERCASE
│   │   ├── EngineNotifReport.php
│   │   ├── MteleplusReport.php
│   │   ├── TrxPbiLimitReport.php                # Per jam per CCY2, index wic-trx-pbi-ceklimit*
│   │   └── TrxPbiSettlementReport.php           # Per jam per CCY2, index log-wic-trx-pbi*
│   ├── MoonShine/
│   │   ├── Layouts/
│   │   │   └── MoonShineLayout.php              # Layout & menu (canSee per role)
│   │   ├── Pages/
│   │   │   └── Dashboard.php
│   │   └── Resources/
│   │       ├── AppMetric/
│   │       │   ├── Pages/
│   │       │   │   ├── AppMetricIndexPage.php   # Table + grafik + filter FK
│   │       │   │   └── AppMetricFormPage.php    # Form + reactive satuan
│   │       │   └── AppMetricResource.php        # Eager load masterAplikasi & masterMetrik
│   │       ├── MasterAplikasi/
│   │       │   ├── Pages/
│   │       │   │   ├── MasterAplikasiIndexPage.php  # QueryTag Aktif/Sampah + restore
│   │       │   │   └── MasterAplikasiFormPage.php
│   │       │   └── MasterAplikasiResource.php
│   │       ├── MasterMetrik/
│   │       │   ├── Pages/
│   │       │   │   ├── MasterMetrikIndexPage.php    # QueryTag Aktif/Sampah + restore
│   │       │   │   └── MasterMetrikFormPage.php     # Select satuan dari MetricUnit enum
│   │       │   └── MasterMetrikResource.php
│   │       ├── EngineNotifReport/
│   │       │   ├── Pages/
│   │       │   │   ├── EngineNotifReportIndexPage.php
│   │       │   │   └── EngineNotifReportFetchPage.php
│   │       │   └── EngineNotifReportResource.php
│   │       ├── MteleplusReport/
│   │       │   ├── Pages/
│   │       │   │   ├── MteleplusReportIndexPage.php
│   │       │   │   └── MteleplusReportFetchPage.php
│   │       │   └── MteleplusReportResource.php
│   │       ├── TrxPbiLimitReport/
│   │       │   ├── Pages/
│   │       │   │   ├── TrxPbiLimitReportIndexPage.php   # Table + 7 chart per jam per CCY2
│   │       │   │   └── TrxPbiLimitReportFetchPage.php   # Fetch manual, maks 90 hari
│   │       │   └── TrxPbiLimitReportResource.php
│   │       ├── TrxPbiSettlementReport/
│   │       │   ├── Pages/
│   │       │   │   ├── TrxPbiSettlementReportIndexPage.php
│   │       │   │   └── TrxPbiSettlementReportFetchPage.php
│   │       │   └── TrxPbiSettlementReportResource.php
│   │       ├── MoonShineUser/
│   │       │   ├── Pages/
│   │       │   │   ├── MoonShineUserFormPage.php
│   │       │   │   └── MoonShineUserIndexPage.php
│   │       │   └── MoonShineUserResource.php
│   │       └── MoonShineUserRole/
│   │           ├── Pages/
│   │           │   ├── MoonShineUserRoleFormPage.php
│   │           │   └── MoonShineUserRoleIndexPage.php
│   │           └── MoonShineUserRoleResource.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── MoonShineServiceProvider.php         # authorizationRules per resource
│   └── Services/
│       ├── ElasticsearchService.php             # query & parse per index (Engine Notif, Mteleplus, TrxPBI Limit, TrxPBI Settlement)
│       ├── EngineNotifReportService.php
│       ├── MteleplusReportService.php
│       ├── TrxPbiLimitReportService.php
│       └── TrxPbiSettlementReportService.php
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
│       ├── 2026_06_09_000002_update_app_metrics_recorded_at_microseconds.php
│       ├── 2026_06_10_000001_add_role_and_avatar_to_users_table.php
│       ├── 2026_06_12_000001_create_master_tables.php
│       ├── 2026_06_12_145700_add_master_relations_to_app_metrics_table.php
│       ├── 2026_06_12_150800_drop_string_columns_from_app_metrics_table.php
│       ├── 2026_07_01_000001_create_trx_pbi_limit_reports_table.php
│       ├── 2026_07_01_000002_recreate_trx_pbi_limit_reports_hourly.php   # per-jam + unique(report_hour, ccy2)
│       └── 2026_07_01_000003_create_trx_pbi_settlement_reports_table.php
└── routes/
    ├── web.php                                  # Redirect / → /admin
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

### 5. Buat Admin Panel

```bash
php artisan moonshine:user
```

Perintah ini membuat akun pertama dengan role **Admin** untuk login ke panel. Jalankan sekali saat fresh install.

### 6. Jalankan Server

```bash
php artisan serve
```

Akses admin panel di: `http://127.0.0.1:8000/admin`

> Root URL `/` otomatis redirect ke `/admin`, sehingga akses via IP langsung (mis. `https://192.168.1.50`) diarahkan ke halaman login panel.

### Akses via LAN (HTTPS)

Untuk mengakses dari perangkat lain dalam satu jaringan menggunakan Laragon:

1. **Enable SSL** — Laragon tray → Menu → Apache → SSL → Enable SSL
2. **Edit VHost** — tambah `<Directory>` block dan IP LAN sebagai `ServerAlias` di `C:\laragon\etc\apache2\sites-enabled\auto.monitoring-laravel.test.conf`
3. **Buka Firewall** — izinkan port 443 inbound di Windows Defender Firewall
4. **Update `.env`** — set `APP_URL=https://[domain-atau-IP]`
5. **Akses dari perangkat lain** — `https://[IP-host]/admin`, klik **Advanced → Proceed** untuk melewati peringatan self-signed certificate

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

Schedule::command('report:fetch-trx-pbi-limit')
    ->dailyAt('00:09')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/trx-pbi-limit-fetch.log'));

Schedule::command('report:fetch-trx-pbi-settlement')
    ->dailyAt('00:11')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/trx-pbi-settlement-fetch.log'));
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

# Fetch TrxPBI Limit kemarin dari Elasticsearch
php artisan report:fetch-trx-pbi-limit

# Fetch TrxPBI Settlement kemarin dari Elasticsearch
php artisan report:fetch-trx-pbi-settlement

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
     │       ├── 00:07 → report:fetch-mteleplus
     │       ├── 00:09 → report:fetch-trx-pbi-limit
     │       └── 00:11 → report:fetch-trx-pbi-settlement
     │
     └── Manual: dari panel (form fetch per rentang tanggal, maks 90 hari)
               │
               ▼
     Service::fetchAndStore(Carbon $date)
               │
               ├── ElasticsearchService::query...()     ← agregasi per jam / per hari
               └── Model::updateOrCreate()              ← upsert unique key
                         │
                         ▼
               Database MySQL
                         │
                         ▼
               MoonShine Panel
                    ├── Table (filter, sort, pagination, export Excel)
                    └── Chart (Fragment async + withQueryParams)
                              ├── ValueMetric  (Total Trx, Total NominalEqUSD, Total Nominal)
                              ├── LineChartMetric (per jam per CCY2)
                              └── DonutChartMetric (distribusi per CCY2)
```

---

## Struktur Tabel Database

### `master_aplikasi`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `nama` | varchar | Nama aplikasi — unique, auto-UPPERCASE |
| `keterangan` | varchar | Keterangan opsional |
| `deleted_at` | timestamp | Soft-delete |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

### `master_metrik`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `nama` | varchar | Nama metrik — unique, auto-UPPERCASE |
| `satuan_default` | varchar | Satuan default (%, GB, MB/s, ms, dst.) |
| `keterangan` | varchar | Keterangan opsional |
| `deleted_at` | timestamp | Soft-delete |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

**Seed awal (9 metrik):**

| Nama | Satuan |
|---|---|
| CPU | % |
| MEMORY | % |
| DISK | % |
| NETWORK_IN | MB/s |
| NETWORK_OUT | MB/s |
| LOAD_1M | - |
| LOAD_5M | - |
| LOAD_15M | - |
| RESPONSE_TIME | ms |

### `app_metrics`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `recorded_at` | timestamp(6) | Waktu pencatatan — microsecond precision, auto-unique |
| `master_aplikasi_id` | bigint | FK → `master_aplikasi.id` |
| `master_metrik_id` | bigint | FK → `master_metrik.id` |
| `value` | varchar | Nilai metrik (mis. `75`, `2.4`) |
| `satuan` | varchar | Satuan metrik — auto-terisi dari master saat input |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

> **Catatan:** Second dan microsecond pada `recorded_at` diisi otomatis saat menyimpan — user hanya memilih tanggal, jam, dan menit.

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

**Accessor:** `mvrk_total`, `sms_total`, `email_total`, `total_success`, `total_fail`

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

**Accessor:** `akt_total`, `rpin_total`, `total_success`, `total_fail`

### `trx_pbi_limit_reports`

> Data diambil dari index Elasticsearch **`wic-trx-pbi-ceklimit*`**, field waktu: `RequestTime` (UTC → WIB +07:00).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `report_hour` | datetime | Jam transaksi (WIB, dibulatkan ke awal jam) |
| `ccy2` | varchar | Kode mata uang tujuan (contoh: `USD`, `SGD`) |
| `total_trx` | integer | Jumlah transaksi dalam jam tersebut |
| `total_nominal` | float | Total nominal transaksi |
| `total_nominal_eq_usd` | float | Total nominal setara USD |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

**Unique key:** `(report_hour, ccy2)` — upsert otomatis saat fetch ulang

### `trx_pbi_settlement_reports`

> Data diambil dari index Elasticsearch **`log-wic-trx-pbi*`**, field waktu: `DateTime` (UTC → WIB +07:00).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `report_hour` | datetime | Jam transaksi (WIB, dibulatkan ke awal jam) |
| `ccy2` | varchar | Kode mata uang tujuan (contoh: `USD`, `SGD`) |
| `total_trx` | integer | Jumlah transaksi dalam jam tersebut |
| `total_nominal` | float | Total nominal transaksi |
| `total_nominal_eq_usd` | float | Total nominal setara USD |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

**Unique key:** `(report_hour, ccy2)` — upsert otomatis saat fetch ulang

---

## Role Panel

| Role | Menu yang Terlihat |
|---|---|
| **Admin** | Manajemen (Users, Roles) + App Metric (Data Metrik, Master Aplikasi, Master Metrik) + Elastic |
| **User** | App Metric (Data Metrik saja) + Elastic |

- Admin dibuat via `php artisan moonshine:user`
- User tambahan dibuat dari **Manajemen → Admins** di panel
- Akses ke resource Master Aplikasi/Metrik dan Manajemen User/Role diblokir secara server-side untuk role User

---

## Halaman Admin Panel

### Menu: Manajemen (khusus Admin)

**Admins** — CRUD panel users (nama, email, password, role)

**User Roles** — CRUD definisi role; default: Admin (id=1), User (id=2)

### Menu: App Metric

**Data Metrik** (`/admin/resource/app-metric-resource`)

- Tabel data metrik dengan kolom Timestamp, Aplikasi, Metrik, Value, Satuan
- Filter DateRange (default 7 hari terakhir) + filter dropdown Aplikasi & Metrik dari master
- Dropdown per page + column selection
- **Form tambah:** pilih aplikasi & metrik dari dropdown master; satuan auto-terisi saat metrik dipilih (reactive), bisa diubah manual
- **Grafik:** satu LineChart per jenis metrik, satu series per aplikasi

**Master Aplikasi** (khusus Admin) — CRUD daftar nama aplikasi; soft-delete dengan tab Sampah & tombol Pulihkan

**Master Metrik** (khusus Admin) — CRUD jenis metrik + satuan default; soft-delete dengan tab Sampah & tombol Pulihkan

### Menu: Elastic

**Engine Notif Reports** — tabel harian, chart, fetch manual, export Excel

**Mteleplus Reports** — tabel harian, chart, fetch manual, export Excel

**TrxPBI Limit** — tabel per jam per CCY2, 7 chart interaktif (3 ValueMetric + 2 LineChart + 2 DonutChart), fetch manual, export Excel

**TrxPBI Settlement** — tabel per jam per CCY2, 7 chart interaktif (3 ValueMetric + 2 LineChart + 2 DonutChart), fetch manual, export Excel

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
