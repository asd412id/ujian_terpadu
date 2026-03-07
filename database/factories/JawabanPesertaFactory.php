<?php

namespace Database\Factories;

use App\Models\JawabanPeserta;
use App\Models\SesiPeserta;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class JawabanPesertaFactory extends Factory
{
    protected $model = JawabanPeserta::class;

    public function definition(): array
    {
        return [
            'sesi_peserta_id'  => SesiPeserta::factory(),
            'soal_id'          => Soal::factory(),
            'jawaban_pg'       => null,
            'jawaban_teks'     => null,
            'jawaban_pasangan' => null,
            'file_essay'       => null,
            'is_ditandai'      => false,
            'is_terjawab'      => false,
            'skor_auto'        => null,
            'skor_manual'      => null,
            'dinilai_oleh'     => null,
            'dinilai_at'       => null,
            'catatan_penilai'  => null,
            'waktu_jawab'      => now(),
            'durasi_jawab_detik' => fake()->numberBetween(10, 300),
            'idempotency_key'  => Str::uuid()->toString(),
        ];
    }

    public function pg(string $label = 'A'): static
    {
        return $this->state([
            'jawaban_pg'  => [$label],
            'is_terjawab' => true,
        ]);
    }

    public function essay(string $teks = 'Jawaban essay'): static
    {
        return $this->state([
            'jawaban_teks' => $teks,
            'is_terjawab'  => true,
        ]);
    }

    public function isian(string $teks = 'jawaban'): static
    {
        return $this->state([
            'jawaban_teks' => $teks,
            'is_terjawab'  => true,
        ]);
    }

    public function menjodohkan(array $pasangan = []): static
    {
        return $this->state([
            'jawaban_pasangan' => $pasangan,
            'is_terjawab'      => true,
        ]);
    }

    public function ditandai(): static
    {
        return $this->state(['is_ditandai' => true]);
    }
}
