<?php

namespace Database\Factories;

use App\Models\PaketSoal;
use App\Models\PaketUjian;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaketSoalFactory extends Factory
{
    protected $model = PaketSoal::class;

    public function definition(): array
    {
        return [
            'paket_id'       => PaketUjian::factory(),
            'soal_id'        => Soal::factory(),
            'nomor_urut'     => fake()->numberBetween(1, 50),
            'bobot_override' => null,
        ];
    }
}
