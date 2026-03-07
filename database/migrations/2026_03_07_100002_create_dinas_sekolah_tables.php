<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinas_pendidikan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama');
            $table->string('kode_wilayah', 10)->unique();
            $table->string('kota');
            $table->string('provinsi');
            $table->string('alamat')->nullable();
            $table->string('telepon', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('kepala_dinas')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sekolah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dinas_id')->constrained('dinas_pendidikan')->cascadeOnDelete();
            $table->string('nama');
            $table->string('npsn', 10)->unique()->nullable();
            $table->enum('jenjang', ['SD', 'SMP', 'SMA', 'SMK', 'MA', 'MTs', 'MI']);
            $table->string('alamat')->nullable();
            $table->string('kota')->nullable();
            $table->string('telepon', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('kepala_sekolah')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['dinas_id', 'jenjang']);
        });

        // Add FK from users to sekolah now that sekolah table exists
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('sekolah_id')->references('id')->on('sekolah')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['sekolah_id']);
        });
        Schema::dropIfExists('sekolah');
        Schema::dropIfExists('dinas_pendidikan');
    }
};
