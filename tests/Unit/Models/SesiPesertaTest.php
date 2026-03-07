<?php

namespace Tests\Unit\Models;

use App\Models\JawabanPeserta;
use App\Models\Peserta;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SesiPesertaTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_sesi_peserta(): void
    {
        $sp = SesiPeserta::factory()->create();
        $this->assertDatabaseHas('sesi_peserta', ['id' => $sp->id]);
    }

    public function test_fillable_attributes(): void
    {
        $sp = new SesiPeserta();
        $expected = [
            'sesi_id', 'peserta_id', 'token_ujian', 'urutan_soal',
            'status', 'ip_address', 'browser_info', 'device_type',
            'mulai_at', 'submit_at', 'durasi_aktual_detik',
            'soal_terjawab', 'soal_ditandai',
            'nilai_akhir', 'nilai_benar',
            'jumlah_benar', 'jumlah_salah', 'jumlah_kosong',
        ];
        $this->assertEquals($expected, $sp->getFillable());
    }

    public function test_datetime_casts(): void
    {
        $sp = SesiPeserta::factory()->mengerjakan()->create();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $sp->mulai_at);
    }

    public function test_sesi_relationship(): void
    {
        $sesi = SesiUjian::factory()->create();
        $sp = SesiPeserta::factory()->create(['sesi_id' => $sesi->id]);

        $this->assertInstanceOf(SesiUjian::class, $sp->sesi);
    }

    public function test_peserta_relationship(): void
    {
        $peserta = Peserta::factory()->create();
        $sp = SesiPeserta::factory()->create(['peserta_id' => $peserta->id]);

        $this->assertInstanceOf(Peserta::class, $sp->peserta);
    }

    public function test_jawaban_relationship(): void
    {
        $sp = SesiPeserta::factory()->create();
        JawabanPeserta::factory()->count(5)->create(['sesi_peserta_id' => $sp->id]);

        $this->assertCount(5, $sp->jawaban);
    }

    public function test_status_submit(): void
    {
        $sp = SesiPeserta::factory()->submit()->create();
        $this->assertEquals('submit', $sp->status);
    }

    public function test_status_mengerjakan(): void
    {
        $sp = SesiPeserta::factory()->mengerjakan()->create();
        $this->assertEquals('mengerjakan', $sp->status);
    }

    public function test_status_login(): void
    {
        $sp = SesiPeserta::factory()->create(['status' => 'login']);
        $this->assertEquals('login', $sp->status);
    }

    public function test_sisa_waktu_detik_returns_positive_for_active_session(): void
    {
        $sp = SesiPeserta::factory()->mengerjakan()->create([
            'mulai_at' => now()->subMinutes(10),
        ]);
        $sesi = $sp->sesi;
        $paket = $sesi->paket;
        $paket->update(['durasi_menit' => 90]);

        $sp->refresh();
        $sisaWaktu = $sp->sisa_waktu_detik;
        $this->assertGreaterThan(0, $sisaWaktu);
    }

    public function test_sisa_waktu_detik_returns_zero_when_no_mulai(): void
    {
        $sp = SesiPeserta::factory()->create(['mulai_at' => null]);

        $this->assertEquals(0, $sp->sisa_waktu_detik);
    }
}
