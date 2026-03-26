<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Assignment by kategori: semua soal di kategori X → user Y
        Schema::create('kategori_soal_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('kategori_soal_id')->constrained('kategori_soal')->cascadeOnDelete();
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'kategori_soal_id']);
            $table->index('kategori_soal_id');
        });

        // Assignment per soal individual: soal X → user Y
        Schema::create('soal_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('soal_id')->constrained('soal')->cascadeOnDelete();
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'soal_id']);
            $table->index('soal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soal_user');
        Schema::dropIfExists('kategori_soal_user');
    }
};
