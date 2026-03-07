<?php

namespace Database\Factories;

use App\Models\DinasPendidikan;
use Illuminate\Database\Eloquent\Factories\Factory;

class DinasPendidikanFactory extends Factory
{
    protected $model = DinasPendidikan::class;

    public function definition(): array
    {
        return [
            'nama'           => 'Dinas Pendidikan ' . fake()->city(),
            'kode_wilayah'   => fake()->numerify('##.##'),
            'kota'           => fake()->city(),
            'provinsi'       => fake()->state(),
            'alamat'         => fake()->address(),
            'telepon'        => fake()->phoneNumber(),
            'email'          => fake()->unique()->safeEmail(),
            'kepala_dinas'   => fake()->name(),
            'logo'           => null,
            'is_active'      => true,
        ];
    }
}
