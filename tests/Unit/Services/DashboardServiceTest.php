<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use App\Services\DashboardService;

class DashboardServiceTest extends TestCase
{
    protected DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // DashboardService has no constructor dependencies.
        // All methods use Eloquent models and Cache directly.
        $this->service = new DashboardService();
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

    public function test_service_has_no_constructor_dependencies(): void
    {
        $reflection = new \ReflectionClass(DashboardService::class);
        $constructor = $reflection->getConstructor();

        // No constructor or empty constructor
        $this->assertTrue(
            $constructor === null || count($constructor->getParameters()) === 0
        );
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
