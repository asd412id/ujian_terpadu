<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paket_ujian', function (Blueprint $table) {
            $table->boolean('anti_curang')->default(true)->after('boleh_kembali');
        });
    }

    public function down(): void
    {
        Schema::table('paket_ujian', function (Blueprint $table) {
            $table->dropColumn('anti_curang');
        });
    }
};
