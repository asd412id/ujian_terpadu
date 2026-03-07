<?php

namespace Database\Factories;

use App\Models\LogAktivitasUjian;
use App\Models\SesiPeserta;
use Illuminate\Database\Eloquent\Factories\Factory;

class LogAktivitasUjianFactory extends Factory
{
    protected $model = LogAktivitasUjian::class;

    public function definition(): array
    {
        return [
            'sesi_peserta_id' => SesiPeserta::factory(),
            'tipe_event'      => fake()->randomElement(['login', 'mulai_ujian', 'pindah_soal', 'ganti_tab', 'fullscreen_exit', 'submit_jawaban', 'submit_ujian', 'koneksi_putus']),
            'detail'          => ['info' => fake()->sentence()],
            'ip_address'      => fake()->ipv4(),
            'created_at'      => now(),
        ];
    }
}
