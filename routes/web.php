<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PesertaLoginController;
use App\Http\Controllers\Dinas\DashboardController as DinasDashboardController;
use App\Http\Controllers\Dinas\MonitoringController;
use App\Http\Controllers\Dinas\LaporanController as DinasLaporanController;
use App\Http\Controllers\Sekolah\DashboardController as SekolahDashboardController;
use App\Http\Controllers\Sekolah\SoalController;
use App\Http\Controllers\Sekolah\PesertaController;
use App\Http\Controllers\Sekolah\PaketUjianController;
use App\Http\Controllers\Sekolah\KartuLoginController;
use App\Http\Controllers\Ujian\LobbyController;
use App\Http\Controllers\Ujian\UjianController;
use App\Http\Controllers\Ujian\JawabanController;
use Illuminate\Support\Facades\Route;

// Root redirect
Route::get('/', fn () => redirect()->route('login'));

// =============================================================
// AUTH — Admin (Dinas / Sekolah / Pengawas)
// =============================================================
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});
Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// =============================================================
// AUTH — Peserta Ujian
// =============================================================
Route::prefix('ujian')->name('ujian.')->group(function () {
    Route::middleware('guest:peserta')->group(function () {
        Route::get('/login', [PesertaLoginController::class, 'showLogin'])->name('login');
        Route::post('/login', [PesertaLoginController::class, 'login'])->name('login.post');
    });
    Route::post('/logout', [PesertaLoginController::class, 'logout'])
        ->middleware('peserta')
        ->name('logout');

    // Lobby & Ujian (auth peserta)
    Route::middleware('peserta')->group(function () {
        Route::get('/lobby', [LobbyController::class, 'index'])->name('lobby');
        Route::get('/{sesiPeserta}', [UjianController::class, 'index'])->name('mulai');
        Route::post('/{sesiPeserta}/submit', [UjianController::class, 'submit'])->name('submit');
        Route::get('/{sesiPeserta}/selesai', [UjianController::class, 'selesai'])->name('selesai');
    });
});

// =============================================================
// API — Offline Sync (token-based, no CSRF)
// =============================================================
Route::prefix('api/ujian')->name('api.ujian.')->middleware('throttle:200,1')->group(function () {
    Route::post('/sync-jawaban', [JawabanController::class, 'syncOffline'])->name('sync');
    Route::get('/status/{token}', [JawabanController::class, 'status'])->name('status');
    Route::post('/submit/{token}', [JawabanController::class, 'submitApi'])->name('submit');
});

// =============================================================
// DINAS PENDIDIKAN
// =============================================================
Route::prefix('dinas')->name('dinas.')->middleware(['auth', 'role:super_admin,admin_dinas'])->group(function () {
    Route::get('/dashboard', [DinasDashboardController::class, 'index'])->name('dashboard');

    // Monitoring real-time
    Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring');
    Route::get('/monitoring/api', [MonitoringController::class, 'apiIndex'])->name('monitoring.api');
    Route::get('/monitoring/sekolah/{sekolah}', [MonitoringController::class, 'sekolah'])->name('monitoring.sekolah');
    Route::get('/monitoring/sekolah-all', [MonitoringController::class, 'sekolahAll'])->name('monitoring.sekolah.all');
    Route::get('/monitoring/sekolah-all/api', [MonitoringController::class, 'apiSekolahAll'])->name('monitoring.sekolah.api');
    Route::get('/monitoring/sesi/{sesi}', [MonitoringController::class, 'sesi'])->name('monitoring.sesi');
    Route::get('/monitoring/sesi/{sesi}/api', [MonitoringController::class, 'apiSesi'])->name('monitoring.sesi.api');

    // Laporan
    Route::get('/laporan', [DinasLaporanController::class, 'index'])->name('laporan');
    Route::get('/laporan/export', [DinasLaporanController::class, 'export'])->name('laporan.export');

    // Manajemen Sekolah
    Route::resource('sekolah', \App\Http\Controllers\Dinas\SekolahController::class)->names('sekolah');

    // Bank Soal (dinas bisa kelola semua soal)
    Route::resource('soal', \App\Http\Controllers\Dinas\SoalController::class)->names('dinas.soal');

    // Kategori Soal
    Route::resource('kategori', \App\Http\Controllers\Dinas\KategoriSoalController::class)->names('kategori');

    // Paket Ujian
    Route::resource('paket', \App\Http\Controllers\Dinas\PaketUjianController::class)->names('paket');
    Route::post('/paket/{paket}/publish', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'publish'])->name('paket.publish');
    Route::post('/paket/{paket}/draft', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'draft'])->name('paket.draft');
    Route::post('/paket/{paket}/soal/add', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'soalAdd'])->name('paket.soal.add');
    Route::delete('/paket/{paket}/soal/{soal}', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'soalRemove'])->name('paket.soal.remove');

    // Sesi Ujian CRUD (nested under paket)
    Route::post('/paket/{paket}/sesi', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'store'])->name('paket.sesi.store');
    Route::get('/paket/{paket}/sesi/{sesi}/edit', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'edit'])->name('paket.sesi.edit');
    Route::put('/paket/{paket}/sesi/{sesi}', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'update'])->name('paket.sesi.update');
    Route::delete('/paket/{paket}/sesi/{sesi}', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'destroy'])->name('paket.sesi.destroy');

    // Grading Essay
    Route::get('/grading', [\App\Http\Controllers\Dinas\GradingController::class, 'index'])->name('grading');
    Route::post('/grading/{jawaban}', [\App\Http\Controllers\Dinas\GradingController::class, 'nilai'])->name('grading.nilai');

    // User management
    Route::resource('users', \App\Http\Controllers\Dinas\UserController::class)->names('users');
});

// =============================================================
// ADMIN SEKOLAH
// =============================================================
Route::prefix('sekolah')->name('sekolah.')->middleware(['auth', 'role:admin_sekolah,super_admin,admin_dinas'])->group(function () {
    Route::get('/dashboard', [SekolahDashboardController::class, 'index'])->name('dashboard');

    // Peserta
    Route::get('/peserta/import', [PesertaController::class, 'showImport'])->name('peserta.import');
    Route::post('/peserta/import', [PesertaController::class, 'import'])->name('peserta.import.post');
    Route::get('/peserta/import/template', [PesertaController::class, 'downloadTemplate'])->name('peserta.import.template');
    Route::resource('peserta', PesertaController::class)->names('peserta')->parameters(['peserta' => 'peserta']);

    // Kartu Login
    Route::get('/kartu-login', [KartuLoginController::class, 'index'])->name('kartu.index');
    Route::get('/kartu-login/cetak-semua', [KartuLoginController::class, 'cetakSemua'])->name('kartu.cetak-semua');
    Route::get('/kartu-login/{sesi}', [KartuLoginController::class, 'preview'])->name('kartu.preview');
    Route::get('/kartu-login/{sesi}/cetak', [KartuLoginController::class, 'cetak'])->name('kartu.cetak');
    Route::get('/kartu-login/peserta/{peserta}', [KartuLoginController::class, 'cetakSatu'])->name('kartu.satu');
    Route::get('/kartu-login/peserta/{peserta}/show', [KartuLoginController::class, 'show'])->name('kartu.show');

    // Soal (admin sekolah input soal untuk sekolahnya)
    Route::get('/soal/import', [SoalController::class, 'showImport'])->name('soal.import');
    Route::post('/soal/import/excel', [SoalController::class, 'importExcel'])->name('soal.import.excel');
    Route::post('/soal/import/word', [SoalController::class, 'importWord'])->name('soal.import.word');
    Route::get('/soal/import/template/{format}', [SoalController::class, 'downloadTemplate'])->name('soal.import.template');
    Route::get('/soal/import/status/{job}', [SoalController::class, 'importStatus'])->name('soal.import.status');
    Route::resource('soal', SoalController::class)->names('soal');

    // Paket Ujian (lihat dan daftarkan peserta)
    Route::get('/paket', [PaketUjianController::class, 'index'])->name('paket');
    Route::get('/paket/{paket}', [PaketUjianController::class, 'show'])->name('paket.show');
    Route::post('/paket/{paket}/daftar', [PaketUjianController::class, 'daftarPeserta'])->name('paket.daftar');
});

// =============================================================
// PENGAWAS
// =============================================================
Route::prefix('pengawas')->name('pengawas.')->middleware(['auth', 'role:pengawas,admin_sekolah,admin_dinas,super_admin'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Pengawas\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/sesi/{sesi}', [\App\Http\Controllers\Pengawas\MonitoringRuangController::class, 'index'])->name('sesi');
});

// =============================================================
// PWA
// =============================================================
Route::get('/manifest.json', fn () => response()->file(public_path('manifest.json'), ['Content-Type' => 'application/manifest+json']))->name('pwa.manifest');
Route::get('/offline', fn () => view('offline'))->name('offline');
