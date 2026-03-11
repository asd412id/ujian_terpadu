<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_jobs', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('catatan');
        });

        if (DB::getDriverName() === 'sqlite') {
            // SQLite tidak support ALTER COLUMN — tipe sudah string di SQLite
        } else {
            DB::statement("ALTER TABLE import_jobs MODIFY COLUMN tipe ENUM('soal_excel','soal_word','peserta_excel','sekolah_excel') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite tidak support ALTER COLUMN
        } else {
            DB::statement("ALTER TABLE import_jobs MODIFY COLUMN tipe ENUM('soal_excel','soal_word','peserta_excel') NOT NULL");
        }

        Schema::table('import_jobs', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
