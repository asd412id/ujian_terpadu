<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DinasPendidikanSeeder::class,
            SekolahSeeder::class,
            UserSeeder::class,
            KategoriSoalSeeder::class,
        ]);
    }
}
