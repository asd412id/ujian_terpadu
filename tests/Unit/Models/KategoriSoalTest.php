<?php

namespace Tests\Unit\Models;

use App\Models\KategoriSoal;
use App\Models\Soal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KategoriSoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_kategori(): void
    {
        $kategori = KategoriSoal::factory()->create();
        $this->assertDatabaseHas('kategori_soal', ['id' => $kategori->id]);
    }

    public function test_fillable_attributes(): void
    {
        $kategori = new KategoriSoal();
        $this->assertEquals(
            ['nama', 'kode', 'jenjang', 'kelompok', 'kurikulum', 'urutan', 'is_active'],
            $kategori->getFillable()
        );
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $kategori = KategoriSoal::factory()->create(['is_active' => 1]);
        $this->assertIsBool($kategori->is_active);
        $this->assertTrue($kategori->is_active);
    }

    public function test_soal_relationship(): void
    {
        $kategori = KategoriSoal::factory()->create();
        Soal::factory()->count(3)->create(['kategori_id' => $kategori->id]);

        $this->assertCount(3, $kategori->soal);
        $this->assertInstanceOf(Soal::class, $kategori->soal->first());
    }
}
