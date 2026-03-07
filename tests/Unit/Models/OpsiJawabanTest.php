<?php

namespace Tests\Unit\Models;

use App\Models\OpsiJawaban;
use App\Models\Soal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsiJawabanTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_opsi_jawaban(): void
    {
        $opsi = OpsiJawaban::factory()->create();
        $this->assertDatabaseHas('opsi_jawaban', ['id' => $opsi->id]);
    }

    public function test_fillable_attributes(): void
    {
        $opsi = new OpsiJawaban();
        $this->assertEquals(
            ['soal_id', 'label', 'teks', 'gambar', 'is_benar', 'urutan'],
            $opsi->getFillable()
        );
    }

    public function test_is_benar_cast_to_boolean(): void
    {
        $opsi = OpsiJawaban::factory()->create(['is_benar' => 1]);
        $this->assertIsBool($opsi->is_benar);
        $this->assertTrue($opsi->is_benar);
    }

    public function test_soal_relationship(): void
    {
        $soal = Soal::factory()->create();
        $opsi = OpsiJawaban::factory()->create(['soal_id' => $soal->id]);

        $this->assertInstanceOf(Soal::class, $opsi->soal);
        $this->assertEquals($soal->id, $opsi->soal->id);
    }

    public function test_benar_factory_state(): void
    {
        $opsi = OpsiJawaban::factory()->benar()->create();
        $this->assertTrue($opsi->is_benar);
    }
}
