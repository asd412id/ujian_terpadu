<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sekolah;
use App\Models\DinasPendidikan;

class SekolahSeeder extends Seeder
{
    public function run(): void
    {
        $dinas = DinasPendidikan::first();

        $sekolahData = [
            ['SMPN 1 Contoh',  'SMP', '20300001', 'Jl. Pahlawan No. 1, Kec. Pusat'],
            ['SMPN 2 Contoh',  'SMP', '20300002', 'Jl. Merdeka No. 5, Kec. Barat'],
            ['SMAN 1 Contoh',  'SMA', '20300010', 'Jl. Diponegoro No. 10, Kec. Timur'],
            ['SMAN 2 Contoh',  'SMA', '20300011', 'Jl. Sudirman No. 3, Kec. Utara'],
            ['SDN 1 Contoh',   'SD',  '20300020', 'Jl. Kartini No. 7, Kec. Selatan'],
        ];

        foreach ($sekolahData as [$nama, $jenjang, $npsn, $alamat]) {
            Sekolah::firstOrCreate(
                ['npsn' => $npsn],
                [
                    'dinas_id'  => $dinas?->id,
                    'nama'      => $nama,
                    'jenjang'   => $jenjang,
                    'alamat'    => $alamat,
                    'is_active' => true,
                ]
            );
        }
    }
}
