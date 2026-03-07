<?php

namespace Tests\Unit\Models;

use App\Models\JawabanPeserta;
use App\Models\SesiPeserta;
use App\Models\Soal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JawabanPesertaTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_jawaban(): void
    {
        $jawaban = JawabanPeserta::factory()->create();
        $this->assertDatabaseHas('jawaban_peserta', ['id' => $jawaban->id]);
    }

    public function test_fillable_attributes(): void
    {
        $jawaban = new JawabanPeserta();
        $expected = [
            'sesi_peserta_id', 'soal_id',
            'jawaban_pg', 'jawaban_teks', 'jawaban_pasangan',
            'file_essay', 'is_ditandai', 'is_terjawab',
            'skor_auto', 'skor_manual', 'dinilai_oleh', 'dinilai_at',
            'catatan_penilai', 'waktu_jawab', 'durasi_jawab_detik',
            'idempotency_key',
        ];
        $this->assertEquals($expected, $jawaban->getFillable());
    }

    public function test_array_casts(): void
    {
        $jawaban = JawabanPeserta::factory()->pg('A')->create();
        $this->assertIsArray($jawaban->jawaban_pg);
        $this->assertEquals(['A'], $jawaban->jawaban_pg);
    }

    public function test_pasangan_cast(): void
    {
        $jawaban = JawabanPeserta::factory()->menjodohkan(['1' => 'A', '2' => 'B'])->create();
        $this->assertIsArray($jawaban->jawaban_pasangan);
        $this->assertEquals(['1' => 'A', '2' => 'B'], $jawaban->jawaban_pasangan);
    }

    public function test_boolean_casts(): void
    {
        $jawaban = JawabanPeserta::factory()->create(['is_ditandai' => 1, 'is_terjawab' => 0]);
        $this->assertIsBool($jawaban->is_ditandai);
        $this->assertIsBool($jawaban->is_terjawab);
    }

    public function test_sesi_peserta_relationship(): void
    {
        $sp = SesiPeserta::factory()->create();
        $jawaban = JawabanPeserta::factory()->create(['sesi_peserta_id' => $sp->id]);

        $this->assertInstanceOf(SesiPeserta::class, $jawaban->sesiPeserta);
    }

    public function test_soal_relationship(): void
    {
        $soal = Soal::factory()->create();
        $jawaban = JawabanPeserta::factory()->create(['soal_id' => $soal->id]);

        $this->assertInstanceOf(Soal::class, $jawaban->soal);
    }

    public function test_pg_factory_state(): void
    {
        $jawaban = JawabanPeserta::factory()->pg('C')->create();
        $this->assertEquals(['C'], $jawaban->jawaban_pg);
        $this->assertTrue($jawaban->is_terjawab);
    }

    public function test_essay_factory_state(): void
    {
        $jawaban = JawabanPeserta::factory()->essay('Ini jawaban saya')->create();
        $this->assertEquals('Ini jawaban saya', $jawaban->jawaban_teks);
        $this->assertTrue($jawaban->is_terjawab);
    }

    public function test_ditandai_factory_state(): void
    {
        $jawaban = JawabanPeserta::factory()->ditandai()->create();
        $this->assertTrue($jawaban->is_ditandai);
    }
}
