<?php

namespace Database\Factories;

use App\Models\KategoriSoal;
use App\Models\Sekolah;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SoalFactory extends Factory
{
    protected $model = Soal::class;

    public function definition(): array
    {
        return [
            'kategori_id'       => KategoriSoal::factory(),
            'sekolah_id'        => Sekolah::factory(),
            'created_by'        => User::factory(),
            'tipe_soal'         => 'pg',
            'pertanyaan'        => fake()->sentence(10) . '?',
            'gambar_soal'       => null,
            'posisi_gambar'     => 'atas',
            'tingkat_kesulitan' => fake()->randomElement(['mudah', 'sedang', 'sulit']),
            'bobot'             => 1.00,
            'pembahasan'        => fake()->paragraph(),
            'sumber'            => null,
            'tahun_soal'        => date('Y'),
            'is_active'         => true,
            'is_verified'       => false,
            'tags'              => null,
        ];
    }

    public function pg(): static
    {
        return $this->state(['tipe_soal' => 'pg']);
    }

    public function pgKompleks(): static
    {
        return $this->state(['tipe_soal' => 'pg_kompleks']);
    }

    public function essay(): static
    {
        return $this->state(['tipe_soal' => 'essay']);
    }

    public function isian(): static
    {
        return $this->state(['tipe_soal' => 'isian']);
    }

    public function menjodohkan(): static
    {
        return $this->state(['tipe_soal' => 'menjodohkan']);
    }

    public function benarSalah(): static
    {
        return $this->state(['tipe_soal' => 'benar_salah']);
    }
}
