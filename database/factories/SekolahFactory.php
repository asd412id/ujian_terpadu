<?php

namespace Database\Factories;

use App\Models\DinasPendidikan;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

class SekolahFactory extends Factory
{
    protected $model = Sekolah::class;

    public function definition(): array
    {
        return [
            'dinas_id'        => DinasPendidikan::factory(),
            'nama'            => 'SMA ' . fake()->lastName(),
            'npsn'            => fake()->unique()->numerify('########'),
            'jenjang'         => fake()->randomElement(['SMA', 'SMK', 'SMP']),
            'alamat'          => fake()->address(),
            'kota'            => fake()->city(),
            'telepon'         => fake()->phoneNumber(),
            'email'           => fake()->unique()->safeEmail(),
            'kepala_sekolah'  => fake()->name(),
            'logo'            => null,
            'is_active'       => true,
        ];
    }
}
