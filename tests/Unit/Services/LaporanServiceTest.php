<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\LaporanService;
use App\Repositories\LaporanRepository;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LaporanServiceTest extends TestCase
{
    protected LaporanService $service;
    protected MockInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(LaporanRepository::class);
        $this->service = new LaporanService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── getHasilUjian ──────────────────────────────────────────

    public function test_get_hasil_ujian_returns_array_with_sesi_list(): void
    {
        $this->repository->shouldReceive('getSekolahList')->once()->andReturn(new EloquentCollection());
        $this->repository->shouldReceive('getPaketList')->once()->andReturn(new EloquentCollection());
        $this->repository->shouldReceive('getHasilUjianFiltered')->once()->andReturn(new LengthAwarePaginator([], 0, 30));
        $this->repository->shouldReceive('buildRekap')->once()->andReturn([]);
        $this->repository->shouldReceive('getKelasListAll')->once()->andReturn(new Collection());
        $this->repository->shouldReceive('getSesiList')->once()->with(null)->andReturn(new EloquentCollection(['sesi-1']));

        $result = $this->service->getHasilUjian();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('sesiList', $result);
        $this->assertArrayHasKey('sekolahList', $result);
        $this->assertArrayHasKey('paketList', $result);
        $this->assertArrayHasKey('kelasList', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rekap', $result);
    }

    public function test_get_hasil_ujian_passes_paket_id_to_sesi_list(): void
    {
        $this->repository->shouldReceive('getSekolahList')->andReturn(new EloquentCollection());
        $this->repository->shouldReceive('getPaketList')->andReturn(new EloquentCollection());
        $this->repository->shouldReceive('getHasilUjianFiltered')->andReturn(new LengthAwarePaginator([], 0, 30));
        $this->repository->shouldReceive('buildRekap')->andReturn([]);
        $this->repository->shouldReceive('getKelasListAll')->andReturn(new Collection());
        $this->repository->shouldReceive('getSesiList')->once()->with('paket-123')->andReturn(new EloquentCollection());

        $result = $this->service->getHasilUjian(['paket_id' => 'paket-123']);

        $this->assertIsArray($result);
    }

    public function test_get_hasil_ujian_uses_sekolah_kelas_list_when_sekolah_filter(): void
    {
        $this->repository->shouldReceive('getSekolahList')->andReturn(new EloquentCollection());
        $this->repository->shouldReceive('getPaketList')->andReturn(new EloquentCollection());
        $this->repository->shouldReceive('getHasilUjianFiltered')->andReturn(new LengthAwarePaginator([], 0, 30));
        $this->repository->shouldReceive('buildRekap')->andReturn([]);
        $this->repository->shouldReceive('getKelasListBySekolah')->once()->with('sekolah-1')->andReturn(new Collection());
        $this->repository->shouldReceive('getSesiList')->andReturn(new EloquentCollection());

        $result = $this->service->getHasilUjian(['sekolah_id' => 'sekolah-1']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('kelasList', $result);
    }

    // ── getHasilUjianBySekolah ─────────────────────────────────

    public function test_get_hasil_ujian_by_sekolah_returns_sesi_list(): void
    {
        $sekolahId = 'sekolah-1';

        $this->repository->shouldReceive('getPaketListBySekolah')->with($sekolahId)->andReturn(new EloquentCollection());
        $this->repository->shouldReceive('getKelasListBySekolah')->with($sekolahId)->andReturn(new Collection());
        $this->repository->shouldReceive('getSesiListBySekolah')->once()->with($sekolahId, null)->andReturn(new EloquentCollection(['sesi-A']));
        $this->repository->shouldReceive('getHasilUjianFilteredBySekolah')->andReturn(new LengthAwarePaginator([], 0, 30));
        $this->repository->shouldReceive('buildRekapBySekolah')->andReturn([]);

        $result = $this->service->getHasilUjianBySekolah($sekolahId);

        $this->assertArrayHasKey('sesiList', $result);
        $this->assertCount(1, $result['sesiList']);
    }

    public function test_get_hasil_ujian_by_sekolah_passes_paket_id_to_sesi_list(): void
    {
        $sekolahId = 'sekolah-1';

        $this->repository->shouldReceive('getPaketListBySekolah')->andReturn(new EloquentCollection());
        $this->repository->shouldReceive('getKelasListBySekolah')->andReturn(new Collection());
        $this->repository->shouldReceive('getSesiListBySekolah')->once()->with($sekolahId, 'paket-abc')->andReturn(new EloquentCollection());
        $this->repository->shouldReceive('getHasilUjianFilteredBySekolah')->andReturn(new LengthAwarePaginator([], 0, 30));
        $this->repository->shouldReceive('buildRekapBySekolah')->andReturn([]);

        $result = $this->service->getHasilUjianBySekolah($sekolahId, ['paket_id' => 'paket-abc']);

        $this->assertIsArray($result);
    }

    // ── getHasilBySekolah ──────────────────────────────────────

    public function test_get_hasil_by_sekolah_delegates_to_repository(): void
    {
        $sekolahId = 'sekolah-1';
        $expected = new EloquentCollection(['item1', 'item2']);

        $this->repository
            ->shouldReceive('getHasilBySekolah')
            ->once()
            ->with($sekolahId)
            ->andReturn($expected);

        $result = $this->service->getHasilBySekolah($sekolahId);
        $this->assertEquals($expected, $result);
    }

    public function test_get_hasil_by_sekolah_returns_empty_collection(): void
    {
        $sekolahId = 'empty-sekolah';

        $this->repository
            ->shouldReceive('getHasilBySekolah')
            ->once()
            ->with($sekolahId)
            ->andReturn(new EloquentCollection([]));

        $result = $this->service->getHasilBySekolah($sekolahId);
        $this->assertCount(0, $result);
    }

    // ── getHasilByPaket ────────────────────────────────────────

    public function test_get_hasil_by_paket_delegates_to_repository(): void
    {
        $paketId = 'paket-1';
        $expected = new EloquentCollection(['result1']);

        $this->repository
            ->shouldReceive('getHasilByPaket')
            ->once()
            ->with($paketId)
            ->andReturn($expected);

        $result = $this->service->getHasilByPaket($paketId);
        $this->assertEquals($expected, $result);
    }

    public function test_get_hasil_by_paket_returns_empty_collection(): void
    {
        $paketId = 'paket-empty';

        $this->repository
            ->shouldReceive('getHasilByPaket')
            ->once()
            ->with($paketId)
            ->andReturn(new EloquentCollection([]));

        $result = $this->service->getHasilByPaket($paketId);
        $this->assertCount(0, $result);
    }

    // ── getStatistik ───────────────────────────────────────────

    public function test_get_statistik_with_paket_delegates_to_repository(): void
    {
        $paketId = 'paket-1';
        $expected = [
            'total_peserta' => 10,
            'rata_rata' => 75.5,
            'nilai_max' => 95,
            'nilai_min' => 40,
            'sangat_baik' => 2,
            'baik' => 3,
            'cukup' => 2,
            'kurang' => 2,
            'sangat_kurang' => 1,
        ];

        $this->repository
            ->shouldReceive('getStatistik')
            ->once()
            ->with($paketId)
            ->andReturn($expected);

        $result = $this->service->getStatistik($paketId);
        $this->assertEquals($expected, $result);
    }

    public function test_get_statistik_without_paket_uses_global(): void
    {
        $expected = ['total_peserta' => 100];

        $this->repository
            ->shouldReceive('getGlobalStatistik')
            ->once()
            ->andReturn($expected);

        $result = $this->service->getStatistik(null);
        $this->assertEquals($expected, $result);
    }

    // ── getDetailNilaiPeserta ──────────────────────────────────

    public function test_get_detail_nilai_peserta_delegates_to_repository(): void
    {
        $pesertaId = 'peserta-1';
        $expected = Mockery::mock(\App\Models\SesiPeserta::class);

        $this->repository
            ->shouldReceive('getDetailNilaiPeserta')
            ->once()
            ->with($pesertaId)
            ->andReturn($expected);

        $result = $this->service->getDetailNilaiPeserta($pesertaId);
        $this->assertEquals($expected, $result);
    }

    public function test_get_detail_nilai_peserta_returns_null_when_not_found(): void
    {
        $pesertaId = 'non-existent';

        $this->repository
            ->shouldReceive('getDetailNilaiPeserta')
            ->once()
            ->with($pesertaId)
            ->andReturn(null);

        $result = $this->service->getDetailNilaiPeserta($pesertaId);
        $this->assertNull($result);
    }

    // ── exportHasil ────────────────────────────────────────────

    public function test_export_hasil_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'exportHasil');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    public function test_export_hasil_accepts_optional_filters(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'exportHasil');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('filters', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertEquals([], $params[0]->getDefaultValue());
    }

    // ── exportHasilBySekolah ───────────────────────────────────

    public function test_export_hasil_by_sekolah_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'exportHasilBySekolah');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    public function test_export_hasil_by_sekolah_has_correct_signature(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'exportHasilBySekolah');
        $params = $reflection->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('sekolahId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
        $this->assertEquals('filters', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertEquals([], $params[1]->getDefaultValue());
    }

    public function test_export_hasil_by_sekolah_calls_sekolah_scoped_methods(): void
    {
        $sekolahId = 'sekolah-1';
        $filters = ['paket_id' => 'paket-1'];

        $this->repository->shouldReceive('chunkHasilForExportBySekolah')
            ->once()
            ->with($sekolahId, Mockery::type('array'), 500, Mockery::type('callable'));

        $this->repository->shouldReceive('findPaketName')
            ->once()->with('paket-1')->andReturn('Ujian Test');

        $this->repository->shouldReceive('findSekolahName')
            ->once()->with($sekolahId)->andReturn('SMA Test');

        $this->repository->shouldReceive('getExportSesiPesertaIdsBySekolah')
            ->once()->with($sekolahId, Mockery::type('array'))->andReturn(new Collection());

        $this->repository->shouldReceive('buildPerSoalAnalysis')
            ->once()->andReturn([]);

        $this->repository->shouldReceive('buildRekapBySekolah')
            ->once()->with($sekolahId, Mockery::type('array'))->andReturn([]);

        $result = $this->service->exportHasilBySekolah($sekolahId, $filters);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('hasil', $result);
        $this->assertArrayHasKey('perSoal', $result);
        $this->assertArrayHasKey('rekap', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertEquals('SMA Test', $result['filters']['sekolah_nama']);
    }
}
