<?php

namespace Database\Factories;

use App\Models\PasanganSoal;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Factories\Factory;

class PasanganSoalFactory extends Factory
{
    protected $model = PasanganSoal::class;

    public function definition(): array
    {
        return [
            'soal_id'      => Soal::factory(),
            'kiri_teks'    => fake()->word(),
            'kiri_gambar'  => null,
            'kanan_teks'   => fake()->word(),
            'kanan_gambar' => null,
            'urutan'       => fake()->numberBetween(1, 10),
        ];
    }
}
