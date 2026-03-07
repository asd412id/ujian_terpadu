<?php

namespace Database\Factories;

use App\Models\Peserta;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SesiPesertaFactory extends Factory
{
    protected $model = SesiPeserta::class;

    public function definition(): array
    {
        return [
            'sesi_id'              => SesiUjian::factory(),
            'peserta_id'           => Peserta::factory(),
            'token_ujian'          => Str::random(64),
            'urutan_soal'          => null,
            'status'               => 'login',
            'ip_address'           => fake()->ipv4(),
            'browser_info'         => 'Chrome 120',
            'device_type'          => 'desktop',
            'mulai_at'             => null,
            'submit_at'            => null,
            'durasi_aktual_detik'  => null,
            'soal_terjawab'        => 0,
            'soal_ditandai'        => 0,
            'nilai_akhir'          => null,
            'nilai_benar'          => null,
            'jumlah_benar'         => 0,
            'jumlah_salah'         => 0,
            'jumlah_kosong'        => 0,
        ];
    }

    public function mengerjakan(): static
    {
        return $this->state([
            'status'   => 'mengerjakan',
            'mulai_at' => now(),
        ]);
    }

    public function submit(): static
    {
        return $this->state([
            'status'    => 'submit',
            'mulai_at'  => now()->subMinutes(60),
            'submit_at' => now(),
        ]);
    }
}
