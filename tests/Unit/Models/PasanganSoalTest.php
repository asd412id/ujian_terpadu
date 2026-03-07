<?php

namespace Tests\Unit\Models;

use App\Models\PasanganSoal;
use App\Models\Soal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasanganSoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_pasangan(): void
    {
        $pasangan = PasanganSoal::factory()->create();
        $this->assertDatabaseHas('pasangan_soal', ['id' => $pasangan->id]);
    }

    public function test_fillable_attributes(): void
    {
        $pasangan = new PasanganSoal();
        $this->assertEquals(
            ['soal_id', 'kiri_teks', 'kiri_gambar', 'kanan_teks', 'kanan_gambar', 'urutan'],
            $pasangan->getFillable()
        );
    }

    public function test_soal_relationship(): void
    {
        $soal = Soal::factory()->menjodohkan()->create();
        $pasangan = PasanganSoal::factory()->create(['soal_id' => $soal->id]);

        $this->assertInstanceOf(Soal::class, $pasangan->soal);
        $this->assertEquals($soal->id, $pasangan->soal->id);
    }
}
