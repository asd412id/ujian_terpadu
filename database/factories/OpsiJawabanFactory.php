<?php

namespace Database\Factories;

use App\Models\OpsiJawaban;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpsiJawabanFactory extends Factory
{
    protected $model = OpsiJawaban::class;

    public function definition(): array
    {
        return [
            'soal_id'  => Soal::factory(),
            'label'    => fake()->randomElement(['A', 'B', 'C', 'D', 'E']),
            'teks'     => fake()->sentence(),
            'gambar'   => null,
            'is_benar' => false,
            'urutan'   => fake()->numberBetween(1, 5),
        ];
    }

    public function benar(): static
    {
        return $this->state(['is_benar' => true]);
    }
}
