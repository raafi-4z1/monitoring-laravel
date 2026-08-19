# Monitoring Laravel

Admin panel monitoring berbasis **Laravel 12** + **MoonShine v4** yang mengintegrasikan data dari **Elasticsearch** (langsung maupun lewat proxy datasource **Grafana**) ke database MySQL, dilengkapi dengan dashboard, laporan per jam, scheduler otomatis, chart interaktif, dan export Excel/CSV.

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
│   │       ├── FetchSystemOnlineReport.php       # Fetch System Online (response time)
│   │       ├── ExportSystemOnlineCsv.php         # Export System Online ke CSV
│   │       ├── FetchWicDbMetricReport.php        # Fetch WIC DB Metric
│   │       ├── FetchWicAppMetricReport.php       # Fetch WIC APP Metric
│   │       ├── ExportWicMetricCsv.php            # Export gabungan WIC DB+APP ke CSV
│   │       ├── FetchSpacexLdnJobExecutionReport.php  # Fetch Job Execution London (via proxy Grafana)
│   │       ├── FetchSpacexLdnFileArchiveReport.php   # Fetch File Archive London (via proxy Grafana)
│   │       ├── FetchSpacexLdnDcAppMetricReport.php   # Fetch APP Metric DC London (via proxy Grafana)
│   │       ├── FetchSpacexLdnDcDbMetricReport.php    # Fetch DB Metric DC London (via proxy Grafana)
│   │       ├── FetchSpacexNycJobExecutionReport.php  # Fetch Job Execution New York (via proxy Grafana)
│   │       ├── FetchSpacexNycFileArchiveReport.php   # Fetch File Archive New York (via proxy Grafana)
│   │       ├── FetchSpacexNycDcAppMetricReport.php   # Fetch APP Metric DC New York (via proxy Grafana)
│   │       └── FetchSpacexNycDcDbMetricReport.php    # Fetch DB Metric DC New York (via proxy Grafana)
│   ├── Enums/
│   │   ├── MetricUnit.php
│   │   └── SpacexCity.php                        # Daftar kota/server Space-X (Ldn, Nyc, dst.) - dipakai service generik di bawah
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
│   │   ├── WicAppMetricReport.php                # Metrik WIC APP per jam per tipe (cpu/memory/disk)
│   │   ├── SpacexLdnJobExecutionReport.php       # Batch job server London (via proxy Grafana)
│   │   ├── SpacexLdnFileArchiveReport.php        # Hasil pengecekan file archive server London
│   │   ├── SpacexLdnDcAppMetricReport.php        # Metrik server APP DC London (HQREPOLDNDC)
│   │   ├── SpacexLdnDcDbMetricReport.php         # Metrik server DB DC London (REPODBLDNDC)
│   │   ├── SpacexNycJobExecutionReport.php       # Batch job server New York (via proxy Grafana)
│   │   ├── SpacexNycFileArchiveReport.php        # Hasil pengecekan file archive server New York
│   │   ├── SpacexNycDcAppMetricReport.php        # Metrik server APP DC New York (HQREPONYADC)
│   │   └── SpacexNycDcDbMetricReport.php         # Metrik server DB DC New York (REPODBNYADC)
│   ├── MoonShine/
│   │   ├── Auth/
│   │   │   └── ThrottleLoginByIp.php             # Rate limit login per-IP (lapis tambahan di atas bawaan MoonShine)
│   │   ├── Concerns/
│   │   │   ├── GuardsFetchPageAccess.php         # Guard permission untuk halaman Fetch Manual
│   │   │   └── BuildsHourlyOrDailyChart.php      # Helper bangun chart per jam/hari, dipakai berbagai resource
│   │   ├── Handlers/
│   │   │   └── GuardedExportHandler.php          # Guard permission + disk privat untuk export Excel/CSV
│   │   ├── Http/
│   │   │   └── Requests/
│   │   │       └── StrongPasswordProfileFormRequest.php  # Validasi password kuat di halaman profil
│   │   ├── Middleware/
│   │   │   └── GuardResourcePermission.php       # Guard permission global untuk semua route ber-resource
│   │   ├── Layouts/
│   │   │   └── MoonShineLayout.php               # Menu sidebar & palet warna panel
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   ├── ProfilePage.php                   # Halaman profil sendiri (ganti password, email terkunci)
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
│   │       ├── SystemOnlineReport/
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
│   │       ├── SpacexLdnJobExecutionReport/
│   │       │   ├── Pages/
│   │       │   │   ├── SpacexLdnJobExecutionReportIndexPage.php
│   │       │   │   └── SpacexLdnJobExecutionReportFetchPage.php
│   │       │   └── SpacexLdnJobExecutionReportResource.php
│   │       ├── SpacexLdnFileArchiveReport/
│   │       │   ├── Pages/
│   │       │   │   ├── SpacexLdnFileArchiveReportIndexPage.php
│   │       │   │   └── SpacexLdnFileArchiveReportFetchPage.php
│   │       │   └── SpacexLdnFileArchiveReportResource.php
│   │       ├── SpacexLdnDcAppMetricReport/
│   │       │   ├── Pages/
│   │       │   │   ├── SpacexLdnDcAppMetricReportIndexPage.php
│   │       │   │   └── SpacexLdnDcAppMetricReportFetchPage.php
│   │       │   └── SpacexLdnDcAppMetricReportResource.php
│   │       ├── SpacexLdnDcDbMetricReport/
│   │       │   ├── Pages/
│   │       │   │   ├── SpacexLdnDcDbMetricReportIndexPage.php
│   │       │   │   └── SpacexLdnDcDbMetricReportFetchPage.php
│   │       │   └── SpacexLdnDcDbMetricReportResource.php
│   │       ├── SpacexNycJobExecutionReport/
│   │       │   ├── Pages/
│   │       │   │   ├── SpacexNycJobExecutionReportIndexPage.php
│   │       │   │   └── SpacexNycJobExecutionReportFetchPage.php
│   │       │   └── SpacexNycJobExecutionReportResource.php
│   │       ├── SpacexNycFileArchiveReport/
│   │       │   ├── Pages/
│   │       │   │   ├── SpacexNycFileArchiveReportIndexPage.php
│   │       │   │   └── SpacexNycFileArchiveReportFetchPage.php
│   │       │   └── SpacexNycFileArchiveReportResource.php
│   │       ├── SpacexNycDcAppMetricReport/
│   │       │   ├── Pages/
│   │       │   │   ├── SpacexNycDcAppMetricReportIndexPage.php
│   │       │   │   └── SpacexNycDcAppMetricReportFetchPage.php
│   │       │   └── SpacexNycDcAppMetricReportResource.php
│   │       ├── SpacexNycDcDbMetricReport/
│   │       │   ├── Pages/
│   │       │   │   ├── SpacexNycDcDbMetricReportIndexPage.php
│   │       │   │   └── SpacexNycDcDbMetricReportFetchPage.php
│   │       │   └── SpacexNycDcDbMetricReportResource.php
│   │       ├── ActivityLog/
│   │       ├── MoonShineUser/
│   │       └── MoonShineUserRole/
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── MoonShineServiceProvider.php          # Registrasi resource, page, & authorization rules
│   └── Services/
│       ├── ElasticsearchService.php              # Query & parse per index ES langsung (Engine Notif, mTeleplus, WIC, dll.)
│       ├── GrafanaElasticsearchService.php        # Query ES via proxy datasource Grafana (dipakai resource Space-X)
│       ├── EngineNotifReportService.php
│       ├── MteleplusReportService.php
│       ├── TrxPbiLimitReportService.php
│       ├── TrxPbiSettlementReportService.php
│       ├── TrxPbiLoaderReportService.php
│       ├── SystemOnlineReportService.php
│       ├── WicDbMetricReportService.php
│       ├── WicAppMetricReportService.php
│       ├── SpacexJobExecutionReportService.php    # Generik semua kota Space-X (konfig per kota lihat App\Enums\SpacexCity)
│       ├── SpacexFileArchiveReportService.php     # Generik semua kota Space-X
│       ├── SpacexDcAppMetricReportService.php     # Generik semua kota Space-X
│       ├── SpacexDcDbMetricReportService.php      # Generik semua kota Space-X
│       ├── ActivityLogger.php                     # Pencatat Activity Log terpusat
│       ├── LoginIpThrottle.php                    # Rate limit login per-IP
│       ├── CsvFormulaGuard.php                    # Netralkan formula injection di export CSV/Excel
│       └── SafeFilename.php                       # Bersihkan nama file export terjadwal
├── config/
│   ├── elasticsearch.php                         # Koneksi ES langsung
│   ├── grafana.php                               # Koneksi Grafana + mapping UID datasource per key
│   └── exports.php                               # Mapping folder tujuan tiap auto export CSV
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

Untuk resource **Space-X** (Job Execution, File Archive, APP/DB Metric DC), data diambil lewat proxy datasource Grafana, bukan koneksi ES langsung — isi juga:

```env
GRAFANA_HOST=
GRAFANA_PORT=
GRAFANA_USERNAME=
GRAFANA_PASSWORD=
GRAFANA_REPORTINGKCLN_UID=
GRAFANA_METRICBEAT_ELKHUB_UID=
```

Opsional — folder tujuan file export CSV (default: `storage/app/exports` kalau dikosongkan):

```env
TRX_PBI_EXPORT_PATH=
WIC_METRIC_EXPORT_PATH=
TRX_PBI_LOADER_EXPORT_PATH=
SYSTEM_ONLINE_EXPORT_PATH=
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

Seeder bersifat **insert-only** dan aman dijalankan ulang: baris yang sudah ada di database tidak akan ditimpa, hanya entri baru yang ditambahkan. Jadi perubahan yang sudah dilakukan lewat admin panel (mis. satuan di Master Metrik atau metadata di Report Sources) tidak akan kembali ke nilai default. Konsekuensinya, mengubah nilai baris yang sudah ada harus lewat admin panel atau migration — bukan dengan menjalankan ulang seeder.

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

Setiap fetch dan export terjadwal mencatat hasilnya (berhasil / gagal / tidak ada data) ke **Activity Log**, jadi keberhasilan scheduler bisa dipantau langsung dari admin panel tanpa harus membuka file log di server.

Alur harian (ringkas):

| Waktu | Aksi |
|---|---|
| 00:01 | Fetch Engine Notif dari Elasticsearch |
| 00:02 | Fetch mTeleplus dari Elasticsearch |
| 00:03 | Fetch TrxPBI Limit dari Elasticsearch |
| 00:04 | Fetch TrxPBI Settlement → **auto export** TrxPBI CSV |
| 00:05 | Fetch WIC DB Metric dari Elasticsearch |
| 00:06 | Fetch WIC APP Metric → **auto export** WIC Metric CSV |
| 00:07 | Fetch TrxPBI Loader → **auto export** TrxPBI Loader CSV |
| 00:08 | Fetch System Online → **auto export** System Online CSV |
| 00:09 | Fetch Job Execution (Space-X, server London, via proxy Grafana) |
| 00:10 | Fetch APP Metric DC (Space-X, server London, via proxy Grafana) |
| 00:11 | Fetch DB Metric DC (Space-X, server London, via proxy Grafana) |
| 00:12 | Fetch File Archive (Space-X, server London, via proxy Grafana) |
| 00:13 | Fetch Job Execution (Space-X, server New York, via proxy Grafana) |
| 00:14 | Fetch File Archive (Space-X, server New York, via proxy Grafana) |
| 00:15 | Fetch APP Metric DC (Space-X, server New York, via proxy Grafana) |
| 00:16 | Fetch DB Metric DC (Space-X, server New York, via proxy Grafana) |

Resource Space-X (Job Execution, File Archive, APP/DB Metric DC — server London maupun New York) belum punya auto export CSV terjadwal — export-nya masih manual dari panel.

File CSV disimpan di folder yang dikonfigurasi di `.env` (`TRX_PBI_EXPORT_PATH` / `WIC_METRIC_EXPORT_PATH` / `TRX_PBI_LOADER_EXPORT_PATH` / `SYSTEM_ONLINE_EXPORT_PATH`, dipetakan di `config/exports.php`; dikosongkan berarti memakai `storage/app/exports`), terstruktur per tahun/bulan/tanggal. Nama file dibedakan lewat `kode_prefix` di tabel `report_sources` (mis. `BP` untuk TrxPBI, `SPB` untuk TrxPBI Loader, `SPI` untuk WIC Metric, `SPO` untuk System Online), sehingga aman berdampingan dalam satu folder.

### Menjalankan Scheduler

**Development — jalankan di terminal (polling tiap menit):**

```bash
php artisan schedule:work
```

Terminal harus tetap terbuka selama dipakai.

**Production — Windows Task Scheduler:**

Buat satu task yang menjalankan `php artisan schedule:run` **setiap menit**. Laravel sendiri yang menentukan command mana yang waktunya tiba, jadi cukup satu task untuk seluruh jadwal.

| Pengaturan | Nilai |
|---|---|
| Program/script | path lengkap ke `php.exe` (mis. `C:\laragon\bin\php\php-8.2.31-nts-Win32-vs16-x64\php.exe`) |
| Add arguments | `artisan schedule:run` |
| Start in | folder root project — **wajib diisi**, kalau kosong Laravel tidak menemukan `.env` |
| Trigger | Daily, ulangi tiap **1 menit** selama **indefinitely** |
| Run whether user is logged on or not | dicentang, supaya tetap jalan tanpa ada yang login |
| Akun | user yang punya izin tulis ke folder export dan ke `storage/` |

Hal yang paling sering bikin gagal: *Start in* dikosongkan, atau task dijalankan dengan akun yang tidak punya akses ke folder tujuan export (mis. folder OneDrive milik user lain).

Untuk memastikan scheduler benar-benar jalan, cek menu **Manajemen → Activity Log** dan filter aksi *Fetch Terjadwal* / *Export Terjadwal* — hasil tiap jadwal tercatat di sana tanpa perlu membuka file log di server.

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
php artisan report:fetch-system-online
php artisan report:fetch-system-online --date=YYYY-MM-DD     # tanggal tertentu
php artisan report:fetch-wic-metric
php artisan report:fetch-wic-app-metric

# Fetch data kemarin dari Elasticsearch via proxy Grafana (Space-X, server London)
php artisan report:fetch-spacex-ldn-job-execution
php artisan report:fetch-spacex-ldn-job-execution --date=YYYY-MM-DD
php artisan report:fetch-spacex-ldn-file-archive
php artisan report:fetch-spacex-ldn-file-archive --date=YYYY-MM-DD
php artisan report:fetch-spacex-ldn-dc-app-metric
php artisan report:fetch-spacex-ldn-dc-app-metric --date=YYYY-MM-DD
php artisan report:fetch-spacex-ldn-dc-db-metric
php artisan report:fetch-spacex-ldn-dc-db-metric --date=YYYY-MM-DD

# Fetch data kemarin dari Elasticsearch via proxy Grafana (Space-X, server New York)
php artisan report:fetch-spacex-nyc-job-execution
php artisan report:fetch-spacex-nyc-job-execution --date=YYYY-MM-DD
php artisan report:fetch-spacex-nyc-file-archive
php artisan report:fetch-spacex-nyc-file-archive --date=YYYY-MM-DD
php artisan report:fetch-spacex-nyc-dc-app-metric
php artisan report:fetch-spacex-nyc-dc-app-metric --date=YYYY-MM-DD
php artisan report:fetch-spacex-nyc-dc-db-metric
php artisan report:fetch-spacex-nyc-dc-db-metric --date=YYYY-MM-DD

# Export CSV
php artisan report:export-trx-pbi-csv                          # TrxPBI kemarin
php artisan report:export-trx-pbi-csv --date=YYYY-MM-DD         # TrxPBI tanggal tertentu
php artisan report:export-trx-pbi-loader-csv                    # TrxPBI Loader kemarin
php artisan report:export-trx-pbi-loader-csv --date=YYYY-MM-DD  # TrxPBI Loader tanggal tertentu
php artisan report:export-system-online-csv                     # System Online kemarin
php artisan report:export-system-online-csv --date=YYYY-MM-DD   # System Online tanggal tertentu
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
     ├── Koneksi langsung ── ElasticsearchService (Engine Notif, mTeleplus, TrxPBI, WIC, dll.)
     └── Proxy Grafana ───── GrafanaElasticsearchService (resource Space-X, server London & New York)
               │
               ├── Otomatis: scheduler harian (lihat tabel Scheduler di atas)
               └── Manual: dari panel (form fetch per rentang tanggal, maks 90 hari)
                         │
                         ▼
               Service::fetchAndStore(Carbon $date)
                         │
                         └── Model::updateOrCreate()
                                   │
                                   ▼
                         Database MySQL
                                   │
                                   ├── MoonShine Panel
                                   │        ├── Table (filter, sort, pagination, export Excel/CSV)
                                   │        └── Chart (Fragment async + filter tipe metrik)
                                   │
                                   └── Auto Export CSV (TrxPBI, TrxPBI Loader, System Online & WIC Metric —
                                       resource Space-X belum punya auto export terjadwal)
```

---

## Role Panel & Hak Akses

Sistem role bersifat dinamis, dikelola dari database — bukan hardcode.

- **Admin** selalu memiliki akses penuh ke semua resource, tidak bisa dibatasi. Admin juga tidak bisa menurunkan role sendiri, atau mengubah/menghapus akun Admin lain (self & cross-admin protection).
- **Role lain** aksesnya diatur per-resource lewat halaman **Manajemen → Hak Akses Role**:
  - Tab **Kelola Resource** — daftar resource yang bisa diatur per role, bisa tambah/hapus dari resource yang tersedia.
  - Tab **Atur Akses per Role** — matrix checkbox Role × Resource. Ada checkbox "select all" per baris (role) dan per kolom (resource).
- **Default tertutup**: resource yang belum ditambahkan ke "Kelola Resource", atau role yang belum dicentang untuk suatu resource, otomatis **tidak dapat diakses** (fail-closed) — kecuali oleh Admin.
- Resource sistem (Users, Roles, Master Aplikasi, Master Metrik, Report Sources, Activity Log) selalu admin-only secara permanen, tidak bisa dipindah ke role lain.
- Menu sidebar, halaman resource, halaman Fetch Manual, export Excel/CSV, dan Dashboard semuanya mengikuti permission yang sama secara otomatis — tidak perlu ubah kode saat admin mengubah permission dari UI.
- Permission ditegakkan di level middleware (bukan cuma tampilan menu), jadi resource yang tidak diizinkan tetap tidak bisa diakses walau URL diketik langsung. File hasil export juga disimpan di storage privat (tidak bisa diunduh langsung tanpa login).

### Pembatasan Percobaan Login

Ada dua lapis, yang paling ketat yang berlaku:

- **Per akun** (bawaan MoonShine) — 5 percobaan gagal per menit untuk kombinasi username + IP.
- **Per alamat IP** — 10 percobaan gagal per 6 menit, berapa pun jumlah akun yang dicoba. Lapis ini menutup *password spraying*, yaitu mencoba banyak akun berbeda dari satu sumber sehingga batas per-akun tidak pernah tersentuh.

Hitungan per-IP hanya menghitung percobaan yang gagal dan langsung direset begitu ada login berhasil dari IP tersebut, jadi user sah yang salah ketik tidak ikut terkunci. Ambangnya diatur lewat konstanta di `app/Services/LoginIpThrottle.php`. Akun yang terkunci dan IP yang diblokir ikut tercatat di Activity Log.

> **Catatan saat deploy di belakang reverse proxy** (mis. setup HTTPS via web server): daftarkan `trustProxies` di `bootstrap/app.php`. Tanpa itu semua request terlihat berasal dari IP proxy, sehingga kuota per-IP jadi dibagi oleh seluruh user dan satu orang yang salah ketik berulang bisa mengunci semua orang.

### Kebijakan Password

Password akun (baik dibuat admin lewat resource Users, maupun diganti sendiri lewat halaman profil) wajib minimal **12 karakter**, kombinasi huruf besar-kecil dan angka. Syarat simbol sengaja tidak diwajibkan karena akses aplikasi ini terbatas (bukan publik). Diatur terpusat di `AppServiceProvider` — kalau perlu disesuaikan lagi, cukup ubah di satu method (`configurePasswordPolicy()`).

> Perintah `php artisan moonshine:user` (pembuatan akun admin pertama saat instalasi) **tidak** melewati validasi ini — password yang diketik langsung di-hash apa adanya. Kebijakan di atas baru berlaku untuk perubahan/pembuatan akun berikutnya lewat panel.

### Keamanan Export

- **Formula injection dinetralkan** di seluruh export CSV/Excel — baik yang dipicu manual dari panel maupun yang berjalan terjadwal. Sel yang diawali `=`, `+`, `@`, atau tab (pola umum serangan CSV formula injection) diberi awalan tanda kutip supaya dibuka sebagai teks biasa, bukan dieksekusi sebagai rumus oleh Excel/LibreOffice. Nilai lain (termasuk yang diawali `-` seperti angka negatif atau tanda `-` kosong) tidak terpengaruh.
- **Nama file export terjadwal dibersihkan** dari karakter yang tidak sah — `kode_prefix`, `app_id`, dan `service_integrator` di Report Sources bisa diedit dari panel dan disambung langsung ke nama file, jadi nilai yang tidak wajar (mis. mengandung `../`) tidak lagi bisa mengarahkan file ke luar folder tujuan.

---

## Halaman Admin Panel

### Menu: Manajemen (khusus Admin)

**Users** — kelola akun panel

**Roles** — kelola daftar role

**Hak Akses Role** — atur resource apa saja yang bisa diakses tiap role (lihat bagian Role Panel & Hak Akses)

**Activity Log** — jejak audit aktivitas user: waktu, nama user, alamat IP, jenis aksi, dan deskripsi. Bisa difilter per tanggal / jenis aksi / user, serta diekspor ke Excel/CSV. Bersifat read-only (tidak bisa dibuat, diubah, atau dihapus dari panel) supaya jejaknya tidak bisa dimanipulasi dari UI. Nilai sensitif seperti password dan token tidak ikut disimpan.

### Menu: App Metric

**Data Metric** — input manual metrik aplikasi, dengan grafik per jenis metrik

**Master Aplikasi** — daftar nama aplikasi (khusus Admin)

**Master Metrik** — daftar jenis metrik & satuan default (khusus Admin)

**Report Sources** — konfigurasi metadata sumber data (khusus Admin)

### Menu: Elastic

**Engine Notif Reports** — tabel per jam, chart, fetch manual, export Excel & CSV

**Mteleplus Reports** — tabel per jam, chart, fetch manual, export Excel & CSV

### Menu: WIC

Gabungan seluruh resource bersumber data WIC dalam satu grup menu, urutan tampil sesuai daftar berikut:

**TrxPBI Limit** — tabel per jam per mata uang, chart (ValueMetric + LineChart + DonutChart), fetch manual, export

**TrxPBI Settlement** — tabel per jam per mata uang, chart (ValueMetric + LineChart + DonutChart), fetch manual, export

**System Online** — tabel response time rata-rata per jam per service (SVC Service & Login); chart Response Time Avg (ms) per service; filter service; fetch manual; export Excel & CSV

**WIC APP (HQWIC)** — metrik server WIC APP per jam; chart CPU (Max/Avg/Min %), Memory (Max/Avg/Min %), Disk Usage (% semua disk dalam satu chart); filter tipe metrik; export Excel & CSV

**WIC DB (WICADBDC)** — identik dengan WIC APP namun data dari server WIC DB

**Batch Job (TrxPBI Loader)** — tabel batch job per jam per status; chart Record Processed, Throughput (row/detik), dan Durasi (success vs failed); filter status job; fetch manual; export Excel & CSV

### Menu: Space-X → Server London

Resource reporting luar negeri, khusus server `london_dc`, data diambil lewat proxy datasource Grafana (bukan koneksi ES langsung):

**Job Execution** — tabel status & durasi batch job per hari (`Batch_edw.sh`, `run_edw_dblink.sh`); chart perbandingan durasi minggu ini vs minggu lalu per job; fetch manual; export Excel & CSV

**File Archive** — tabel hasil pengecekan file archive (row count) per hari per grup file; chart tren row count per grup file; fetch manual; export Excel & CSV

**APP Metric (DC)** — metrik server host `HQREPOLDNDC` (CPU, Memory, Disk) dari Metricbeat via Grafana; fetch manual; export Excel & CSV

**DB Metric (DC)** — metrik server host `REPODBLDNDC` (CPU, Memory, Disk) dari Metricbeat via Grafana; fetch manual; export Excel & CSV

### Menu: Space-X → Server New York

Struktur identik dengan Server London (resource, filter, dan jenis chart yang sama persis), khusus server `newyork_dc` (index `reportingkcln-*`) / host `HQREPONYADC` & `REPODBNYADC` (index `metricbeat-*`):

**Job Execution** — tabel status & durasi batch job per hari (`Batch_edw.sh`, `run_edw_dblink.sh`); chart perbandingan durasi minggu ini vs minggu lalu per job; fetch manual; export Excel & CSV

**File Archive** — tabel hasil pengecekan file archive (row count) per hari per grup file; chart tren row count per grup file; fetch manual; export Excel & CSV

**APP Metric (DC)** — metrik server host `HQREPONYADC` (CPU, Memory, Disk) dari Metricbeat via Grafana; fetch manual; export Excel & CSV

**DB Metric (DC)** — metrik server host `REPODBNYADC` (CPU, Memory, Disk) dari Metricbeat via Grafana; fetch manual; export Excel & CSV

> Menu **Server Tokyo** masih kosong (placeholder untuk penambahan server lain nanti).

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
