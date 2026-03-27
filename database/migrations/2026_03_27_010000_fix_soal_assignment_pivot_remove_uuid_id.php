<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kategori_soal_user', 'id')) {
            Schema::table('kategori_soal_user', function (Blueprint $table) {
                $table->dropColumn('id');
            });
        }

        if (Schema::hasColumn('soal_user', 'id')) {
            Schema::table('soal_user', function (Blueprint $table) {
                $table->dropColumn('id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('kategori_soal_user', function (Blueprint $table) {
            $table->uuid('id')->first();
        });

        Schema::table('soal_user', function (Blueprint $table) {
            $table->uuid('id')->first();
        });
    }
};
