<?php

namespace Tests\Unit\Models;

use App\Models\PaketSoal;
use App\Models\PaketUjian;
use App\Models\Sekolah;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaketUjianTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_paket_ujian(): void
    {
        $paket = PaketUjian::factory()->create();
        $this->assertDatabaseHas('paket_ujian', ['id' => $paket->id]);
    }

    public function test_fillable_attributes(): void
    {
        $paket = new PaketUjian();
        $expected = [
            'sekolah_id', 'created_by', 'nama', 'kode',
            'jenis_ujian', 'jenjang', 'deskripsi', 'durasi_menit',
            'jumlah_soal', 'acak_soal', 'acak_opsi',
            'tampilkan_hasil', 'boleh_kembali', 'max_peserta',
            'tanggal_mulai', 'tanggal_selesai', 'status',
        ];
        $this->assertEquals($expected, $paket->getFillable());
    }

    public function test_boolean_casts(): void
    {
        $paket = PaketUjian::factory()->create([
            'acak_soal' => 1, 'acak_opsi' => 0,
            'tampilkan_hasil' => 1, 'boleh_kembali' => 0,
        ]);
        $this->assertIsBool($paket->acak_soal);
        $this->assertIsBool($paket->acak_opsi);
        $this->assertIsBool($paket->tampilkan_hasil);
        $this->assertIsBool($paket->boleh_kembali);
    }

    public function test_datetime_casts(): void
    {
        $paket = PaketUjian::factory()->create();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $paket->tanggal_mulai);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $paket->tanggal_selesai);
    }

    public function test_sekolah_relationship(): void
    {
        $sekolah = Sekolah::factory()->create();
        $paket = PaketUjian::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->assertInstanceOf(Sekolah::class, $paket->sekolah);
    }

    public function test_pembuat_relationship(): void
    {
        $user = User::factory()->create();
        $paket = PaketUjian::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $paket->pembuat);
    }

    public function test_paket_soal_relationship(): void
    {
        $paket = PaketUjian::factory()->create();
        PaketSoal::factory()->count(5)->create(['paket_id' => $paket->id]);

        $this->assertCount(5, $paket->paketSoal);
    }

    public function test_soal_relationship(): void
    {
        $paket = PaketUjian::factory()->create();
        $soal = Soal::factory()->create();
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        $this->assertCount(1, $paket->soal);
    }

    public function test_sesi_relationship(): void
    {
        $paket = PaketUjian::factory()->create();
        SesiUjian::factory()->create(['paket_id' => $paket->id]);

        $this->assertCount(1, $paket->sesi);
    }

    public function test_is_aktif_returns_true_when_status_aktif(): void
    {
        $paket = PaketUjian::factory()->aktif()->create();
        $this->assertTrue($paket->isAktif());
    }

    public function test_is_aktif_returns_false_when_status_not_aktif(): void
    {
        $paket = PaketUjian::factory()->create(['status' => 'draft']);
        $this->assertFalse($paket->isAktif());
    }

    public function test_soft_deletes(): void
    {
        $paket = PaketUjian::factory()->create();
        $paket->delete();

        $this->assertSoftDeleted('paket_ujian', ['id' => $paket->id]);
    }
}
