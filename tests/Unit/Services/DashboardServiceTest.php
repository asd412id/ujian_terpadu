<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\DashboardService;
use App\Repositories\DashboardRepository;

class DashboardServiceTest extends TestCase
{
    protected DashboardService $service;
    protected MockInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(DashboardRepository::class);
        $this->service = new DashboardService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── getDinasDashboard ──────────────────────────────────────

    public function test_get_dinas_dashboard_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getDinasDashboard');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    public function test_get_dinas_dashboard_has_no_parameters(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getDinasDashboard');
        $this->assertCount(0, $reflection->getParameters());
    }

    // ── getSekolahDashboard ────────────────────────────────────

    public function test_get_sekolah_dashboard_accepts_sekolah_id(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getSekolahDashboard');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('sekolahId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    public function test_get_sekolah_dashboard_returns_nullable_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getSekolahDashboard');
        $returnType = $reflection->getReturnType();

        $this->assertTrue($returnType->allowsNull());
        $this->assertEquals('array', $returnType->getName());
    }

    // ── getPengawasDashboard ───────────────────────────────────

    public function test_get_pengawas_dashboard_accepts_pengawas_id(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getPengawasDashboard');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('pengawasId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    public function test_get_pengawas_dashboard_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getPengawasDashboard');
        $returnType = $reflection->getReturnType();

        $this->assertFalse($returnType->allowsNull());
        $this->assertEquals('array', $returnType->getName());
    }

    // ── Class structure ────────────────────────────────────────

    public function test_service_requires_dashboard_repository(): void
    {
        $reflection = new \ReflectionClass(DashboardService::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('repository', $params[0]->getName());
    }

    public function test_service_exposes_three_public_dashboard_methods(): void
    {
        $reflection = new \ReflectionClass(DashboardService::class);
        $publicMethods = array_filter(
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            fn ($m) => $m->getDeclaringClass()->getName() === DashboardService::class
        );
        $methodNames = array_map(fn ($m) => $m->getName(), $publicMethods);

        $this->assertContains('getDinasDashboard', $methodNames);
        $this->assertContains('getSekolahDashboard', $methodNames);
        $this->assertContains('getPengawasDashboard', $methodNames);
    }
}
