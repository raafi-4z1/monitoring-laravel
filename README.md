# Monitoring Laravel

Admin panel monitoring berbasis **Laravel 12** + **MoonShine v4** yang mengintegrasikan data dari **Elasticsearch** ke database MySQL, dilengkapi dengan dashboard, laporan per jam, scheduler otomatis, chart interaktif, dan export Excel/CSV.

---

## Fitur

- **Engine Notif Report** — laporan per jam Engine Notif dari Elasticsearch
- **mTeleplus Report** — laporan per jam mTeleplus dari Elasticsearch
- **TrxPBI Limit Report** — laporan per jam transaksi WIC PBI Cek Limit (index `wic-trx-pbi-ceklimit*`), dikelompokkan per mata uang
- **TrxPBI Settlement Report** — laporan per jam transaksi WIC PBI Settlement (index `log-wic-trx-pbi*`), dikelompokkan per mata uang
- **WIC DB Metric** — laporan per jam metrik server WIC DB (`192.168.63.30` / WICADBDC): CPU, Memory, Disk dari index `xmb-ls*`
- **WIC APP Metric** — laporan per jam metrik server WIC APP (`192.168.7.37` / HQWIC): CPU, Memory, Disk dari index `xmb-ls*`
- **App Metrics** — input manual metrik server (CPU, Memory, Disk, dll.) dengan grafik per jenis metrik
- **Master Aplikasi** — manajemen daftar nama aplikasi (CRUD + soft-delete, khusus Admin)
- **Master Metrik** — manajemen daftar jenis metrik beserta satuan default (CRUD + soft-delete, khusus Admin)
- **Report Sources** — konfigurasi metadata sumber data per layanan (app_id, data_source, data_source_name, service_integrator), khusus Admin
- **Chart Interaktif** — LineChart & DonutChart via ApexCharts, dikelompokkan per mata uang / per jenis metrik, ikut filter DateRange & filter tipe metrik
- **Reactive Form** — saat memilih metrik, kolom satuan otomatis terisi dari `satuan_default` master metrik
- **Scheduler Otomatis** — fetch data dari Elasticsearch setiap hari otomatis
- **Fetch Manual** — ambil data rentang tanggal tertentu langsung dari admin panel (maks 90 hari)
- **Filter Tanggal** — filter data berdasarkan rentang tanggal dengan `DateRange`
- **Pagination & Sort** — navigasi data dengan dropdown per page dan pengurutan kolom
- **Export Excel & CSV** — export data ke file `.xlsx` atau `.csv` dengan format kolom lengkap termasuk metadata report_sources
- **Auto Export CSV TrxPBI** — setelah fetch harian selesai, data TrxPBI Limit & Settlement kemarin diekspor otomatis ke satu file CSV
- **Auto Export CSV WIC Metric** — setelah fetch WIC APP selesai, data WIC DB + WIC APP kemarin diekspor otomatis ke satu file CSV
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
│   │       ├── ExportTrxPbiCsv.php              # Export gabungan TrxPBI Limit+Settlement ke CSV
│   │       ├── FetchWicMetricReport.php          # Fetch WIC DB Metric (WICADBDC)
│   │       ├── FetchWicAppMetricReport.php       # Fetch WIC APP Metric (HQWIC)
│   │       └── ExportWicMetricCsv.php            # Export gabungan WIC DB+APP ke CSV
│   ├── Enums/
│   │   └── MetricUnit.php
│   ├── Models/
│   │   ├── AppMetric.php
│   │   ├── MasterAplikasi.php
│   │   ├── MasterMetrik.php
│   │   ├── ReportSource.php
│   │   ├── EngineNotifReport.php
│   │   ├── MteleplusReport.php
│   │   ├── TrxPbiLimitReport.php
│   │   ├── TrxPbiSettlementReport.php
│   │   ├── WicDbMetricReport.php                # Metrik WIC DB per jam per tipe (cpu/memory/disk)
│   │   └── WicAppMetricReport.php               # Metrik WIC APP per jam per tipe (cpu/memory/disk)
│   ├── MoonShine/
│   │   ├── Layouts/
│   │   │   └── MoonShineLayout.php
│   │   ├── Pages/
│   │   │   └── Dashboard.php
│   │   └── Resources/
│   │       ├── AppMetric/
│   │       ├── MasterAplikasi/
│   │       ├── MasterMetrik/
│   │       ├── ReportSource/
│   │       ├── EngineNotifReport/
│   │       ├── MteleplusReport/
│   │       ├── TrxPbiLimitReport/
│   │       ├── TrxPbiSettlementReport/
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
│   │   └── MoonShineServiceProvider.php
│   └── Services/
│       ├── ElasticsearchService.php             # query & parse per index (termasuk WIC Metric)
│       ├── EngineNotifReportService.php
│       ├── MteleplusReportService.php
│       ├── TrxPbiLimitReportService.php
│       ├── TrxPbiSettlementReportService.php
│       ├── WicDbMetricReportService.php         # HOST_IP=192.168.63.30, HOST_NAME=WICADBDC
│       └── WicAppMetricReportService.php        # HOST_IP=192.168.7.37, HOST_NAME=HQWIC
├── config/
│   └── elasticsearch.php
├── database/
│   ├── migrations/
│   │   ├── ...
│   │   ├── 2026_07_07_000001_create_wic_db_metric_reports_table.php
│   │   ├── 2026_07_07_000002_create_wic_app_metric_reports_table.php
│   │   └── 2026_07_07_000003_refactor_wic_metric_reports_datetime.php  # report_hour → trx_date + trx_hour
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── MasterMetrikSeeder.php
│       └── ReportSourceSeeder.php               # +2 entry: wic_db_dc (id=3), wic_app_dc (id=4)
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

# Folder tujuan export CSV TrxPBI (opsional, default: storage/app/exports)
TRX_PBI_EXPORT_PATH=

# Folder tujuan export CSV WIC Metric DB+APP (opsional, default: storage/app/exports)
WIC_METRIC_EXPORT_PATH=
```

> **Catatan path Windows:** Gunakan forward slash `/` atau double backslash `\\`. Backslash tunggal `\` akan menyebabkan error parsing `.env`.

### 4. Migrasi & Seed Database

```bash
php artisan migrate --seed
```

Perintah `--seed` akan mengisi data awal:
- **9 metrik default** (`MasterMetrikSeeder`): CPU, MEMORY, DISK, NETWORK_IN, NETWORK_OUT, LOAD_1M, LOAD_5M, LOAD_15M, RESPONSE_TIME
- **4 report sources** (`ReportSourceSeeder`): TrxPBI Limit (id=1), TrxPBI Settlement (id=2), WIC DB (id=3), WIC APP (id=4)

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

> Root URL `/` otomatis redirect ke `/admin`.

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
Schedule::command('report:fetch-engine-notif')->dailyAt('00:05')->withoutOverlapping();
Schedule::command('report:fetch-mteleplus')->dailyAt('00:07')->withoutOverlapping();
Schedule::command('report:fetch-trx-pbi-limit')->dailyAt('00:09')->withoutOverlapping();

// Auto export CSV TrxPBI setelah fetch settlement selesai
Schedule::command('report:fetch-trx-pbi-settlement')
    ->dailyAt('00:11')->withoutOverlapping()
    ->then(fn () => Artisan::call('report:export-trx-pbi-csv'));

Schedule::command('report:fetch-wic-metric')->dailyAt('00:13')->withoutOverlapping();

// Auto export CSV WIC Metric setelah fetch WIC APP selesai
Schedule::command('report:fetch-wic-app-metric')
    ->dailyAt('00:15')->withoutOverlapping()
    ->then(fn () => Artisan::call('report:export-wic-metric-csv'));
```

Alur harian otomatis:

| Waktu | Aksi |
|---|---|
| 00:05 | Fetch Engine Notif dari Elasticsearch |
| 00:07 | Fetch mTeleplus dari Elasticsearch |
| 00:09 | Fetch TrxPBI Limit dari Elasticsearch |
| 00:11 | Fetch TrxPBI Settlement → **auto export** TrxPBI CSV |
| 00:13 | Fetch WIC DB Metric (WICADBDC) dari Elasticsearch |
| 00:15 | Fetch WIC APP Metric (HQWIC) → **auto export** WIC Metric CSV |

File CSV disimpan di:
- TrxPBI: `{TRX_PBI_EXPORT_PATH}/YYYY/MM/DD/YYYYMMDD_{kode_prefix}_{app_id}_{service_integrator}.csv`
- WIC Metric: `{WIC_METRIC_EXPORT_PATH}/YYYY/MM/DD/YYYYMMDD_{kode_prefix}_{app_id}_WIC.csv`

Nilai `kode_prefix` diambil dari kolom `kode_prefix` tabel `report_sources` (default: `BP` untuk TrxPBI, `SPI` untuk WIC Metric).

### Menjalankan Scheduler

**Development — Terminal (polling tiap menit):**

```bash
php artisan schedule:work
```

**Windows — Windows Task Scheduler:**

```
Program  : C:\laragon\bin\php\php-8.2\php.exe
Arguments: artisan schedule:run
Start in : C:\path\to\monitoring-laravel
Trigger  : Daily, 00:00
Repeat   : Every 1 minute, for a duration of 30 minutes
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
php artisan report:fetch-mteleplus
php artisan report:fetch-trx-pbi-limit
php artisan report:fetch-trx-pbi-settlement
php artisan report:fetch-wic-metric          # WIC DB (WICADBDC)
php artisan report:fetch-wic-app-metric      # WIC APP (HQWIC)

# Export CSV
php artisan report:export-trx-pbi-csv                      # TrxPBI kemarin
php artisan report:export-trx-pbi-csv --date=2026-07-05    # TrxPBI tanggal tertentu
php artisan report:export-wic-metric-csv                   # WIC Metric kemarin
php artisan report:export-wic-metric-csv --date=2026-07-05 # WIC Metric tanggal tertentu

# Utilitas
php artisan schedule:run
php artisan schedule:list
php artisan optimize:clear
```

---

## Alur Data

```
Elasticsearch (index: xmb-ls*, wic-trx-pbi-ceklimit*, log-wic-trx-pbi*, ...)
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
                         └── Auto Export CSV
                                  ├── TrxPBI → {TRX_PBI_EXPORT_PATH}/YYYY/MM/DD/...csv
                                  └── WIC Metric → {WIC_METRIC_EXPORT_PATH}/YYYY/MM/DD/...csv
```

---

## Struktur Tabel Database

### `report_sources`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | Primary key |
| `service_name` | varchar(50) | Identifier layanan — unique |
| `app_id` | varchar(50) | ID aplikasi |
| `data_source` | varchar(50) | Jenis sumber data (`ELK`, dll.) |
| `data_source_name` | varchar(100) | Nama index/sumber |
| `service_integrator` | varchar(50) | Nama integrator |

### `trx_pbi_limit_reports` / `trx_pbi_settlement_reports`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `report_source_id` | bigint | FK → `report_sources.id` |
| `trx_date` | date | Tanggal transaksi |
| `trx_hour` | tinyint unsigned | Jam (0–23) |
| `trx_currency` | varchar(10) | Kode mata uang |
| `trx_count` | bigint | Jumlah transaksi |
| `success_count` | bigint | Jumlah sukses |
| `trx_amount` | decimal(20,2) | Total nominal |

**Unique key:** `(trx_date, trx_hour, trx_currency)`

### `wic_db_metric_reports` / `wic_app_metric_reports`

> WIC DB: host `192.168.63.30` (WICADBDC, `report_source_id=3`)  
> WIC APP: host `192.168.7.37` (HQWIC, `report_source_id=4`)  
> Sumber: Elasticsearch index `xmb-ls*`, metricset `system.cpu`, `system.memory`, `system.filesystem`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `report_source_id` | bigint | FK → `report_sources.id` |
| `trx_date` | date | Tanggal laporan |
| `trx_hour` | tinyint unsigned | Jam (0–23) |
| `metric_type` | varchar(20) | `cpu` / `memory` / `disk` |
| `disk_path` | varchar(100) | Nama drive (mis. `C`, `D`) — kosong untuk cpu/memory |
| `max_pct` | decimal(8,4) | Nilai maksimum dalam jam (0–1) — cpu/memory |
| `min_pct` | decimal(8,4) | Nilai minimum dalam jam (0–1) — cpu/memory |
| `avg_pct` | decimal(8,4) | Nilai rata-rata dalam jam (0–1) — cpu/memory |
| `last_pct` | decimal(8,4) | Nilai terakhir dalam jam (0–1) — disk |
| `last_used_bytes` | bigint | Bytes terpakai — disk |
| `last_total_bytes` | bigint | Total kapasitas bytes — disk |

**Unique key:** `(trx_date, trx_hour, metric_type, disk_path)`

**Export kolom:** `app_id, data_source, data_source_name, trx_date, trx_hour, hostname, role_type, utilization_avg_pct, utilization_min_pct, utilization_max_pct`

### `engine_notif_reports` / `mteleplus_reports`

> Disimpan per jam, unique key: `report_hour` (datetime).

### `master_aplikasi` / `master_metrik`

> CRUD dengan soft-delete. Nama auto-UPPERCASE.

### `app_metrics`

> Input manual. FK ke `master_aplikasi` dan `master_metrik`.

---

## Role Panel

| Role | Menu yang Terlihat |
|---|---|
| **Admin** | Manajemen (Users, Roles) + App Metric + Elastic + WIC Metric |
| **User** | App Metric (Data Metrik saja) + Elastic + WIC Metric |

---

## Halaman Admin Panel

### Menu: Elastic

**Engine Notif Reports** — tabel per jam, chart, fetch manual, export Excel & CSV

**Mteleplus Reports** — tabel per jam, chart, fetch manual, export Excel & CSV

**TrxPBI Limit** — tabel per jam per mata uang, chart (ValueMetric + LineChart + DonutChart), fetch manual, export

**TrxPBI Settlement** — tabel per jam per mata uang, chart (ValueMetric + LineChart + DonutChart), fetch manual, export

### Menu: WIC Metric

**WIC DB (WICADBDC)** — metrik server WIC DB per jam; chart CPU (Max/Avg/Min %), Memory (Max/Avg/Min %), Disk Usage (% semua disk dalam satu chart); filter tipe metrik; export Excel & CSV

**WIC APP (HQWIC)** — identik dengan WIC DB namun data dari host HQWIC

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
