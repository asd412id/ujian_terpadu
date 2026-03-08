<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\LobbyService;
use App\Repositories\SesiUjianRepository;
use App\Repositories\PaketUjianRepository;

class LobbyServiceTest extends TestCase
{
    protected LobbyService $service;
    protected MockInterface $sesiUjianRepository;
    protected MockInterface $paketUjianRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sesiUjianRepository = Mockery::mock(SesiUjianRepository::class);
        $this->paketUjianRepository = Mockery::mock(PaketUjianRepository::class);
        $this->service = new LobbyService(
            $this->sesiUjianRepository,
            $this->paketUjianRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Service construction ───────────────────────────────────

    public function test_service_requires_two_repository_dependencies(): void
    {
        $reflection = new \ReflectionClass(LobbyService::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('sesiUjianRepository', $params[0]->getName());
        $this->assertEquals('paketUjianRepository', $params[1]->getName());
    }

    // ── getAvailableUjian ──────────────────────────────────────
    // Uses SesiPeserta Eloquent model directly.

    public function test_get_available_ujian_accepts_peserta_id(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getAvailableUjian');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('pesertaId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    public function test_get_available_ujian_is_public(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getAvailableUjian');
        $this->assertTrue($reflection->isPublic());
    }

    // ── getUjianHistory ────────────────────────────────────────
    // Uses SesiPeserta Eloquent model directly.

    public function test_get_ujian_history_accepts_peserta_id(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getUjianHistory');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('pesertaId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    public function test_get_ujian_history_is_public(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getUjianHistory');
        $this->assertTrue($reflection->isPublic());
    }

    // ── getLobbyData ───────────────────────────────────────────

    public function test_get_lobby_data_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getLobbyData');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    public function test_get_lobby_data_accepts_peserta_id(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getLobbyData');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('pesertaId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    // ── Public API surface ─────────────────────────────────────

    public function test_service_exposes_expected_public_methods(): void
    {
        $reflection = new \ReflectionClass(LobbyService::class);
        $publicMethods = array_filter(
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            fn ($m) => $m->getDeclaringClass()->getName() === LobbyService::class
        );
        $methodNames = array_map(fn ($m) => $m->getName(), $publicMethods);

        $this->assertContains('getAvailableUjian', $methodNames);
        $this->assertContains('getUjianHistory', $methodNames);
        $this->assertContains('getLobbyData', $methodNames);
    }
}
