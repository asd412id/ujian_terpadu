<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Sekolah;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin Dinas
        User::firstOrCreate(
            ['email' => 'admin@dinas.test'],
            [
                'name'       => 'Administrator Dinas',
                'password'   => Hash::make('password'),
                'role'       => 'admin_dinas',
                'is_active'  => true,
            ]
        );

        // Operator untuk setiap sekolah
        $sekolahs = Sekolah::all();
        foreach ($sekolahs as $i => $sekolah) {
            $no = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
            User::firstOrCreate(
                ['email' => "operator{$no}@sekolah.test"],
                [
                    'name'       => "Operator {$sekolah->nama}",
                    'password'   => Hash::make('password'),
                    'role'       => 'admin_sekolah',
                    'sekolah_id' => $sekolah->id,
                    'is_active'  => true,
                ]
            );
        }

        // Pengawas
        User::firstOrCreate(
            ['email' => 'pengawas@sekolah.test'],
            [
                'name'       => 'Pengawas Ruang',
                'password'   => Hash::make('password'),
                'role'       => 'pengawas',
                'sekolah_id' => $sekolahs->first()?->id,
                'is_active'  => true,
            ]
        );
    }
}
