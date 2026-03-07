<?php

namespace Tests\Unit\Models;

use App\Models\DinasPendidikan;
use App\Models\PaketUjian;
use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SekolahTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_sekolah(): void
    {
        $sekolah = Sekolah::factory()->create();
        $this->assertDatabaseHas('sekolah', ['id' => $sekolah->id]);
    }

    public function test_fillable_attributes(): void
    {
        $sekolah = new Sekolah();
        $expected = [
            'dinas_id', 'nama', 'npsn', 'jenjang',
            'alamat', 'kota', 'telepon', 'email',
            'kepala_sekolah', 'logo', 'is_active',
        ];
        $this->assertEquals($expected, $sekolah->getFillable());
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $sekolah = Sekolah::factory()->create(['is_active' => 1]);
        $this->assertIsBool($sekolah->is_active);
    }

    public function test_dinas_relationship(): void
    {
        $dinas = DinasPendidikan::factory()->create();
        $sekolah = Sekolah::factory()->create(['dinas_id' => $dinas->id]);

        $this->assertInstanceOf(DinasPendidikan::class, $sekolah->dinas);
        $this->assertEquals($dinas->id, $sekolah->dinas->id);
    }

    public function test_users_relationship(): void
    {
        $sekolah = Sekolah::factory()->create();
        User::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->assertCount(1, $sekolah->users);
    }

    public function test_peserta_relationship(): void
    {
        $sekolah = Sekolah::factory()->create();
        Peserta::factory()->count(3)->create(['sekolah_id' => $sekolah->id]);

        $this->assertCount(3, $sekolah->peserta);
    }

    public function test_paket_ujian_relationship(): void
    {
        $sekolah = Sekolah::factory()->create();
        PaketUjian::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->assertCount(1, $sekolah->paketUjian);
    }

    public function test_soal_relationship(): void
    {
        $sekolah = Sekolah::factory()->create();
        Soal::factory()->count(2)->create(['sekolah_id' => $sekolah->id]);

        $this->assertCount(2, $sekolah->soal);
    }
}
