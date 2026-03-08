# Ujian Terpadu

Sistem Ujian Online Terpadu berbasis Laravel 12 untuk pelaksanaan ujian secara daring dengan dukungan multi-role, monitoring real-time, dan mode offline (PWA).

## Fitur Utama

### Dinas Pendidikan (Admin)
- Dashboard statistik seluruh sekolah
- Manajemen sekolah, user, dan kategori soal
- Bank soal terpusat & paket ujian (publish/unpublish)
- Monitoring ujian real-time (per sekolah & per sesi)
- Grading essay manual
- Laporan & export nilai

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
- Pengerjaan ujian dengan timer
- Auto-save jawaban (offline sync via API)
- Submit & halaman selesai

### Teknis
- PWA support (offline fallback)
- API offline sync untuk jawaban
- Docker-ready deployment
- 232 automated tests (unit + feature)
- SQLite default, MySQL supported

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12 |
| PHP | 8.3+ |
| Database | SQLite (default) / MySQL |
| Frontend | Blade + Tailwind CSS + Vite |
| Queue | Database driver |
| Import | Maatwebsite/Excel 3.1, PhpWord |
| Testing | PHPUnit (232 tests) |
| Container | Docker + Nginx |

## Persyaratan

- PHP 8.3+
- Composer 2.x
- Node.js 18+ & npm
- SQLite3 atau MySQL 8.0+

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

# Database (SQLite default)
touch database/database.sqlite
php artisan migrate --seed

# Build assets
npm run build

# Jalankan server
php artisan serve
```

### Docker

```bash
docker-compose up -d --build
docker-compose exec app php artisan migrate --seed
```

Akses aplikasi di `http://localhost:8080`

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
├── Models/                # Eloquent models
├── Imports/               # Excel/Word import classes
└── Services/              # Business logic services

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
