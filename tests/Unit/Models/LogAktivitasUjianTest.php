<?php

namespace Tests\Unit\Models;

use App\Models\LogAktivitasUjian;
use App\Models\SesiPeserta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogAktivitasUjianTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_log(): void
    {
        $log = LogAktivitasUjian::factory()->create();
        $this->assertDatabaseHas('log_aktivitas_ujian', ['id' => $log->id]);
    }

    public function test_fillable_attributes(): void
    {
        $log = new LogAktivitasUjian();
        $expected = [
            'sesi_peserta_id', 'tipe_event', 'detail', 'ip_address', 'created_at',
        ];
        $this->assertEquals($expected, $log->getFillable());
    }

    public function test_detail_cast_to_array(): void
    {
        $log = LogAktivitasUjian::factory()->create(['detail' => ['soal' => 5, 'action' => 'jawab']]);
        $this->assertIsArray($log->detail);
        $this->assertEquals(5, $log->detail['soal']);
    }

    public function test_sesi_peserta_relationship(): void
    {
        $sp = SesiPeserta::factory()->create();
        $log = LogAktivitasUjian::factory()->create(['sesi_peserta_id' => $sp->id]);

        $this->assertInstanceOf(SesiPeserta::class, $log->sesiPeserta);
        $this->assertEquals($sp->id, $log->sesiPeserta->id);
    }
}
