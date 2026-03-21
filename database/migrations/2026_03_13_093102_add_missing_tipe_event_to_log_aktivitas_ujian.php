<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE log_aktivitas_ujian MODIFY COLUMN tipe_event ENUM(
                'login','mulai_ujian','pindah_soal',
                'ganti_tab','fullscreen_exit','fullscreen_enter',
                'copy_paste','klik_kanan','tidak_fokus',
                'koneksi_putus','koneksi_pulih',
                'sync_offline','submit_jawaban','submit_ujian',
                'browser_minimize','screenshot_attempt',
                'rescore_late_sync','late_submit_sync_error','final_sync_error'
            ) NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE log_aktivitas_ujian MODIFY COLUMN tipe_event ENUM(
                'login','mulai_ujian','pindah_soal',
                'ganti_tab','fullscreen_exit','fullscreen_enter',
                'copy_paste','klik_kanan','tidak_fokus',
                'koneksi_putus','koneksi_pulih',
                'sync_offline','submit_jawaban','submit_ujian',
                'browser_minimize','screenshot_attempt'
            ) NOT NULL");
        }
    }
};
