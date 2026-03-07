<?php

namespace Database\Factories;

use App\Models\PaketUjian;
use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SesiUjianFactory extends Factory
{
    protected $model = SesiUjian::class;

    public function definition(): array
    {
        return [
            'paket_id'       => PaketUjian::factory(),
            'nama_sesi'      => 'Sesi ' . fake()->numberBetween(1, 10),
            'ruangan'        => 'Ruang ' . fake()->numberBetween(1, 20),
            'pengawas_id'    => User::factory(),
            'waktu_mulai'    => now(),
            'waktu_selesai'  => now()->addMinutes(90),
            'status'         => 'persiapan',
            'kapasitas'      => 40,
        ];
    }

    public function berlangsung(): static
    {
        return $this->state(['status' => 'berlangsung']);
    }

    public function selesai(): static
    {
        return $this->state([
            'status'        => 'selesai',
            'waktu_selesai' => now()->subMinutes(10),
        ]);
    }
}
