<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DinasPendidikan;

class DinasPendidikanSeeder extends Seeder
{
    public function run(): void
    {
        DinasPendidikan::firstOrCreate(
            ['kode_wilayah' => 'DINAS01'],
            [
                'nama'         => 'Dinas Pendidikan Kabupaten Contoh',
                'kota'         => 'Kabupaten Contoh',
                'provinsi'     => 'Jawa Tengah',
                'alamat'       => 'Jl. Pendidikan No. 1, Kabupaten Contoh',
                'telepon'      => '0271-123456',
                'email'        => 'dinas@contoh.go.id',
                'kepala_dinas' => 'Dr. Ahmad Sudrajat, M.Pd.',
                'is_active'    => true,
            ]
        );
    }
}
