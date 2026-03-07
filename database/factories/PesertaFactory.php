<?php

namespace Database\Factories;

use App\Models\Peserta;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class PesertaFactory extends Factory
{
    protected $model = Peserta::class;

    public function definition(): array
    {
        $password = 'TEST1234';

        return [
            'sekolah_id'     => Sekolah::factory(),
            'nama'           => fake()->name(),
            'nis'            => fake()->unique()->numerify('######'),
            'nisn'           => fake()->unique()->numerify('##########'),
            'kelas'          => fake()->randomElement(['X-1', 'XI-2', 'XII-3']),
            'jurusan'        => fake()->randomElement(['IPA', 'IPS', 'Bahasa']),
            'jenis_kelamin'  => fake()->randomElement(['L', 'P']),
            'tanggal_lahir'  => fake()->date('Y-m-d', '2008-12-31'),
            'tempat_lahir'   => fake()->city(),
            'foto'           => null,
            'username_ujian' => fake()->unique()->numerify('peserta-######'),
            'password_ujian' => Hash::make($password),
            'password_plain' => encrypt($password),
            'is_active'      => true,
        ];
    }
}
