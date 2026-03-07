<?php

namespace Database\Factories;

use App\Models\KategoriSoal;
use Illuminate\Database\Eloquent\Factories\Factory;

class KategoriSoalFactory extends Factory
{
    protected $model = KategoriSoal::class;

    public function definition(): array
    {
        return [
            'nama'       => fake()->randomElement(['Matematika', 'Bahasa Indonesia', 'IPA', 'IPS', 'Bahasa Inggris']),
            'kode'       => fake()->unique()->bothify('??-###'),
            'jenjang'    => fake()->randomElement(['SMA', 'SMK', 'SMP']),
            'kelompok'   => fake()->randomElement(['Umum', 'Peminatan']),
            'kurikulum'  => 'Merdeka',
            'urutan'     => fake()->numberBetween(1, 20),
            'is_active'  => true,
        ];
    }
}
