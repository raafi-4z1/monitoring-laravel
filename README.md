# Monitoring Laravel

Admin panel monitoring berbasis **Laravel 12** + **MoonShine v4** yang mengintegrasikan data dari **Elasticsearch** ke database MySQL, dilengkapi dengan dashboard, laporan per jam, scheduler otomatis, chart interaktif, dan export Excel.

---

## Fitur

- **Engine Notif Report** — laporan per jam Engine Notif dari Elasticsearch
- **mTeleplus Report** — laporan per jam mTeleplus dari Elasticsearch
- **TrxPBI Limit Report** — laporan per jam transaksi WIC PBI Cek Limit (index `wic-trx-pbi-ceklimit*`), dikelompokkan per mata uang
- **TrxPBI Settlement Report** — laporan per jam transaksi WIC PBI Settlement (index `log-wic-trx-pbi*`), dikelompokkan per mata uang
- **App Metrics** — input manual metrik server (CPU, Memory, Disk, dll.) dengan grafik per jenis metrik
- **Master Aplikasi** — manajemen daftar nama aplikasi (CRUD + soft-delete, khusus Admin)
- **Master Metrik** — manajemen daftar jenis metrik beserta satuan default (CRUD + soft-delete, khusus Admin)
- **Report Sources** — konfigurasi metadata sumber data per layanan (app_id, data_source, data_source_name, service_integrator), khusus Admin
- **Chart Interaktif** — LineChart & DonutChart via ApexCharts, dikelompokkan per mata uang / per jenis metrik, ikut filter DateRange
- **Reactive Form** — saat memilih metrik, kolom satuan otomatis terisi dari `satuan_default` master metrik
- **Scheduler Otomatis** — fetch data dari Elasticsearch setiap hari otomatis
- **Fetch Manual** — ambil data rentang tanggal tertentu langsung dari admin panel (maks 90 hari)
- **Filter Tanggal** — filter data berdasarkan rentang tanggal dengan `DateRange`
- **Pagination & Sort** — navigasi data dengan dropdown per page dan pengurutan kolom
- **Export Excel & CSV** — export data ke file `.xlsx` atau `.csv` dengan format kolom lengkap termasuk metadata report_sources
- **Auto Export CSV TrxPBI** — setelah fetch harian selesai, data TrxPBI Limit & Settlement kemarin diekspor otomatis ke satu file CSV di `storage/app/exports/`
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
│   │       ├── FetchTrxPbiSettlementReport.php
│   │       └── ExportTrxPbiCsv.php              # Export gabungan TrxPBI Limit+Settlement ke CSV
│   ├── Enums/
│   │   └── MetricUnit.php                       # Enum satuan metrik (%, GB, MB/s, ms, dst.)
│   ├── Models/
│   │   ├── AppMetric.php                        # Relasi ke MasterAplikasi & MasterMetrik
│   │   ├── MasterAplikasi.php                   # Soft-delete, nama auto-UPPERCASE
│   │   ├── MasterMetrik.php                     # Soft-delete, nama auto-UPPERCASE
│   │   ├── ReportSource.php                     # Metadata sumber data per layanan
│   │   ├── EngineNotifReport.php
│   │   ├── MteleplusReport.php
│   │   ├── TrxPbiLimitReport.php                # Per jam per mata uang, FK → report_sources
│   │   └── TrxPbiSettlementReport.php           # Per jam per mata uang, FK → report_sources
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
│   │       │   └── AppMetricResource.php
│   │       ├── MasterAplikasi/
│   │       │   ├── Pages/
│   │       │   │   ├── MasterAplikasiIndexPage.php
│   │       │   │   └── MasterAplikasiFormPage.php
│   │       │   └── MasterAplikasiResource.php
│   │       ├── MasterMetrik/
│   │       │   ├── Pages/
│   │       │   │   ├── MasterMetrikIndexPage.php
│   │       │   │   └── MasterMetrikFormPage.php
│   │       │   └── MasterMetrikResource.php
│   │       ├── ReportSource/
│   │       │   ├── Pages/
│   │       │   │   ├── ReportSourceIndexPage.php
│   │       │   │   └── ReportSourceFormPage.php
│   │       │   └── ReportSourceResource.php
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
│   │       │   │   ├── TrxPbiLimitReportIndexPage.php
│   │       │   │   └── TrxPbiLimitReportFetchPage.php
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
│       ├── ElasticsearchService.php             # query & parse per index
│       ├── EngineNotifReportService.php
│       ├── MteleplusReportService.php
│       ├── TrxPbiLimitReportService.php
│       └── TrxPbiSettlementReportService.php
├── config/
│   └── elasticsearch.php
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2020_10_04_115514_create_moonshine_roles_table.php
│   │   ├── 2020_10_05_173148_create_moonshine_tables.php
│   │   ├── 2026_05_22_014556_create_notifications_table.php
│   │   ├── 2026_05_26_033044_create_engine_notif_reports_table.php
│   │   ├── 2026_06_04_140613_create_mteleplus_reports_table.php
│   │   ├── 2026_06_09_000001_create_app_metrics_table.php
│   │   ├── 2026_06_10_000001_add_role_and_avatar_to_users_table.php
│   │   ├── 2026_06_12_000001_create_master_tables.php
│   │   ├── 2026_07_03_000004_create_report_sources_table.php
│   │   └── 2026_07_03_000006_create_trx_pbi_reports_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── MasterMetrikSeeder.php               # 9 metrik default (CPU, MEMORY, DISK, dst.)
│       └── ReportSourceSeeder.php               # Metadata sumber data TrxPBI Limit & Settlement
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

### 4. Migrasi & Seed Database

```bash
php artisan migrate --seed
```

Perintah `--seed` akan mengisi data awal:
- **9 metrik default** (`MasterMetrikSeeder`): CPU, MEMORY, DISK, NETWORK_IN, NETWORK_OUT, LOAD_1M, LOAD_5M, LOAD_15M, RESPONSE_TIME
- **2 report sources** (`ReportSourceSeeder`): metadata TrxPBI Limit & Settlement (app_id, data_source, data_source_name, service_integrator)

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

// Export CSV otomatis dipicu setelah fetch settlement (command terakhir) selesai
Schedule::command('report:fetch-trx-pbi-settlement')
    ->dailyAt('00:11')
    ->withoutOverlapping()
    ->then(fn () => Artisan::call('report:export-trx-pbi-csv'))
    ->appendOutputTo(storage_path('logs/trx-pbi-settlement-fetch.log'));
```

Alur harian otomatis:

| Waktu | Aksi |
|---|---|
| 00:05 | Fetch Engine Notif dari Elasticsearch |
| 00:07 | Fetch mTeleplus dari Elasticsearch |
| 00:09 | Fetch TrxPBI Limit dari Elasticsearch |
| 00:11 | Fetch TrxPBI Settlement dari Elasticsearch |
| ~00:11+ | **Auto export** TrxPBI (Limit + Settlement) kemarin ke CSV |

File CSV disimpan di: `storage/app/exports/YYYY-MM-DD/trx_pbi_YYYYMMDD.csv`

### Menjalankan Scheduler

**Development — Terminal (polling tiap menit, biarkan berjalan):**

```bash
php artisan schedule:work
```

**Windows — Windows Task Scheduler:**

Web server (Apache/Nginx Laragon) **tidak perlu aktif** — scheduler berjalan via PHP CLI. Yang harus jalan hanyalah **MySQL**.

```
Program  : C:\laragon\bin\php\php-8.2\php.exe
Arguments: artisan schedule:run
Start in : C:\path\to\monitoring-laravel
Trigger  : Daily, 00:00
Repeat   : Every 1 minute, for a duration of 30 minutes
```

> Durasi 30 menit (00:00–00:30) sudah mencakup semua jadwal yang berakhir sekitar 00:11. Setelah itu task scheduler berhenti otomatis hingga tengah malam berikutnya.

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

# Export TrxPBI Limit + Settlement kemarin ke satu file CSV
php artisan report:export-trx-pbi-csv

# Export TrxPBI untuk tanggal tertentu
php artisan report:export-trx-pbi-csv --date=2026-07-05

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
               ├── ElasticsearchService::query...()     ← agregasi per jam per mata uang
               └── Model::updateOrCreate()              ← upsert unique key (trx_date, trx_hour, trx_currency)
                         │
                         ▼
               Database MySQL
                         │
                         ├── MoonShine Panel
                         │        ├── Table (filter, sort, pagination, export Excel/CSV)
                         │        └── Chart (Fragment async + withQueryParams)
                         │
                         └── Auto Export CSV (setelah fetch settlement selesai)
                                  └── storage/app/exports/YYYY-MM-DD/trx_pbi_YYYYMMDD.csv
                              ├── ValueMetric  (Total Trx, Total Nominal)
                              ├── LineChart    (per jam per mata uang)
                              └── DonutChart   (distribusi per mata uang)
```

---

## Struktur Tabel Database

### `report_sources`

> Metadata sumber data per layanan — digunakan untuk kolom export Excel TrxPBI.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `service_name` | varchar(50) | Identifier layanan — unique (mis. `trx_pbi_limit`) |
| `app_id` | varchar(50) | ID aplikasi (mis. `AFOAFO0252`) |
| `data_source` | varchar(50) | Jenis sumber data (`ELK`, `Dynatrace`, `DBMS`) |
| `data_source_name` | varchar(100) | Nama index/sumber (mis. `wic-trx-pbi-ceklimit*`) |
| `service_integrator` | varchar(50) | Nama integrator (mis. `WIC`) |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

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

### `app_metrics`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `recorded_at` | timestamp(6) | Waktu pencatatan — microsecond precision, auto-fill detik |
| `value` | varchar | Nilai metrik (mis. `75`, `2.4`) |
| `satuan` | varchar | Satuan metrik |
| `master_aplikasi_id` | bigint | FK → `master_aplikasi.id` |
| `master_metrik_id` | bigint | FK → `master_metrik.id` |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

### `engine_notif_reports`

> Data diambil dari Elasticsearch, disimpan per jam.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `report_hour` | datetime | Jam laporan (WIB, dibulatkan ke awal jam) — unique |
| `mvrk_success` | bigint | MVRK berhasil |
| `mvrk_fail` | bigint | MVRK gagal |
| `sms_success` | bigint | SMS berhasil |
| `sms_fail` | bigint | SMS gagal |
| `email_success` | bigint | Email berhasil |
| `email_fail` | bigint | Email gagal |
| `avg_response_time` | decimal(10,2) | Rata-rata response time |
| `avg_lifespan` | decimal(10,2) | Rata-rata lifespan |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

### `mteleplus_reports`

> Data diambil dari Elasticsearch, disimpan per jam.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `report_hour` | datetime | Jam laporan (WIB, dibulatkan ke awal jam) — unique |
| `akt_success` | bigint | AKT berhasil |
| `akt_fail` | bigint | AKT gagal |
| `rpin_success` | bigint | RPIN berhasil |
| `rpin_fail` | bigint | RPIN gagal |
| `total_incoming` | bigint | Total incoming |
| `total_outgoing` | bigint | Total outgoing |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

### `trx_pbi_limit_reports`

> Data diambil dari index Elasticsearch **`wic-trx-pbi-ceklimit*`**, field waktu: `RequestTime` (UTC → WIB +07:00).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `report_source_id` | bigint | FK → `report_sources.id` (nullable) |
| `trx_date` | date | Tanggal transaksi |
| `trx_hour` | tinyint unsigned | Jam transaksi (0–23) |
| `trx_currency` | varchar(10) | Kode mata uang (mis. `USD`, `SGD`) |
| `trx_count` | bigint | Jumlah transaksi |
| `success_count` | bigint | Jumlah transaksi sukses |
| `trx_amount` | decimal(20,2) | Total nominal transaksi |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

**Unique key:** `(trx_date, trx_hour, trx_currency)`

**Export kolom (Excel & CSV):** `app_id, data_source, data_source_name, trx_date, trx_hour, service_name, service_integrator, trx_currency, trx_amount, trx_count, success_count`

### `trx_pbi_settlement_reports`

> Data diambil dari index Elasticsearch **`log-wic-trx-pbi*`**, field waktu: `DateTime` (UTC → WIB +07:00).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `report_source_id` | bigint | FK → `report_sources.id` (nullable) |
| `trx_date` | date | Tanggal transaksi |
| `trx_hour` | tinyint unsigned | Jam transaksi (0–23) |
| `trx_currency` | varchar(10) | Kode mata uang (mis. `USD`, `SGD`) |
| `trx_count` | bigint | Jumlah transaksi |
| `success_count` | bigint | Jumlah transaksi sukses |
| `trx_amount` | decimal(20,2) | Total nominal transaksi |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

**Unique key:** `(trx_date, trx_hour, trx_currency)`

**Export kolom (Excel & CSV):** `app_id, data_source, data_source_name, trx_date, trx_hour, service_name, service_integrator, trx_currency, trx_amount, trx_count, success_count`

---

## Role Panel

| Role | Menu yang Terlihat |
|---|---|
| **Admin** | Manajemen (Users, Roles) + App Metric (Data Metrik, Master Aplikasi, Master Metrik, Report Sources) + Elastic |
| **User** | App Metric (Data Metrik saja) + Elastic |

- Admin dibuat via `php artisan moonshine:user`
- User tambahan dibuat dari **Manajemen → Admins** di panel
- Akses ke resource Master Aplikasi/Metrik, Report Sources, dan Manajemen User/Role diblokir secara server-side untuk role User

---

## Halaman Admin Panel

### Menu: Manajemen (khusus Admin)

**Admins** — CRUD panel users (nama, email, password, role)

**User Roles** — CRUD definisi role; default: Admin (id=1), User (id=2)

### Menu: App Metric

**Data Metrik** — tabel metrik dengan filter DateRange, dropdown Aplikasi & Metrik, grafik LineChart per jenis metrik

**Master Aplikasi** (khusus Admin) — CRUD daftar nama aplikasi; soft-delete dengan tab Sampah & tombol Pulihkan

**Master Metrik** (khusus Admin) — CRUD jenis metrik + satuan default; soft-delete dengan tab Sampah & tombol Pulihkan

**Report Sources** (khusus Admin) — CRUD metadata sumber data per layanan; digunakan untuk mengisi kolom export Excel TrxPBI

### Menu: Elastic

**Engine Notif Reports** — tabel per jam, chart, fetch manual, export Excel & CSV

**Mteleplus Reports** — tabel per jam, chart, fetch manual, export Excel & CSV

**TrxPBI Limit** — tabel per jam per mata uang, chart interaktif (ValueMetric + LineChart + DonutChart), fetch manual, export Excel & CSV dengan kolom report_sources

**TrxPBI Settlement** — tabel per jam per mata uang, chart interaktif (ValueMetric + LineChart + DonutChart), fetch manual, export Excel & CSV dengan kolom report_sources

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
