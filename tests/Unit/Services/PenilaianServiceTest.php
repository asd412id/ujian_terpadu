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
        $this->service = app(PenilaianService::class);
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

    /**
     * BUG FIX: Soal tanpa record jawaban tetap harus dihitung dalam totalBobot.
     * Kasus ABD. SALAM: 30 soal, hanya 1 dijawab benar → nilai seharusnya ~3.33, bukan 100.
     */
    public function test_hitung_nilai_soal_tanpa_jawaban_record_tetap_dihitung_bobot(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        // Buat 5 soal di paket, tapi hanya 1 yang ada jawaban
        $soal1 = $this->createSoalPG($paket, 'A', 1.0);
        $this->createSoalPG($paket, 'B', 1.0); // tidak dijawab, tidak ada record
        $this->createSoalPG($paket, 'C', 1.0); // tidak dijawab, tidak ada record
        $this->createSoalPG($paket, 'A', 1.0); // tidak dijawab, tidak ada record
        $this->createSoalPG($paket, 'B', 1.0); // tidak dijawab, tidak ada record

        // Hanya soal1 yang dijawab benar
        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);

        $result = $this->service->hitungNilai($sp);

        // 1 benar dari 5 soal, bobot semua 1.0 → nilai = (1/5)*100 = 20.00
        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(0, $result['jumlah_salah']);
        $this->assertEquals(4, $result['jumlah_kosong']);
        $this->assertEquals(20.00, $result['nilai_akhir']);
    }

    public function test_hitung_nilai_kosong_dengan_record_is_terjawab_false(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp = $this->createSesiPeserta($paket);

        $soal1 = $this->createSoalPG($paket, 'A', 1.0);
        $soal2 = $this->createSoalPG($paket, 'B', 1.0);
        $soal3 = $this->createSoalPG($paket, 'C', 1.0);

        // Soal 1: jawab benar
        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);
        // Soal 2: ada record tapi is_terjawab = false
        JawabanPeserta::factory()->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal2->id,
            'is_terjawab'     => false,
        ]);
        // Soal 3: tidak ada record sama sekali

        $result = $this->service->hitungNilai($sp);

        // totalBobot = 3 (semua soal di paket), nilaiBenar = 1
        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(0, $result['jumlah_salah']);
        $this->assertEquals(2, $result['jumlah_kosong']);
        $this->assertEquals(33.33, $result['nilai_akhir']); // 1/3 * 100 = 33.33
    }
}
