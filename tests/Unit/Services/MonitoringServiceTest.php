<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\MonitoringService;
use App\Repositories\MonitoringRepository;

class MonitoringServiceTest extends TestCase
{
    protected MonitoringService $service;
    protected MockInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(MonitoringRepository::class);
        $this->service = new MonitoringService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── getStatistik ───────────────────────────────────────────

    public function test_get_statistik_delegates_to_repository(): void
    {
        $expected = [
            'total_sesi' => 5,
            'peserta_online' => 20,
            'peserta_ragu' => 0,
            'sudah_submit' => 15,
        ];

        $this->repository
            ->shouldReceive('getStatistik')
            ->once()
            ->withNoArgs()
            ->andReturn($expected);

        $result = $this->service->getStatistik();

        $this->assertEquals($expected, $result);
    }

    public function test_get_statistik_returns_array(): void
    {
        $this->repository
            ->shouldReceive('getStatistik')
            ->once()
            ->andReturn(['total_sesi' => 0]);

        $result = $this->service->getStatistik();

        $this->assertIsArray($result);
    }

    // ── getDashboardMonitoring ─────────────────────────────────
    // Uses Eloquent (Sekolah, SesiUjian, SesiPeserta) directly — not through repository.
    // Structural verification only.

    public function test_get_dashboard_monitoring_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getDashboardMonitoring');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    // ── getSesiAktif ───────────────────────────────────────────
    // Uses Eloquent (SesiUjian) directly.

    public function test_get_sesi_aktif_accepts_filters_parameter(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getSesiAktif');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('filters', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertEquals([], $params[0]->getDefaultValue());
    }

    // ── getPesertaStatus ───────────────────────────────────────
    // Uses Eloquent (SesiUjian, LogAktivitasUjian) directly.

    public function test_get_peserta_status_accepts_sesi_id(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getPesertaStatus');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('sesiId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    public function test_get_peserta_status_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getPesertaStatus');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    // ── getRuangMonitoring ─────────────────────────────────────
    // Uses Eloquent (SesiUjian) directly.

    public function test_get_ruang_monitoring_accepts_pengawas_id(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getRuangMonitoring');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('pengawasId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    public function test_get_ruang_monitoring_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getRuangMonitoring');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    // ── getPesertaByRuang ──────────────────────────────────────
    // Uses Eloquent (SesiUjian) directly.

    public function test_get_peserta_by_ruang_accepts_sesi_id(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getPesertaByRuang');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('sesiId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    public function test_get_peserta_by_ruang_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getPesertaByRuang');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    // ── getSekolahList ─────────────────────────────────────────

    public function test_get_sekolah_list_method_is_public(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getSekolahList');
        $this->assertTrue($reflection->isPublic());
    }
}
