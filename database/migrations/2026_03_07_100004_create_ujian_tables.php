<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Peserta ujian
        Schema::create('peserta', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('sekolah')->cascadeOnDelete();
            $table->string('nisn', 20)->nullable();
            $table->string('nis', 20)->nullable(); // nomor induk sekolah
            $table->string('nama');
            $table->string('kelas', 10)->nullable(); // XII, XI, X
            $table->string('jurusan')->nullable(); // IPA, IPS, Bahasa, TKJ, dll
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->string('foto')->nullable();
            // Kredensial login ujian
            // username_ujian diisi otomatis: prioritas NIS > NISN > auto-generate
            // Peserta bisa login menggunakan NIS, NISN, atau username_ujian
            $table->string('username_ujian', 50)->unique();
            $table->string('password_ujian'); // hashed
            $table->string('password_plain', 20)->nullable(); // untuk cetak kartu (store encrypted)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sekolah_id', 'kelas', 'jurusan']);
            $table->index('nisn');
        });

        // Paket ujian
        Schema::create('paket_ujian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->nullable()->constrained('sekolah')->nullOnDelete();
            // null = dibuat dinas, tidak null = dibuat sekolah
            $table->foreignUuid('created_by')->constrained('users');
            $table->string('nama');
            $table->string('kode', 20)->nullable()->unique();
            $table->enum('jenis_ujian', ['TKA_SEKOLAH', 'SIMULASI_UTBK', 'TRYOUT', 'ULANGAN', 'PAS', 'PAT', 'LAINNYA']);
            $table->enum('jenjang', ['SD', 'SMP', 'SMA', 'SMK', 'MA', 'MTs', 'MI', 'SEMUA']);
            $table->text('deskripsi')->nullable();
            $table->integer('durasi_menit')->default(120);
            $table->integer('jumlah_soal')->default(40);
            $table->boolean('acak_soal')->default(true);
            $table->boolean('acak_opsi')->default(true);
            $table->boolean('tampilkan_hasil')->default(false); // tampilkan nilai setelah selesai
            $table->boolean('boleh_kembali')->default(true); // boleh kembali ke soal sebelumnya
            $table->integer('max_peserta')->nullable();
            $table->datetime('tanggal_mulai')->nullable();
            $table->datetime('tanggal_selesai')->nullable();
            $table->enum('status', ['draft', 'aktif', 'selesai', 'arsip'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sekolah_id', 'status']);
            $table->index(['jenis_ujian', 'jenjang']);
        });

        // Soal-soal dalam paket ujian
        Schema::create('paket_soal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('paket_id')->constrained('paket_ujian')->cascadeOnDelete();
            $table->foreignUuid('soal_id')->constrained('soal')->cascadeOnDelete();
            $table->integer('nomor_urut')->default(0);
            $table->decimal('bobot_override', 5, 2)->nullable(); // override bobot soal
            $table->timestamps();

            $table->unique(['paket_id', 'soal_id']);
            $table->index(['paket_id', 'nomor_urut']);
        });

        // Sesi ujian (ruangan/gelombang)
        Schema::create('sesi_ujian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('paket_id')->constrained('paket_ujian')->cascadeOnDelete();
            $table->string('nama_sesi'); // Sesi 1, Ruang A, Gelombang Pagi
            $table->string('ruangan')->nullable();
            $table->foreignUuid('pengawas_id')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('waktu_mulai')->nullable();
            $table->datetime('waktu_selesai')->nullable();
            $table->enum('status', ['persiapan', 'berlangsung', 'selesai'])->default('persiapan');
            $table->integer('kapasitas')->nullable();
            $table->timestamps();

            $table->index(['paket_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_ujian');
        Schema::dropIfExists('paket_soal');
        Schema::dropIfExists('paket_ujian');
        Schema::dropIfExists('peserta');
    }
};
