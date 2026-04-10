<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paket_ujian', function (Blueprint $table) {
            $table->boolean('scoring_partial')->default(true)->after('anti_curang');
        });
    }

    public function down(): void
    {
        Schema::table('paket_ujian', function (Blueprint $table) {
            $table->dropColumn('scoring_partial');
        });
    }
};
