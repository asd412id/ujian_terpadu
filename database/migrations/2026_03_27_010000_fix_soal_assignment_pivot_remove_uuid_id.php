<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the uuid id column and use composite PK instead.
        // Laravel's belongsToMany sync/attach doesn't auto-generate UUIDs for pivot rows.

        Schema::table('kategori_soal_user', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('soal_user', function (Blueprint $table) {
            $table->dropColumn('id');
        });
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
