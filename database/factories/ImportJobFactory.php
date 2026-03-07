<?php

namespace Database\Factories;

use App\Models\ImportJob;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportJobFactory extends Factory
{
    protected $model = ImportJob::class;

    public function definition(): array
    {
        return [
            'created_by'     => User::factory(),
            'sekolah_id'     => Sekolah::factory(),
            'tipe'           => 'peserta_excel',
            'filename'       => 'import_peserta.xlsx',
            'filepath'       => 'imports/import_peserta.xlsx',
            'status'         => 'pending',
            'total_rows'     => 0,
            'processed_rows' => 0,
            'success_rows'   => 0,
            'error_rows'     => 0,
            'errors'         => null,
            'catatan'        => null,
            'started_at'     => null,
            'completed_at'   => null,
        ];
    }

    public function processing(): static
    {
        return $this->state([
            'status'     => 'processing',
            'started_at' => now(),
        ]);
    }

    public function selesai(): static
    {
        return $this->state([
            'status'       => 'selesai',
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }
}
