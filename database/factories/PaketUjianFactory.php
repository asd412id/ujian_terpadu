<?php

namespace Database\Factories;

use App\Models\PaketUjian;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaketUjianFactory extends Factory
{
    protected $model = PaketUjian::class;

    public function definition(): array
    {
        return [
            'sekolah_id'       => Sekolah::factory(),
            'created_by'       => User::factory(),
            'nama'             => 'Ujian ' . fake()->words(3, true),
            'kode'             => fake()->unique()->bothify('UJ-####'),
            'jenis_ujian'      => fake()->randomElement(['TKA_SEKOLAH', 'SIMULASI_UTBK', 'TRYOUT', 'ULANGAN', 'PAS', 'PAT', 'LAINNYA']),
            'jenjang'          => fake()->randomElement(['SMA', 'SMK', 'SMP']),
            'deskripsi'        => fake()->sentence(),
            'durasi_menit'     => 90,
            'jumlah_soal'      => 40,
            'acak_soal'        => false,
            'acak_opsi'        => false,
            'tampilkan_hasil'  => true,
            'boleh_kembali'    => true,
            'max_peserta'      => null,
            'tanggal_mulai'    => now(),
            'tanggal_selesai'  => now()->addDays(7),
            'status'           => 'draft',
        ];
    }

    public function aktif(): static
    {
        return $this->state(['status' => 'aktif']);
    }

    public function selesai(): static
    {
        return $this->state(['status' => 'selesai']);
    }
}
