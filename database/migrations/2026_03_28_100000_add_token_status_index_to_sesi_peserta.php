<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_peserta', function (Blueprint $table) {
            $table->index(['token_ujian', 'status'], 'sesi_peserta_token_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('sesi_peserta', function (Blueprint $table) {
            $table->dropIndex('sesi_peserta_token_status_index');
        });
    }
};
