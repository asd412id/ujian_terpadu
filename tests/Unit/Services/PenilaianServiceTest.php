<?php

namespace Tests\Unit\Services;

use App\Models\JawabanPeserta;
use App\Models\OpsiJawaban;
use App\Models\PaketSoal;
use App\Models\PaketUjian;
use App\Models\PasanganSoal;
use App\Models\Peserta;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Services\PenilaianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenilaianServiceTest extends TestCase
{
    use RefreshDatabase;

    private PenilaianService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PenilaianService();
    }

    private function createSesiPeserta(PaketUjian $paket): SesiPeserta
    {
        $sesi = SesiUjian::factory()->create(['paket_id' => $paket->id]);
        return SesiPeserta::factory()->mengerjakan()->create(['sesi_id' => $sesi->id]);
    }

    private function createSoalPG(PaketUjian $paket, string $jawabanBenar = 'A', float $bobot = 1.0): Soal
    {
        $soal = Soal::factory()->pg()->create(['bobot' => $bobot]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            OpsiJawaban::factory()->create([
                'soal_id'  => $soal->id,
                'label'    => $label,
                'is_benar' => $label === $jawabanBenar,
                'urutan'   => $i + 1,
            ]);
        }

        return $soal;
    }

    public function test_hitung_nilai_pg_semua_benar(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        $soal1 = $this->createSoalPG($paket, 'A', 1.0);
        $soal2 = $this->createSoalPG($paket, 'B', 1.0);

        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);
        JawabanPeserta::factory()->pg('B')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal2->id]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(2, $result['jumlah_benar']);
        $this->assertEquals(0, $result['jumlah_salah']);
        $this->assertEquals(100.00, $result['nilai_akhir']);
    }

    public function test_hitung_nilai_pg_semua_salah(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        $soal1 = $this->createSoalPG($paket, 'A', 1.0);
        $soal2 = $this->createSoalPG($paket, 'B', 1.0);

        JawabanPeserta::factory()->pg('C')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);
        JawabanPeserta::factory()->pg('D')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal2->id]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(0, $result['jumlah_benar']);
        $this->assertEquals(2, $result['jumlah_salah']);
        $this->assertEquals(0.0, $result['nilai_akhir']);
    }

    public function test_hitung_nilai_pg_mixed(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        $soal1 = $this->createSoalPG($paket, 'A', 1.0);
        $soal2 = $this->createSoalPG($paket, 'B', 1.0);

        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);
        JawabanPeserta::factory()->pg('C')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal2->id]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(1, $result['jumlah_salah']);
        $this->assertEquals(50.00, $result['nilai_akhir']);
    }

    public function test_hitung_nilai_with_kosong(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        $soal1 = $this->createSoalPG($paket, 'A', 1.0);
        $soal2 = $this->createSoalPG($paket, 'B', 1.0);

        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);
        JawabanPeserta::factory()->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal2->id,
            'is_terjawab'     => false,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(1, $result['jumlah_kosong']);
    }

    public function test_hitung_nilai_isian_benar(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->isian()->create(['bobot' => 1.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create([
            'soal_id' => $soal->id,
            'teks'    => 'Jakarta',
        ]);

        JawabanPeserta::factory()->isian('jakarta')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(100.00, $result['nilai_akhir']);
    }

    public function test_hitung_nilai_isian_salah(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->isian()->create(['bobot' => 1.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create([
            'soal_id' => $soal->id,
            'teks'    => 'Jakarta',
        ]);

        JawabanPeserta::factory()->isian('Bandung')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(0, $result['jumlah_benar']);
    }

    public function test_hitung_nilai_essay_skipped_auto(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->essay()->create(['bobot' => 1.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        JawabanPeserta::factory()->essay('Jawaban panjang essay...')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
        ]);

        $result = $this->service->hitungNilai($sp);

        // Essay not counted in auto-score
        $this->assertEquals(0, $result['jumlah_benar']);
        $this->assertEquals(0, $result['jumlah_salah']);
    }

    public function test_hitung_nilai_with_bobot_override(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->pg()->create(['bobot' => 1.0]);
        PaketSoal::factory()->create([
            'paket_id'       => $paket->id,
            'soal_id'        => $soal->id,
            'bobot_override' => 5.0,
        ]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'A']);

        JawabanPeserta::factory()->pg('A')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(5.0, $result['nilai_benar']);
        $this->assertEquals(100.00, $result['nilai_akhir']);
    }

    public function test_hitung_nilai_pg_kompleks_semua_benar(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->pgKompleks()->create(['bobot' => 2.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'A']);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'C']);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'B']);

        JawabanPeserta::factory()->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
            'jawaban_pg'      => ['A', 'C'],
            'is_terjawab'     => true,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(2.0, $result['nilai_benar']);
    }

    public function test_hitung_nilai_pg_kompleks_partial(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->pgKompleks()->create(['bobot' => 2.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'A']);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'C']);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'B']);

        JawabanPeserta::factory()->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
            'jawaban_pg'      => ['A'],
            'is_terjawab'     => true,
        ]);

        $result = $this->service->hitungNilai($sp);

        // 1/2 benar * 2.0 bobot * 0.5 partial = 0.50
        $this->assertEquals(0, $result['jumlah_benar']);
        $this->assertEquals(1, $result['jumlah_salah']);
    }

    public function test_status_is_submit(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        $result = $this->service->hitungNilai($sp);
        $this->assertEquals('submit', $result['status']);
    }
}
