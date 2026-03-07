<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kategori / Mapel soal
        Schema::create('kategori_soal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama'); // Matematika, Fisika, B.Indonesia, dll
            $table->string('kode', 20)->nullable(); // MTK, FIS, BIN
            $table->enum('jenjang', ['SD', 'SMP', 'SMA', 'SMK', 'MA', 'MTs', 'MI', 'SEMUA'])->default('SEMUA');
            $table->string('kelompok')->nullable(); // Saintek, Soshum, Bahasa, Wajib
            $table->string('kurikulum')->default('Merdeka'); // Merdeka, K13
            $table->integer('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Bank Soal utama
        Schema::create('soal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kategori_id')->constrained('kategori_soal');
            $table->foreignUuid('sekolah_id')->nullable()->constrained('sekolah')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users');
            $table->enum('tipe_soal', ['pg', 'pg_kompleks', 'menjodohkan', 'isian', 'essay']);
            $table->longText('pertanyaan'); // HTML rich text
            $table->string('gambar_soal')->nullable(); // path gambar soal
            $table->enum('posisi_gambar', ['atas', 'bawah', 'kiri', 'kanan'])->default('bawah')->nullable();
            $table->enum('tingkat_kesulitan', ['mudah', 'sedang', 'sulit'])->default('sedang');
            $table->decimal('bobot', 5, 2)->default(1.00);
            $table->text('pembahasan')->nullable(); // penjelasan jawaban
            $table->string('sumber')->nullable(); // sumber soal
            $table->integer('tahun_soal')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->json('tags')->nullable(); // tag tambahan
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kategori_id', 'tipe_soal', 'tingkat_kesulitan']);
            $table->index(['sekolah_id', 'is_active']);
        });

        // Opsi jawaban untuk PG, PG Kompleks
        Schema::create('opsi_jawaban', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('soal_id')->constrained('soal')->cascadeOnDelete();
            $table->string('label', 5); // A, B, C, D, E
            $table->text('teks')->nullable(); // teks opsi (nullable jika hanya gambar)
            $table->string('gambar')->nullable(); // gambar pada opsi
            $table->boolean('is_benar')->default(false);
            $table->integer('urutan')->default(0);
            $table->timestamps();

            $table->index(['soal_id', 'is_benar']);
        });

        // Pasangan untuk soal menjodohkan
        Schema::create('pasangan_soal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('soal_id')->constrained('soal')->cascadeOnDelete();
            $table->text('kiri_teks')->nullable();
            $table->string('kiri_gambar')->nullable();
            $table->text('kanan_teks')->nullable();
            $table->string('kanan_gambar')->nullable();
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pasangan_soal');
        Schema::dropIfExists('opsi_jawaban');
        Schema::dropIfExists('soal');
        Schema::dropIfExists('kategori_soal');
    }
};
