<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_peserta', function (Blueprint $table) {
            $table->json('urutan_opsi')->nullable()->after('urutan_soal');
        });
    }

    public function down(): void
    {
        Schema::table('sesi_peserta', function (Blueprint $table) {
            $table->dropColumn('urutan_opsi');
        });
    }
};
