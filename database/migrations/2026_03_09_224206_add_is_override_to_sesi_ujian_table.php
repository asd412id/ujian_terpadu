<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_ujian', function (Blueprint $table) {
            $table->boolean('is_peserta_override')->default(false)->after('kapasitas');
        });
    }

    public function down(): void
    {
        Schema::table('sesi_ujian', function (Blueprint $table) {
            $table->dropColumn('is_peserta_override');
        });
    }
};
