<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite index on sesi_peserta (peserta_id, status) — critical for monitoring queries
        Schema::table('sesi_peserta', function (Blueprint $table) {
            $table->index(['peserta_id', 'status'], 'idx_sp_peserta_status');
            $table->index(['sesi_id', 'status'], 'idx_sp_sesi_status');
        });

        // sesi_ujian.status — used in monitoring, dashboard, sync queries
        Schema::table('sesi_ujian', function (Blueprint $table) {
            $table->index('status', 'idx_sesi_status');
        });

        // users.role — used in middleware auth checks
        Schema::table('users', function (Blueprint $table) {
            $table->index('role', 'idx_users_role');
        });

        // import_jobs.status — used for import monitoring
        Schema::table('import_jobs', function (Blueprint $table) {
            $table->index('status', 'idx_import_jobs_status');
        });

        // jawaban_peserta (sesi_peserta_id, soal_id) — used in analisis soal
        Schema::table('jawaban_peserta', function (Blueprint $table) {
            $table->index(['sesi_peserta_id', 'soal_id'], 'idx_jp_sp_soal');
        });
    }

    public function down(): void
    {
        Schema::table('sesi_peserta', function (Blueprint $table) {
            $table->dropIndex('idx_sp_peserta_status');
            $table->dropIndex('idx_sp_sesi_status');
        });

        Schema::table('sesi_ujian', function (Blueprint $table) {
            $table->dropIndex('idx_sesi_status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role');
        });

        Schema::table('import_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_import_jobs_status');
        });

        Schema::table('jawaban_peserta', function (Blueprint $table) {
            $table->dropIndex('idx_jp_sp_soal');
        });
    }
};
