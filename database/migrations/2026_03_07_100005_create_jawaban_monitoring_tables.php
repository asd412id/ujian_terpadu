<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Peserta terdaftar di sesi
        Schema::create('sesi_peserta', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sesi_id')->constrained('sesi_ujian')->cascadeOnDelete();
            $table->foreignUuid('peserta_id')->constrained('peserta')->cascadeOnDelete();
            $table->string('token_ujian', 64)->unique()->nullable(); // auth token saat ujian berlangsung
            $table->json('urutan_soal')->nullable(); // array soal ID yg sudah diacak per peserta
            $table->enum('status', [
                'terdaftar', 'belum_login', 'login', 'mengerjakan',
                'tidak_hadir', 'submit', 'dinilai'
            ])->default('terdaftar');
            $table->string('ip_address', 45)->nullable();
            $table->string('browser_info')->nullable();
            $table->string('device_type', 20)->nullable(); // mobile, tablet, desktop
            $table->timestamp('mulai_at')->nullable();
            $table->timestamp('submit_at')->nullable();
            $table->integer('durasi_aktual_detik')->nullable();
            $table->integer('soal_terjawab')->default(0);
            $table->integer('soal_ditandai')->default(0);
            $table->decimal('nilai_akhir', 6, 2)->nullable();
            $table->decimal('nilai_benar', 6, 2)->nullable();
            $table->integer('jumlah_benar')->nullable();
            $table->integer('jumlah_salah')->nullable();
            $table->integer('jumlah_kosong')->nullable();
            $table->timestamps();

            $table->unique(['sesi_id', 'peserta_id']);
            $table->index(['sesi_id', 'status']);
            $table->index('token_ujian');
        });

        // Jawaban peserta
        Schema::create('jawaban_peserta', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sesi_peserta_id')->constrained('sesi_peserta')->cascadeOnDelete();
            $table->foreignUuid('soal_id')->constrained('soal')->cascadeOnDelete();
            // Jawaban berdasarkan tipe soal
            $table->json('jawaban_pg')->nullable(); // ["A"] atau ["A","C"] untuk kompleks
            $table->text('jawaban_teks')->nullable(); // untuk isian/essay
            $table->json('jawaban_pasangan')->nullable(); // [[1,3],[2,1],[3,4]] untuk menjodohkan
            $table->string('file_essay')->nullable(); // upload file untuk essay
            $table->boolean('is_ditandai')->default(false);
            $table->boolean('is_terjawab')->default(false);
            $table->decimal('skor_auto', 5, 2)->nullable(); // skor otomatis (PG, PGK, dll)
            $table->decimal('skor_manual', 5, 2)->nullable(); // skor manual (essay)
            $table->foreignUuid('dinilai_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dinilai_at')->nullable();
            $table->text('catatan_penilai')->nullable();
            $table->timestamp('waktu_jawab')->nullable(); // kapan terakhir menjawab
            $table->integer('durasi_jawab_detik')->nullable(); // berapa lama di soal ini
            $table->string('idempotency_key', 64)->nullable(); // untuk offline sync
            $table->timestamps();

            $table->unique(['sesi_peserta_id', 'soal_id']);
            $table->index('idempotency_key');
            $table->index(['sesi_peserta_id', 'is_terjawab']);
        });

        // Log aktivitas ujian (anti-cheat monitoring)
        Schema::create('log_aktivitas_ujian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sesi_peserta_id')->constrained('sesi_peserta')->cascadeOnDelete();
            $table->enum('tipe_event', [
                'login', 'mulai_ujian', 'pindah_soal',
                'ganti_tab', 'fullscreen_exit', 'fullscreen_enter',
                'copy_paste', 'klik_kanan', 'tidak_fokus',
                'koneksi_putus', 'koneksi_pulih',
                'sync_offline', 'submit_jawaban', 'submit_ujian',
                'browser_minimize', 'screenshot_attempt'
            ]);
            $table->json('detail')->nullable(); // detail tambahan event
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sesi_peserta_id', 'tipe_event']);
            $table->index(['sesi_peserta_id', 'created_at']);
        });

        // Import jobs tracking
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('sekolah_id')->nullable()->constrained('sekolah')->nullOnDelete();
            $table->enum('tipe', ['soal_excel', 'soal_word', 'peserta_excel']);
            $table->string('filename');
            $table->string('filepath');
            $table->enum('status', ['pending', 'processing', 'selesai', 'gagal'])->default('pending');
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('success_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->json('errors')->nullable(); // list error per baris
            $table->text('catatan')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('log_aktivitas_ujian');
        Schema::dropIfExists('jawaban_peserta');
        Schema::dropIfExists('sesi_peserta');
    }
};
