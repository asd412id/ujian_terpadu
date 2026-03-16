<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PesertaLoginController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Dinas\DashboardController as DinasDashboardController;
use App\Http\Controllers\Dinas\MonitoringController;
use App\Http\Controllers\Dinas\LaporanController as DinasLaporanController;
use App\Http\Controllers\Dinas\SekolahController as DinasSekolahController;
use App\Http\Controllers\Sekolah\DashboardController as SekolahDashboardController;
use App\Http\Controllers\Sekolah\PesertaController;
use App\Http\Controllers\Sekolah\PaketUjianController;
use App\Http\Controllers\Sekolah\KartuLoginController;
use App\Http\Controllers\Ujian\LobbyController;
use App\Http\Controllers\Ujian\UjianController;
use App\Http\Controllers\Ujian\JawabanController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root redirect
Route::get('/', function () {
    if (Auth::guard('web')->check()) {
        /** @var \App\Models\User $user */
        $user = Auth::guard('web')->user();
        return redirect()->route($user->getDashboardRoute());
    }
    if (Auth::guard('peserta')->check()) {
        $peserta = Auth::guard('peserta')->user();
        if (! $peserta->is_active) {
            Auth::guard('peserta')->logout();
            return redirect()->route('login');
        }
        return redirect()->route('ujian.lobby');
    }
    return redirect()->route('login');
});

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

// Account Settings (all authenticated admin/dinas/sekolah/pengawas users)
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'edit'])->name('edit');
    Route::put('/', [AccountController::class, 'update'])->name('update');
});

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
        Route::middleware('no.active.exam')->group(function () {
            Route::get('/lobby', [LobbyController::class, 'index'])->name('lobby');
            Route::get('/{sesiPeserta}/konfirmasi', [UjianController::class, 'konfirmasi'])->name('konfirmasi');
        });
        Route::get('/{sesiPeserta}/mengerjakan', [UjianController::class, 'mengerjakan'])->name('mengerjakan');
        Route::post('/{sesiPeserta}/submit', [UjianController::class, 'submit'])->name('submit');
        Route::get('/{sesiPeserta}/selesai', [UjianController::class, 'selesai'])->name('selesai');
    });
});

// =============================================================
// API — Offline Sync (token-based, no CSRF)
// =============================================================
Route::prefix('api/ujian')->name('api.ujian.')->middleware(['throttle:200,1', 'verify.ujian.token'])->group(function () {
    Route::post('/sync-jawaban', [JawabanController::class, 'syncOffline'])->name('sync');
    Route::get('/status/{token}', [JawabanController::class, 'status'])->name('status');
    Route::post('/submit/{token}', [JawabanController::class, 'submitApi'])->name('submit');
    Route::post('/log-cheating', [JawabanController::class, 'logCheating'])->name('log-cheating');
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
    Route::post('/monitoring/sesi/{sesi}/reset-peserta/{sesiPeserta}', [MonitoringController::class, 'resetPesertaUjian'])->name('monitoring.sesi.reset-peserta');

    // Laporan
    Route::get('/laporan', [DinasLaporanController::class, 'index'])->name('laporan');
    Route::post('/laporan/recalculate', [DinasLaporanController::class, 'recalculate'])->name('laporan.recalculate');
    Route::get('/laporan/recalculate-progress', [DinasLaporanController::class, 'recalculateProgress'])->name('laporan.recalculate-progress');
    Route::get('/laporan/export', [DinasLaporanController::class, 'export'])->name('laporan.export');
    Route::get('/laporan/analisis-soal/{paket}', [DinasLaporanController::class, 'analisisSoal'])->name('laporan.analisis-soal');
    Route::get('/laporan/detail-siswa/{sesiPeserta}', [DinasLaporanController::class, 'detailSiswa'])->name('laporan.detail-siswa');

    // Manajemen Sekolah — Import routes HARUS sebelum resource agar tidak konflik dengan {sekolah}
    Route::get('/sekolah/import', [DinasSekolahController::class, 'showImport'])->name('sekolah.import');
    Route::post('/sekolah/import', [DinasSekolahController::class, 'import'])->name('sekolah.import.post');
    Route::get('/sekolah/import/template', [DinasSekolahController::class, 'downloadTemplate'])->name('sekolah.import.template');
    Route::get('/sekolah/import/status/{job}', [DinasSekolahController::class, 'importStatus'])->name('sekolah.import.status');
    Route::delete('/sekolah/destroy-all', [DinasSekolahController::class, 'destroyAll'])->name('sekolah.destroy-all');
    Route::resource('sekolah', \App\Http\Controllers\Dinas\SekolahController::class)->names('sekolah');

    // Bank Soal — Import routes HARUS sebelum resource agar tidak konflik dengan {soal}
    Route::get('/soal/import', [\App\Http\Controllers\Dinas\SoalController::class, 'showImport'])->name('soal.import');
    Route::post('/soal/import/word', [\App\Http\Controllers\Dinas\SoalController::class, 'importWord'])->name('soal.import.word');
    Route::post('/soal/import/zip', [\App\Http\Controllers\Dinas\SoalController::class, 'importZip'])->name('soal.import.zip');
    Route::get('/soal/import/status/{job}', [\App\Http\Controllers\Dinas\SoalController::class, 'importStatus'])->name('soal.import.status');
    Route::get('/soal/import/template/word', [\App\Http\Controllers\Dinas\SoalController::class, 'templateWord'])->name('soal.import.template.word');
    Route::get('/soal/import/template/zip', [\App\Http\Controllers\Dinas\SoalController::class, 'templateZip'])->name('soal.import.template.zip');
    Route::delete('/soal/destroy-all', [\App\Http\Controllers\Dinas\SoalController::class, 'destroyAll'])->name('soal.destroy-all');
    Route::get('/soal/preview-all', [\App\Http\Controllers\Dinas\SoalController::class, 'previewAll'])->name('soal.preview-all');
    Route::post('/soal/upload-image', [\App\Http\Controllers\Dinas\SoalController::class, 'uploadImage'])->name('soal.upload-image');
    Route::resource('soal', \App\Http\Controllers\Dinas\SoalController::class)->names('soal');

    // Narasi Soal
    Route::get('/narasi/api/by-kategori', [\App\Http\Controllers\Dinas\NarasiSoalController::class, 'apiByKategori'])->name('narasi.api.by-kategori');
    Route::resource('narasi', \App\Http\Controllers\Dinas\NarasiSoalController::class)->names('narasi');

    // Kategori Soal
    Route::resource('kategori', \App\Http\Controllers\Dinas\KategoriSoalController::class)->names('kategori');

    // Paket Ujian
    Route::get('/paket/trash', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'trash'])->name('paket.trash');
    Route::post('/paket/{paket_trashed}/restore', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'restore'])->name('paket.restore')->withTrashed();
    Route::delete('/paket/{paket_trashed}/force-delete', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'forceDelete'])->name('paket.force-delete')->withTrashed();
    Route::resource('paket', \App\Http\Controllers\Dinas\PaketUjianController::class)->names('paket');
    Route::post('/paket/{paket}/publish', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'publish'])->name('paket.publish');
    Route::post('/paket/{paket}/draft', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'draft'])->name('paket.draft');
    Route::get('/paket/{paket}/soal/bank', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'bankSoal'])->name('paket.soal.bank');
    Route::post('/paket/{paket}/soal/add', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'soalAdd'])->name('paket.soal.add');
    Route::put('/paket/{paket}/soal/sync', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'soalSync'])->name('paket.soal.sync');
    Route::delete('/paket/{paket}/soal/{soal}', [\App\Http\Controllers\Dinas\PaketUjianController::class, 'soalRemove'])->name('paket.soal.remove');

    // Sesi Ujian CRUD (nested under paket)
    Route::post('/paket/{paket}/sesi', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'store'])->name('paket.sesi.store');
    Route::get('/paket/{paket}/sesi/{sesi}/edit', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'edit'])->name('paket.sesi.edit');
    Route::put('/paket/{paket}/sesi/{sesi}', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'update'])->name('paket.sesi.update');
    Route::delete('/paket/{paket}/sesi/{sesi}', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'destroy'])->name('paket.sesi.destroy');
    // Peserta Sesi
    Route::get('/paket/{paket}/sesi/{sesi}/peserta', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'peserta'])->name('paket.sesi.peserta');
    Route::post('/paket/{paket}/sesi/{sesi}/peserta/add', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'addPeserta'])->name('paket.sesi.peserta.add');
    Route::post('/paket/{paket}/sesi/{sesi}/peserta/remove', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'removePeserta'])->name('paket.sesi.peserta.remove');
    Route::post('/paket/{paket}/sesi/{sesi}/peserta/reset', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'resetPeserta'])->name('paket.sesi.peserta.reset');
    Route::post('/paket/{paket}/sesi/{sesi}/peserta/sync', [\App\Http\Controllers\Dinas\SesiUjianController::class, 'syncPesertaBaru'])->name('paket.sesi.peserta.sync');

    // Grading Essay
    Route::get('/grading', [\App\Http\Controllers\Dinas\GradingController::class, 'index'])->name('grading');
    Route::post('/grading/{jawaban}', [\App\Http\Controllers\Dinas\GradingController::class, 'nilai'])->name('grading.nilai');

    // User management
    Route::resource('users', \App\Http\Controllers\Dinas\UserController::class)->names('users');

    // Peserta management (dinas kelola semua peserta lintas sekolah)
    // Import routes HARUS sebelum resource agar tidak konflik dengan {peserta}
    Route::get('/peserta/import', [\App\Http\Controllers\Dinas\PesertaController::class, 'showImport'])->name('peserta.import');
    Route::post('/peserta/import', [\App\Http\Controllers\Dinas\PesertaController::class, 'import'])->name('peserta.import.post');
    Route::get('/peserta/import/template', [\App\Http\Controllers\Dinas\PesertaController::class, 'downloadTemplate'])->name('peserta.import.template');
    Route::get('/peserta/import/status/{job}', [\App\Http\Controllers\Dinas\PesertaController::class, 'importStatus'])->name('peserta.import.status');
    Route::delete('/peserta/destroy-all', [\App\Http\Controllers\Dinas\PesertaController::class, 'destroyAll'])->name('peserta.destroy-all');
    Route::resource('peserta', \App\Http\Controllers\Dinas\PesertaController::class)
         ->names('peserta')
         ->parameters(['peserta' => 'peserta']);
});

// =============================================================
// ADMIN SEKOLAH
// =============================================================
Route::prefix('sekolah')->name('sekolah.')->middleware(['auth', 'role:admin_sekolah,super_admin,admin_dinas'])->group(function () {
    Route::get('/dashboard', [SekolahDashboardController::class, 'index'])->name('dashboard');

    // Peserta (sekolah hanya bisa lihat daftar + import + cetak kartu, tidak bisa tambah/edit/hapus manual)
    // Import routes HARUS sebelum resource agar tidak konflik dengan {peserta}
    Route::get('/peserta/import', [PesertaController::class, 'showImport'])->name('peserta.import');
    Route::post('/peserta/import', [PesertaController::class, 'import'])->name('peserta.import.post');
    Route::get('/peserta/import/template', [PesertaController::class, 'downloadTemplate'])->name('peserta.import.template');
    Route::get('/peserta/import/status/{job}', [PesertaController::class, 'importStatus'])->name('peserta.import.status');
    Route::delete('/peserta/destroy-all', [PesertaController::class, 'destroyAll'])->name('peserta.destroy-all');
    Route::resource('peserta', PesertaController::class)->only(['index'])->names('peserta')->parameters(['peserta' => 'peserta']);

    // Kartu Login
    Route::get('/kartu-login', [KartuLoginController::class, 'index'])->name('kartu.index');
    Route::get('/kartu-login/cetak-semua', [KartuLoginController::class, 'cetakSemua'])->name('kartu.cetak-semua');
    Route::get('/kartu-login/{sesi}', [KartuLoginController::class, 'preview'])->name('kartu.preview');
    Route::get('/kartu-login/{sesi}/cetak', [KartuLoginController::class, 'cetak'])->name('kartu.cetak');
    Route::get('/kartu-login/peserta/{peserta}', [KartuLoginController::class, 'cetakSatu'])->name('kartu.satu');
    Route::get('/kartu-login/peserta/{peserta}/show', [KartuLoginController::class, 'show'])->name('kartu.show');

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
    Route::get('/sesi/{sesi}/api', [\App\Http\Controllers\Pengawas\MonitoringRuangController::class, 'apiSesi'])->name('sesi.api');
});

// =============================================================
// PWA
// =============================================================
Route::get('/manifest.json', fn () => response()->file(public_path('manifest.json'), ['Content-Type' => 'application/manifest+json']))->name('pwa.manifest');
Route::get('/offline', fn () => view('offline'))->name('offline');
