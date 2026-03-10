# Ujian Terpadu

Sistem Ujian Online Terpadu berbasis Laravel 12 untuk pelaksanaan ujian secara daring dengan dukungan multi-role, monitoring real-time, dan mode offline (PWA).

## Fitur Utama

### Dinas Pendidikan (Admin)
- Dashboard statistik seluruh sekolah
- Manajemen sekolah, user, dan kategori soal
- Bank soal terpusat & paket ujian (publish/unpublish)
- Multi-select soal pada paket ujian (checkbox per kategori, search & filter, bulk sync)
- Monitoring ujian real-time (per sekolah & per sesi)
- Grading essay manual dengan kalkulasi nilai otomatis
- Laporan & export Excel (.xlsx) — 3 sheet: Rekap, Hasil Ujian (19 kolom), Analisis Per Soal
- Filter laporan berdasarkan paket ujian, sekolah, dan status lulus/tidak lulus

### Admin Sekolah
- Dashboard statistik sekolah
- Manajemen peserta (CRUD + import Excel)
- Input soal (manual, import Excel/Word)
- Pendaftaran peserta ke paket ujian
- Cetak kartu login peserta (per sesi / per peserta / semua)

### Pengawas
- Dashboard pengawasan
- Monitoring ruang ujian per sesi

### Peserta Ujian
- Login terpisah dengan token
- Lobby ujian (daftar sesi tersedia)
- Halaman konfirmasi sebelum mulai ujian
- Pengerjaan ujian dengan timer (6 tipe soal: PG, PG Kompleks, Menjodohkan, Isian, Essay)
- Auto-save jawaban ke IndexedDB (Dexie.js) + offline sync via API
- Submit otomatis saat waktu habis + submit manual
- Halaman selesai dengan ringkasan hasil

### Teknis
- **Laravel Octane + FrankenPHP** — persistent worker architecture, menggantikan php-fpm + nginx
- **Laravel Horizon** — queue dashboard dengan auto-scaling workers dan monitoring real-time
- PWA support (offline fallback)
- API offline sync untuk jawaban (IndexedDB + server sync dengan idempotency key)
- Final submit menyertakan seluruh jawaban sebagai safety net
- Multi-guard auth (admin via `web`, peserta via `peserta`)
- Docker production-ready (optimasi untuk 2000 peserta bersamaan pada 4 core / 4GB RAM)
- Automated tests (unit + feature)
- MySQL sebagai database utama, Redis untuk cache & queue

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12 |
| PHP | 8.3+ |
| App Server | Laravel Octane + FrankenPHP (Caddy built-in) |
| Queue | Laravel Horizon + Redis (auto-scaling workers) |
| Database | MySQL 8.0+ |
| Cache & Session | Redis 7 |
| Frontend | Blade + Alpine.js + Tailwind CSS + Vite |
| Offline Storage | Dexie.js (IndexedDB) |
| Export | Maatwebsite/Excel 3.1 (PhpSpreadsheet) |
| Import | Maatwebsite/Excel 3.1, PhpWord |
| Testing | PHPUnit |
| Container | Docker (FrankenPHP, tanpa Nginx) |
| Process Manager | Supervisord (Octane + Horizon + Scheduler) |

## Persyaratan

### Lokal
- PHP 8.3+
- Composer 2.x
- Node.js 18+ & npm
- MySQL 8.0+
- Redis 7+

### Docker (Production)
- Docker & Docker Compose
- Minimum: 4 core CPU, 4GB RAM
- Dioptimasi untuk 2000 peserta bersamaan

## Instalasi

### Lokal

```bash
# Clone repository
git clone <repository-url>
cd ujian_terpadu

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Database (MySQL)
# Sesuaikan DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD di .env
php artisan migrate --seed

# Build assets
npm run build

# Jalankan server
php artisan serve
```

### Docker (Production)

```bash
# Build & jalankan (FrankenPHP + Octane + Horizon)
docker-compose up -d --build
docker-compose exec app php artisan migrate --seed

# Deploy ulang (zero-downtime)
bash deploy.sh
```

Akses aplikasi di `http://localhost` (port 80) atau `https://localhost` (port 443).

> FrankenPHP menangani HTTP/HTTPS/HTTP3 secara langsung — **tidak perlu Nginx/Apache**.
> Horizon dashboard tersedia di `/horizon` (hanya super admin).

## Akun Default (Seeder)

| Role | Email | Password |
|------|-------|----------|
| Admin Dinas | `admin@dinas.test` | `password` |
| Operator Sekolah | `operator01@sekolah.test` | `password` |
| Pengawas | `pengawas@sekolah.test` | `password` |

> Peserta login menggunakan token yang tercetak di kartu login.

## Struktur Aplikasi

```
app/
├── Http/Controllers/
│   ├── Auth/              # Login admin & peserta
│   ├── Dinas/             # Dashboard, monitoring, soal, paket, grading, laporan
│   ├── Sekolah/           # Peserta, soal, paket, kartu login
│   ├── Pengawas/          # Dashboard & monitoring ruang
│   └── Ujian/             # Lobby, pengerjaan ujian, jawaban API
├── Exports/               # Excel export classes (Maatwebsite/Excel)
├── Models/                # Eloquent models
├── Imports/               # Excel/Word import classes
├── Providers/
│   └── HorizonServiceProvider.php  # Horizon auth gate
├── Repositories/          # Data access layer
└── Services/              # Business logic services

config/
├── octane.php             # FrankenPHP server config (workers, GC, max exec time)
└── horizon.php            # Queue supervisors & auto-balancing config

docker/
├── app/
│   ├── Dockerfile         # FrankenPHP (dunglas/frankenphp:latest-php8.3)
│   ├── php.ini            # PHP config (opcache CLI enabled)
│   └── supervisord.conf   # 3 proses: Octane, Horizon, Scheduler
└── mysql/
    └── my.cnf             # MySQL tuning (InnoDB buffer, connections)

database/
├── migrations/            # Schema migrations
├── factories/             # Model factories
└── seeders/               # Dinas, sekolah, user, kategori seeders

resources/views/
├── dinas/                 # Views admin dinas
├── sekolah/               # Views admin sekolah
├── pengawas/              # Views pengawas
├── ujian/                 # Views peserta ujian
├── auth/                  # Login pages
├── components/            # Blade components
└── layouts/               # Layout templates

tests/
├── Unit/                  # Unit tests
└── Feature/               # Feature tests (controllers, auth, API)
```

## Arsitektur Docker (Production)

```
┌─────────────────────────────────────────────────────┐
│  app (FrankenPHP + Octane)          1856 MB / 3.5 CPU│
│  ┌─────────────────────────────────────────────────┐ │
│  │ supervisord                                     │ │
│  │  ├── octane:start (FrankenPHP, 4 workers)       │ │
│  │  ├── horizon (auto-scaling queue workers)       │ │
│  │  └── scheduler (cron loop, 60s interval)        │ │
│  └─────────────────────────────────────────────────┘ │
│  Port 80 (HTTP) / 443 (HTTPS+HTTP3)                 │
├─────────────────────────────────────────────────────┤
│  mysql 8.0                           768 MB / 2 CPU  │
│  InnoDB buffer 384M, max connections 500             │
├─────────────────────────────────────────────────────┤
│  redis 7-alpine                      256 MB / 0.5 CPU│
│  Cache, session, queue broker, Horizon storage       │
└─────────────────────────────────────────────────────┘
Total: ~2880 MB (sisa ~1200 MB untuk OS + Docker overhead)
```

## Testing

```bash
# Jalankan semua test
php artisan test

# Dengan coverage
php artisan test --coverage

# Filter test tertentu
php artisan test --filter=NamaTest
```

## API Endpoints

### Offline Sync (tanpa CSRF, rate-limited 200/menit)

| Method | Endpoint | Keterangan |
|--------|----------|------------|
| POST | `/api/ujian/sync-jawaban` | Sync jawaban offline |
| GET | `/api/ujian/status/{token}` | Cek status ujian peserta |
| POST | `/api/ujian/submit/{token}` | Submit ujian via API |

## Roles & Middleware

| Role | Middleware | Prefix URL |
|------|-----------|------------|
| `admin_dinas` / `super_admin` | `auth`, `role:super_admin,admin_dinas` | `/dinas` |
| `admin_sekolah` | `auth`, `role:admin_sekolah,super_admin,admin_dinas` | `/sekolah` |
| `pengawas` | `auth`, `role:pengawas,admin_sekolah,admin_dinas,super_admin` | `/pengawas` |
| Peserta | `peserta` (guard terpisah) | `/ujian` |

## Lisensi

MIT
