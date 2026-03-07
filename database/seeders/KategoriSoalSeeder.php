<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KategoriSoal;

class KategoriSoalSeeder extends Seeder
{
    public function run(): void
    {
        $kategoriData = [
            // SD
            ['Matematika Dasar',     'SD',  'MTK-SD',  'Wajib'],
            ['Bahasa Indonesia Dasar','SD', 'BIN-SD',  'Wajib'],
            ['IPA Dasar',            'SD',  'IPA-SD',  'Wajib'],
            ['IPS Dasar',            'SD',  'IPS-SD',  'Wajib'],

            // SMP
            ['Matematika SMP',       'SMP', 'MTK-SMP', 'Wajib'],
            ['Bahasa Indonesia SMP', 'SMP', 'BIN-SMP', 'Wajib'],
            ['IPA SMP',              'SMP', 'IPA-SMP', 'Wajib'],
            ['IPS SMP',              'SMP', 'IPS-SMP', 'Wajib'],
            ['Bahasa Inggris SMP',   'SMP', 'BIG-SMP', 'Wajib'],

            // SMA
            ['Matematika Wajib SMA',    'SMA', 'MTK-SMA', 'Wajib'],
            ['Matematika Peminatan SMA','SMA', 'MTP-SMA', 'Saintek'],
            ['Bahasa Indonesia SMA',    'SMA', 'BIN-SMA', 'Wajib'],
            ['Fisika SMA',              'SMA', 'FIS-SMA', 'Saintek'],
            ['Kimia SMA',               'SMA', 'KIM-SMA', 'Saintek'],
            ['Biologi SMA',             'SMA', 'BIO-SMA', 'Saintek'],
            ['Ekonomi SMA',             'SMA', 'EKO-SMA', 'Soshum'],
            ['Sejarah SMA',             'SMA', 'SEJ-SMA', 'Wajib'],
            ['Bahasa Inggris SMA',      'SMA', 'BIG-SMA', 'Wajib'],
            ['Sosiologi SMA',           'SMA', 'SOS-SMA', 'Soshum'],
            ['Geografi SMA',            'SMA', 'GEO-SMA', 'Soshum'],
        ];

        foreach ($kategoriData as [$nama, $jenjang, $kode, $kelompok]) {
            KategoriSoal::firstOrCreate(
                ['kode' => $kode],
                [
                    'nama'     => $nama,
                    'jenjang'  => $jenjang,
                    'kelompok' => $kelompok,
                    'is_active'=> true,
                ]
            );
        }
    }
}
