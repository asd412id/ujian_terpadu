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

    // =========================================================================
    // HELPERS
    // =========================================================================

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

    // =========================================================================
    // PILIHAN GANDA (PG)
    // =========================================================================

    public function test_pg_semua_benar(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal1 = $this->createSoalPG($paket, 'A');
        $soal2 = $this->createSoalPG($paket, 'B');

        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);
        JawabanPeserta::factory()->pg('B')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal2->id]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(2, $result['jumlah_benar']);
        $this->assertEquals(0, $result['jumlah_salah']);
        $this->assertEquals(0, $result['jumlah_kosong']);
        $this->assertEquals(100.00, $result['nilai_akhir']);
    }

    public function test_pg_semua_salah(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal1 = $this->createSoalPG($paket, 'A');
        $soal2 = $this->createSoalPG($paket, 'B');

        JawabanPeserta::factory()->pg('C')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);
        JawabanPeserta::factory()->pg('D')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal2->id]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(0, $result['jumlah_benar']);
        $this->assertEquals(2, $result['jumlah_salah']);
        $this->assertEquals(0.0, $result['nilai_akhir']);
    }

    public function test_pg_mixed_benar_salah(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal1 = $this->createSoalPG($paket, 'A');
        $soal2 = $this->createSoalPG($paket, 'B');

        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);
        JawabanPeserta::factory()->pg('C')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal2->id]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(1, $result['jumlah_salah']);
        $this->assertEquals(50.00, $result['nilai_akhir']);
    }

    public function test_pg_dengan_jawaban_kosong(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal1 = $this->createSoalPG($paket, 'A');
        $soal2 = $this->createSoalPG($paket, 'B');

        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);
        // soal2 ada record tapi is_terjawab = false (tidak dijawab)
        JawabanPeserta::factory()->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal2->id,
            'is_terjawab'     => false,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(0, $result['jumlah_salah']);
        $this->assertEquals(1, $result['jumlah_kosong']);
        $this->assertEquals(50.00, $result['nilai_akhir']);
    }

    public function test_pg_dengan_bobot_override(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->pg()->create(['bobot' => 1.0]);
        PaketSoal::factory()->create([
            'paket_id'       => $paket->id,
            'soal_id'        => $soal->id,
            'bobot_override' => 5.0,
        ]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'A']);

        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal->id]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(5.0, $result['nilai_benar']);
        $this->assertEquals(100.00, $result['nilai_akhir']);
    }

    // =========================================================================
    // PILIHAN GANDA KOMPLEKS (PGK)
    // =========================================================================

    public function test_pgk_semua_pilihan_benar(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->pgKompleks()->create(['bobot' => 2.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'A']);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'C']);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'B', 'is_benar' => false]);

        JawabanPeserta::factory()->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
            'jawaban_pg'      => ['A', 'C'],
            'is_terjawab'     => true,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(0, $result['jumlah_salah']);
        $this->assertEquals(2.0, $result['nilai_benar']);
        $this->assertEquals(100.00, $result['nilai_akhir']);
    }

    public function test_pgk_partial_tidak_dihitung_sebagai_salah(): void
    {
        // Bug fix: partial credit (subset benar) tidak boleh increment jumlah_salah
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->pgKompleks()->create(['bobot' => 2.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'A']);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'C']);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'B', 'is_benar' => false]);

        // Hanya pilih A (subset dari [A,C]) → partial credit, jumlah_pilihan ≤ totalBenar
        JawabanPeserta::factory()->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
            'jawaban_pg'      => ['A'],
            'is_terjawab'     => true,
        ]);

        $result = $this->service->hitungNilai($sp);

        // 1/2 benar * 2.0 bobot * 0.5 = 0.50 partial credit
        $this->assertEquals(0.50, $result['nilai_benar']);
        // Partial credit bukan salah dan bukan benar penuh
        $this->assertEquals(0, $result['jumlah_benar']);
        $this->assertEquals(0, $result['jumlah_salah']);
        // jumlah_kosong menyerap partial (totalSoal - benar - salah = 1 - 0 - 0 = 1)
        $this->assertEquals(1, $result['jumlah_kosong']);
    }

    public function test_pgk_partial_skor_proporsional(): void
    {
        // 3 jawaban benar, peserta pilih 2 → (2/3) * bobot * 0.5
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->pgKompleks()->create(['bobot' => 6.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'A']);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'B']);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'C']);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'D', 'is_benar' => false]);

        JawabanPeserta::factory()->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
            'jawaban_pg'      => ['A', 'B'], // 2 dari 3 benar
            'is_terjawab'     => true,
        ]);

        $result = $this->service->hitungNilai($sp);

        // (2/3) * 6.0 * 0.5 = 2.0
        $this->assertEquals(2.0, $result['nilai_benar']);
        $this->assertEquals(0, $result['jumlah_benar']);
        $this->assertEquals(0, $result['jumlah_salah']);
    }

    public function test_pgk_dengan_jawaban_extra_skor_nol(): void
    {
        // Pilih lebih banyak dari jumlah benar → tidak dapat partial credit
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->pgKompleks()->create(['bobot' => 2.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'A']);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'C']);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'B', 'is_benar' => false]);

        // Pilih A, B, C (3 pilihan > 2 benar) → 0
        JawabanPeserta::factory()->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
            'jawaban_pg'      => ['A', 'B', 'C'],
            'is_terjawab'     => true,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(0.0, $result['nilai_benar']);
        $this->assertEquals(0, $result['jumlah_benar']);
        $this->assertEquals(1, $result['jumlah_salah']);
    }

    public function test_pgk_semua_salah(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->pgKompleks()->create(['bobot' => 2.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'A']);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'C']);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'B', 'is_benar' => false]);

        // Pilih B (salah semua)
        JawabanPeserta::factory()->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
            'jawaban_pg'      => ['B'],
            'is_terjawab'     => true,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(0.0, $result['nilai_benar']);
        $this->assertEquals(1, $result['jumlah_salah']);
    }

    // =========================================================================
    // BENAR-SALAH (BS)
    // =========================================================================

    public function test_benar_salah_semua_pernyataan_benar(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->benarSalah()->create(['bobot' => 4.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        // 4 pernyataan: A=benar, B=salah, C=benar, D=salah (is_benar di DB)
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'A', 'is_benar' => true]);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'B', 'is_benar' => false]);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'C', 'is_benar' => true]);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'D', 'is_benar' => false]);

        // Peserta menjawab sesuai kunci
        JawabanPeserta::factory()->benarSalah([
            'A' => 'benar',
            'B' => 'salah',
            'C' => 'benar',
            'D' => 'salah',
        ])->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal->id]);

        $result = $this->service->hitungNilai($sp);

        // 4/4 benar → skor = 4.0
        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(4.0, $result['nilai_benar']);
        $this->assertEquals(100.00, $result['nilai_akhir']);
    }

    public function test_benar_salah_sebagian_benar_proporsional(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->benarSalah()->create(['bobot' => 4.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'A', 'is_benar' => true]);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'B', 'is_benar' => false]);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'C', 'is_benar' => true]);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'D', 'is_benar' => false]);

        // Peserta benar 2 dari 4 (A dan B salah, C dan D benar)
        JawabanPeserta::factory()->benarSalah([
            'A' => 'salah',  // salah (kunci: benar)
            'B' => 'salah',  // benar
            'C' => 'benar',  // benar
            'D' => 'salah',  // benar
        ])->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal->id]);

        $result = $this->service->hitungNilai($sp);

        // 3/4 benar → (3/4) * 4.0 = 3.0
        $this->assertEquals(3.0, $result['nilai_benar']);
        $this->assertGreaterThan(0, $result['nilai_akhir']);
    }

    public function test_benar_salah_semua_pernyataan_salah(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->benarSalah()->create(['bobot' => 4.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'A', 'is_benar' => true]);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'B', 'is_benar' => false]);

        // Peserta menjawab kebalikan semua
        JawabanPeserta::factory()->benarSalah([
            'A' => 'salah',  // harusnya benar
            'B' => 'benar',  // harusnya salah
        ])->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal->id]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(0.0, $result['nilai_benar']);
        $this->assertEquals(1, $result['jumlah_salah']);
    }

    public function test_benar_salah_setengah_benar(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->benarSalah()->create(['bobot' => 2.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'A', 'is_benar' => true]);
        OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'B', 'is_benar' => true]);

        // Peserta hanya benar 1 dari 2
        JawabanPeserta::factory()->benarSalah([
            'A' => 'benar',  // benar
            'B' => 'salah',  // salah (kunci: benar)
        ])->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal->id]);

        $result = $this->service->hitungNilai($sp);

        // (1/2) * 2.0 = 1.0
        $this->assertEquals(1.0, $result['nilai_benar']);
        $this->assertEquals(50.00, $result['nilai_akhir']);
    }

    // =========================================================================
    // MENJODOHKAN
    // =========================================================================

    public function test_menjodohkan_semua_pasangan_benar(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->menjodohkan()->create(['bobot' => 3.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        $p1 = PasanganSoal::factory()->create(['soal_id' => $soal->id]);
        $p2 = PasanganSoal::factory()->create(['soal_id' => $soal->id]);
        $p3 = PasanganSoal::factory()->create(['soal_id' => $soal->id]);

        // Semua pasangan dijodohkan dengan benar (kiri_id === kanan_id berarti pasangan sama)
        JawabanPeserta::factory()->menjodohkan([
            [$p1->id, $p1->id],
            [$p2->id, $p2->id],
            [$p3->id, $p3->id],
        ])->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal->id]);

        $result = $this->service->hitungNilai($sp);

        // 3/3 = 3.0 skor
        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(3.0, $result['nilai_benar']);
        $this->assertEquals(100.00, $result['nilai_akhir']);
    }

    public function test_menjodohkan_sebagian_benar_proporsional(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->menjodohkan()->create(['bobot' => 4.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        $p1 = PasanganSoal::factory()->create(['soal_id' => $soal->id]);
        $p2 = PasanganSoal::factory()->create(['soal_id' => $soal->id]);
        $p3 = PasanganSoal::factory()->create(['soal_id' => $soal->id]);
        $p4 = PasanganSoal::factory()->create(['soal_id' => $soal->id]);

        // p1, p2 benar; p3 ditukar dengan p4 (salah)
        JawabanPeserta::factory()->menjodohkan([
            [$p1->id, $p1->id],  // benar
            [$p2->id, $p2->id],  // benar
            [$p3->id, $p4->id],  // salah (kiri ≠ kanan)
            [$p4->id, $p3->id],  // salah (kiri ≠ kanan)
        ])->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal->id]);

        $result = $this->service->hitungNilai($sp);

        // 2/4 = 0.5 * 4.0 = 2.0
        $this->assertEquals(2.0, $result['nilai_benar']);
        $this->assertEquals(50.00, $result['nilai_akhir']);
    }

    public function test_menjodohkan_semua_salah(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->menjodohkan()->create(['bobot' => 2.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        $p1 = PasanganSoal::factory()->create(['soal_id' => $soal->id]);
        $p2 = PasanganSoal::factory()->create(['soal_id' => $soal->id]);

        // Semua salah (ditukar)
        JawabanPeserta::factory()->menjodohkan([
            [$p1->id, $p2->id],  // salah
            [$p2->id, $p1->id],  // salah
        ])->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal->id]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(0.0, $result['nilai_benar']);
        $this->assertEquals(1, $result['jumlah_salah']);
        $this->assertEquals(0.0, $result['nilai_akhir']);
    }

    public function test_menjodohkan_satu_pasangan(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->menjodohkan()->create(['bobot' => 1.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        $p1 = PasanganSoal::factory()->create(['soal_id' => $soal->id]);

        JawabanPeserta::factory()->menjodohkan([
            [$p1->id, $p1->id],
        ])->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal->id]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1.0, $result['nilai_benar']);
        $this->assertEquals(100.00, $result['nilai_akhir']);
    }

    // =========================================================================
    // ISIAN SINGKAT
    // =========================================================================

    public function test_isian_jawaban_benar_exact_match(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->isian()->create(['bobot' => 1.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'teks' => 'Jakarta']);

        JawabanPeserta::factory()->isian('Jakarta')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(100.00, $result['nilai_akhir']);
    }

    public function test_isian_jawaban_benar_case_insensitive(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->isian()->create(['bobot' => 1.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'teks' => 'Jakarta']);

        JawabanPeserta::factory()->isian('jakarta')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(100.00, $result['nilai_akhir']);
    }

    public function test_isian_jawaban_salah(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->isian()->create(['bobot' => 1.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'teks' => 'Jakarta']);

        JawabanPeserta::factory()->isian('Bandung')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(0, $result['jumlah_benar']);
        $this->assertEquals(1, $result['jumlah_salah']);
        $this->assertEquals(0.0, $result['nilai_akhir']);
    }

    public function test_isian_jawaban_dengan_spasi_trim(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->isian()->create(['bobot' => 1.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'teks' => 'Jakarta']);

        JawabanPeserta::factory()->isian('  jakarta  ')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
    }

    // =========================================================================
    // ESSAY
    // =========================================================================

    public function test_essay_auto_skor_nol_meski_dijawab(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->essay()->create(['bobot' => 10.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        JawabanPeserta::factory()->essay('Jawaban panjang essay...')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
            'skor_manual'     => null,
        ]);

        $result = $this->service->hitungNilai($sp);

        // Essay tanpa skor_manual tidak masuk hitungan
        $this->assertEquals(0, $result['jumlah_benar']);
        $this->assertEquals(0, $result['jumlah_salah']);
        $this->assertEquals(0.0, $result['nilai_benar']);
    }

    public function test_essay_dengan_skor_manual_dihitung_dalam_nilai(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->essay()->create(['bobot' => 10.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        JawabanPeserta::factory()->essay('Jawaban essay peserta')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
            'skor_manual'     => 8.0,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(8.0, $result['nilai_benar']);
        $this->assertEquals(80.00, $result['nilai_akhir']); // 8/10 * 100
    }

    public function test_essay_skor_manual_nol_dihitung_salah(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->essay()->create(['bobot' => 10.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        JawabanPeserta::factory()->essay('Jawaban essay peserta')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
            'skor_manual'     => 0,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(0, $result['jumlah_benar']);
        $this->assertEquals(1, $result['jumlah_salah']);
        $this->assertEquals(0.0, $result['nilai_akhir']);
    }

    // =========================================================================
    // MIXED TIPE SOAL
    // =========================================================================

    public function test_mixed_pg_isian_essay_dalam_satu_paket(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        // Soal 1: PG bobot 2 → benar
        $soalPG = Soal::factory()->pg()->create(['bobot' => 2.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soalPG->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soalPG->id, 'label' => 'A']);
        OpsiJawaban::factory()->create(['soal_id' => $soalPG->id, 'label' => 'B']);
        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soalPG->id]);

        // Soal 2: Isian bobot 3 → benar
        $soalIsian = Soal::factory()->isian()->create(['bobot' => 3.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soalIsian->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soalIsian->id, 'teks' => 'Pancasila']);
        JawabanPeserta::factory()->isian('pancasila')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soalIsian->id]);

        // Soal 3: Essay bobot 5 → belum dinilai
        $soalEssay = Soal::factory()->essay()->create(['bobot' => 5.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soalEssay->id]);
        JawabanPeserta::factory()->essay('Jawaban essay')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soalEssay->id]);

        $result = $this->service->hitungNilai($sp);

        // totalBobot = 2 + 3 + 5 = 10, nilaiBenar = 2 + 3 = 5
        $this->assertEquals(2, $result['jumlah_benar']); // PG + isian
        $this->assertEquals(0, $result['jumlah_salah']);
        $this->assertEquals(5.0, $result['nilai_benar']);
        $this->assertEquals(50.00, $result['nilai_akhir']); // 5/10 * 100
    }

    // =========================================================================
    // FORMULA & EDGE CASES
    // =========================================================================

    public function test_hasil_status_selalu_submit(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals('submit', $result['status']);
    }

    public function test_soal_tanpa_record_jawaban_tetap_masuk_total_bobot(): void
    {
        // Bug fix test: soal yang tidak punya record jawaban sama sekali
        // tetap harus dihitung dalam totalBobot → nilai tidak artifisial 100
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal1 = $this->createSoalPG($paket, 'A', 1.0);
        $this->createSoalPG($paket, 'B', 1.0); // tidak dijawab, tidak ada record
        $this->createSoalPG($paket, 'C', 1.0); // tidak dijawab, tidak ada record
        $this->createSoalPG($paket, 'A', 1.0); // tidak dijawab, tidak ada record
        $this->createSoalPG($paket, 'B', 1.0); // tidak dijawab, tidak ada record

        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);

        $result = $this->service->hitungNilai($sp);

        // 1/5 * 100 = 20.00, bukan 100.00
        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(0, $result['jumlah_salah']);
        $this->assertEquals(4, $result['jumlah_kosong']);
        $this->assertEquals(20.00, $result['nilai_akhir']);
    }

    public function test_jumlah_kosong_dengan_record_is_terjawab_false(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal1 = $this->createSoalPG($paket, 'A', 1.0);
        $soal2 = $this->createSoalPG($paket, 'B', 1.0);
        $soal3 = $this->createSoalPG($paket, 'C', 1.0);

        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);
        JawabanPeserta::factory()->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal2->id,
            'is_terjawab'     => false,
        ]);
        // soal3 tidak ada record sama sekali

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(0, $result['jumlah_salah']);
        $this->assertEquals(2, $result['jumlah_kosong']);
        $this->assertEquals(33.33, $result['nilai_akhir']);
    }

    public function test_nilai_akhir_nol_jika_total_bobot_nol(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        // Buat soal tanpa bobot (paket kosong = totalBobot 0)
        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(0, $result['nilai_akhir']);
    }

    public function test_nilai_akhir_dibulatkan_dua_desimal(): void
    {
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        // 1 dari 3 soal → 1/3 * 100 = 33.333... → dibulatkan 33.33
        $soal1 = $this->createSoalPG($paket, 'A', 1.0);
        $this->createSoalPG($paket, 'B', 1.0);
        $this->createSoalPG($paket, 'C', 1.0);

        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal1->id]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(33.33, $result['nilai_akhir']);
    }

    public function test_nilai_akhir_tidak_melebihi_100_meski_skor_manual_melebihi_bobot(): void
    {
        // Essay skor_manual boleh diinput lebih dari bobot (human error penilai).
        // nilai_akhir harus tetap di-cap 100.
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        $soal = Soal::factory()->essay()->create(['bobot' => 10.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        JawabanPeserta::factory()->essay('Jawaban sangat baik')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
            'skor_manual'     => 15.0, // melebihi bobot 10
        ]);

        $result = $this->service->hitungNilai($sp);

        // (15/10) * 100 = 150, tapi harus di-cap 100
        $this->assertEquals(100.0, $result['nilai_akhir']);
    }

    public function test_nilai_akhir_tepat_100_saat_semua_benar(): void
    {
        // Pastikan nilai 100 persis (bukan 100.0001 karena floating point)
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        foreach (range(1, 5) as $i) {
            $soal = $this->createSoalPG($paket, 'A', 1.0);
            JawabanPeserta::factory()->pg('A')->create([
                'sesi_peserta_id' => $sp->id,
                'soal_id'         => $soal->id,
            ]);
        }

        $result = $this->service->hitungNilai($sp);

        $this->assertSame(100.0, $result['nilai_akhir']);
        $this->assertLessThanOrEqual(100.0, $result['nilai_akhir']);
    }

    public function test_nilai_akhir_tidak_melebihi_100_mixed_essay_skor_manual_besar(): void
    {
        // Mixed: PG normal + essay skor_manual >> bobot → total nilaiBenar > totalBobot → cap 100
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket);

        // PG bobot 5, benar
        $soalPG = Soal::factory()->pg()->create(['bobot' => 5.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soalPG->id]);
        OpsiJawaban::factory()->benar()->create(['soal_id' => $soalPG->id, 'label' => 'A']);
        OpsiJawaban::factory()->create(['soal_id' => $soalPG->id, 'label' => 'B']);
        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soalPG->id]);

        // Essay bobot 5, skor_manual 20 (jauh melebihi bobot)
        $soalEssay = Soal::factory()->essay()->create(['bobot' => 5.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soalEssay->id]);
        JawabanPeserta::factory()->essay('Jawaban')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soalEssay->id,
            'skor_manual'     => 20.0,
        ]);

        $result = $this->service->hitungNilai($sp);

        // totalBobot=10, nilaiBenar=25 → (25/10)*100=250 → cap 100
        $this->assertEquals(100.0, $result['nilai_akhir']);
        $this->assertLessThanOrEqual(100.0, $result['nilai_akhir']);
    }

    // =========================================================================
    // LABEL REMAP (OPSI DIACAK)
    // =========================================================================

    public function test_pg_dengan_urutan_opsi_diacak_tetap_dinilai_benar(): void
    {
        // Simulasi: opsi soal diacak saat ujian
        // DB: A=benar, B=salah, C=salah, D=salah
        // Setelah diacak, urutan baru: [opsi_C_id, opsi_A_id, opsi_D_id, opsi_B_id]
        //   → display A = opsi_C, display B = opsi_A, display C = opsi_D, display D = opsi_B
        // Peserta pilih display "B" → memetakan ke opsi_A → benar!
        $paket = PaketUjian::factory()->create();
        $sesi  = SesiUjian::factory()->create(['paket_id' => $paket->id]);

        $soal = Soal::factory()->pg()->create(['bobot' => 1.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        $opsiA = OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'A']);
        $opsiB = OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'B']);
        $opsiC = OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'C']);
        $opsiD = OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'D']);

        // Urutan tampil: C, A, D, B → display A→C, B→A, C→D, D→B
        $sp = SesiPeserta::factory()->mengerjakan()->create([
            'sesi_id'    => $sesi->id,
            'urutan_opsi' => [$soal->id => [$opsiC->id, $opsiA->id, $opsiD->id, $opsiB->id]],
        ]);

        // Peserta menjawab "B" (display) yang setelah remap = opsi A (benar)
        JawabanPeserta::factory()->pg('B')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
        $this->assertEquals(100.00, $result['nilai_akhir']);
    }

    public function test_pg_dengan_urutan_opsi_diacak_jawaban_salah_tetap_dinilai_salah(): void
    {
        $paket = PaketUjian::factory()->create();
        $sesi  = SesiUjian::factory()->create(['paket_id' => $paket->id]);

        $soal  = Soal::factory()->pg()->create(['bobot' => 1.0]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        $opsiA = OpsiJawaban::factory()->benar()->create(['soal_id' => $soal->id, 'label' => 'A']);
        $opsiB = OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'B']);
        $opsiC = OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'C']);
        $opsiD = OpsiJawaban::factory()->create(['soal_id' => $soal->id, 'label' => 'D']);

        // Display A→C, B→A, C→D, D→B
        $sp = SesiPeserta::factory()->mengerjakan()->create([
            'sesi_id'     => $sesi->id,
            'urutan_opsi' => [$soal->id => [$opsiC->id, $opsiA->id, $opsiD->id, $opsiB->id]],
        ]);

        // Peserta menjawab "A" (display) yang setelah remap = opsi C (salah)
        JawabanPeserta::factory()->pg('A')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soal->id,
        ]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(0, $result['jumlah_benar']);
        $this->assertEquals(1, $result['jumlah_salah']);
        $this->assertEquals(0.0, $result['nilai_akhir']);
    }

    public function test_tanpa_urutan_opsi_label_tidak_diremap(): void
    {
        // Tanpa urutan_opsi, label display = label asli (tidak ada remap)
        $paket = PaketUjian::factory()->create();
        $sp    = $this->createSesiPeserta($paket); // urutan_opsi = null

        $soal = $this->createSoalPG($paket, 'A', 1.0);
        JawabanPeserta::factory()->pg('A')->create(['sesi_peserta_id' => $sp->id, 'soal_id' => $soal->id]);

        $result = $this->service->hitungNilai($sp);

        $this->assertEquals(1, $result['jumlah_benar']);
    }
}
