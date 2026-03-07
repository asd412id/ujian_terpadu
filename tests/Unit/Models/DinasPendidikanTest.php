<?php

namespace Tests\Unit\Models;

use App\Models\DinasPendidikan;
use App\Models\Sekolah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DinasPendidikanTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_dinas(): void
    {
        $dinas = DinasPendidikan::factory()->create();
        $this->assertDatabaseHas('dinas_pendidikan', ['id' => $dinas->id]);
    }

    public function test_fillable_attributes(): void
    {
        $dinas = new DinasPendidikan();
        $expected = [
            'nama', 'kode_wilayah', 'kota', 'provinsi',
            'alamat', 'telepon', 'email', 'kepala_dinas',
            'logo', 'is_active',
        ];
        $this->assertEquals($expected, $dinas->getFillable());
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $dinas = DinasPendidikan::factory()->create(['is_active' => 1]);
        $this->assertIsBool($dinas->is_active);
        $this->assertTrue($dinas->is_active);
    }

    public function test_sekolah_relationship(): void
    {
        $dinas = DinasPendidikan::factory()->create();
        Sekolah::factory()->count(3)->create(['dinas_id' => $dinas->id]);

        $this->assertCount(3, $dinas->sekolah);
        $this->assertInstanceOf(Sekolah::class, $dinas->sekolah->first());
    }
}
