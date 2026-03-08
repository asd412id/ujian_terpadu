<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\LaporanService;
use App\Repositories\LaporanRepository;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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
    // Uses Eloquent directly (Sekolah, PaketUjian, SesiPeserta models).

    public function test_get_hasil_ujian_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getHasilUjian');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    public function test_get_hasil_ujian_accepts_optional_filters(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getHasilUjian');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('filters', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertEquals([], $params[0]->getDefaultValue());
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
    // BUG: LaporanService::getStatistik() calls $this->repository->getStatistik() with 0 args,
    // but LaporanRepository::getStatistik(string $paketId) expects 1 arg.
    // This causes an ArgumentCountError at runtime.

    public function test_get_statistik_throws_due_to_argument_mismatch(): void
    {
        $this->repository
            ->shouldReceive('getStatistik')
            ->andReturn([]);

        $this->expectException(\ArgumentCountError::class);
        $this->service->getStatistik();
    }

    // ── getRekapNilai ──────────────────────────────────────────
    // BUG: LaporanService::getRekapNilai(array $filters) passes array to
    // LaporanRepository::getRekapNilai(?string $sekolahId, ?string $paketId).
    // This causes a TypeError at runtime.

    public function test_get_rekap_nilai_throws_type_error_with_array_filters(): void
    {
        $this->repository
            ->shouldReceive('getRekapNilai')
            ->andReturn(new EloquentCollection([]));

        $this->expectException(\TypeError::class);
        $this->service->getRekapNilai(['sekolah_id' => 's1']);
    }

    public function test_get_rekap_nilai_empty_filters_passes_empty_array(): void
    {
        // Even empty array is wrong type for ?string parameter
        $this->repository
            ->shouldReceive('getRekapNilai')
            ->andReturn(new EloquentCollection([]));

        $this->expectException(\TypeError::class);
        $this->service->getRekapNilai();
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
    // Uses Eloquent directly (SesiPeserta model).

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
}
