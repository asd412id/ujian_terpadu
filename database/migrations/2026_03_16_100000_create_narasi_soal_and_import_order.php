<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel narasi/teks bacaan (passage) untuk soal bernarasi
        Schema::create('narasi_soal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kategori_id')->nullable()->constrained('kategori_soal')->nullOnDelete();
            $table->foreignUuid('sekolah_id')->nullable()->constrained('sekolah')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users');
            $table->string('judul'); // label internal: "Cerpen Laskar Pelangi", "Teks Prosedur"
            $table->longText('konten'); // HTML rich text (CKEditor)
            $table->string('gambar')->nullable(); // gambar pendukung narasi
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kategori_id', 'is_active']);
            $table->index('sekolah_id');
        });

        // Tambah kolom narasi + import order ke tabel soal
        Schema::table('soal', function (Blueprint $table) {
            $table->foreignUuid('narasi_id')->nullable()->after('created_by')
                  ->constrained('narasi_soal')->nullOnDelete();
            $table->integer('urutan_dalam_narasi')->default(0)->after('narasi_id');
            $table->integer('nomor_urut_import')->nullable()->after('urutan_dalam_narasi');

            $table->index(['narasi_id', 'urutan_dalam_narasi']);
            $table->index(['kategori_id', 'nomor_urut_import']);
        });
    }

    public function down(): void
    {
        Schema::table('soal', function (Blueprint $table) {
            $table->dropForeign(['narasi_id']);
            $table->dropIndex(['narasi_id', 'urutan_dalam_narasi']);
            $table->dropIndex(['kategori_id', 'nomor_urut_import']);
            $table->dropColumn(['narasi_id', 'urutan_dalam_narasi', 'nomor_urut_import']);
        });

        Schema::dropIfExists('narasi_soal');
    }
};
