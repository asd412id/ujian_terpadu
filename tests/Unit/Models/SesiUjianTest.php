<?php

namespace Tests\Unit\Models;

use App\Models\PaketUjian;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SesiUjianTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_sesi_ujian(): void
    {
        $sesi = SesiUjian::factory()->create();
        $this->assertDatabaseHas('sesi_ujian', ['id' => $sesi->id]);
    }

    public function test_fillable_attributes(): void
    {
        $sesi = new SesiUjian();
        $expected = [
            'paket_id', 'nama_sesi', 'ruangan', 'pengawas_id',
            'waktu_mulai', 'waktu_selesai', 'status', 'kapasitas',
        ];
        $this->assertEquals($expected, $sesi->getFillable());
    }

    public function test_datetime_casts(): void
    {
        $sesi = SesiUjian::factory()->create();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $sesi->waktu_mulai);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $sesi->waktu_selesai);
    }

    public function test_paket_ujian_relationship(): void
    {
        $paket = PaketUjian::factory()->create();
        $sesi = SesiUjian::factory()->create(['paket_id' => $paket->id]);

        $this->assertInstanceOf(PaketUjian::class, $sesi->paket);
        $this->assertEquals($paket->id, $sesi->paket->id);
    }

    public function test_pengawas_relationship(): void
    {
        $pengawas = User::factory()->pengawas()->create();
        $sesi = SesiUjian::factory()->create(['pengawas_id' => $pengawas->id]);

        $this->assertInstanceOf(User::class, $sesi->pengawas);
    }

    public function test_sesi_peserta_relationship(): void
    {
        $sesi = SesiUjian::factory()->create();
        SesiPeserta::factory()->count(3)->create(['sesi_id' => $sesi->id]);

        $this->assertCount(3, $sesi->sesiPeserta);
    }

    public function test_status_selesai(): void
    {
        $sesi = SesiUjian::factory()->selesai()->create();
        $this->assertEquals('selesai', $sesi->status);
    }

    public function test_status_persiapan(): void
    {
        $sesi = SesiUjian::factory()->create();
        $this->assertEquals('persiapan', $sesi->status);
    }

    public function test_status_berlangsung(): void
    {
        $sesi = SesiUjian::factory()->berlangsung()->create();
        $this->assertEquals('berlangsung', $sesi->status);
    }

    public function test_jumlah_aktif_attribute(): void
    {
        $sesi = SesiUjian::factory()->create();
        SesiPeserta::factory()->create(['sesi_id' => $sesi->id, 'status' => 'login']);
        SesiPeserta::factory()->create(['sesi_id' => $sesi->id, 'status' => 'mengerjakan']);
        SesiPeserta::factory()->create(['sesi_id' => $sesi->id, 'status' => 'submit']);

        $this->assertEquals(2, $sesi->jumlah_aktif);
    }

    public function test_jumlah_submit_attribute(): void
    {
        $sesi = SesiUjian::factory()->create();
        SesiPeserta::factory()->create(['sesi_id' => $sesi->id, 'status' => 'submit']);
        SesiPeserta::factory()->create(['sesi_id' => $sesi->id, 'status' => 'mengerjakan']);

        $this->assertEquals(1, $sesi->jumlah_submit);
    }
}
