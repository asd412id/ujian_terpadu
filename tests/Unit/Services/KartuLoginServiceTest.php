<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\KartuLoginService;
use App\Repositories\PesertaRepository;

class KartuLoginServiceTest extends TestCase
{
    protected KartuLoginService $service;
    protected MockInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(PesertaRepository::class);
        $this->service = new KartuLoginService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Service construction ───────────────────────────────────

    public function test_service_requires_peserta_repository(): void
    {
        $reflection = new \ReflectionClass(KartuLoginService::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('repository', $params[0]->getName());
    }

    // ── generateKartuLogin ─────────────────────────────────────
    // Uses Peserta Eloquent model directly.

    public function test_generate_kartu_login_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'generateKartuLogin');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    public function test_generate_kartu_login_accepts_sekolah_id_and_filters(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'generateKartuLogin');
        $params = $reflection->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('sekolahId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
        $this->assertEquals('filters', $params[1]->getName());
        $this->assertEquals('array', $params[1]->getType()->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertEquals([], $params[1]->getDefaultValue());
    }

    // ── getKartuBySekolah ──────────────────────────────────────
    // Uses SesiUjian and SesiPeserta Eloquent directly.

    public function test_get_kartu_by_sekolah_accepts_sekolah_id(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getKartuBySekolah');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('sekolahId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    public function test_get_kartu_by_sekolah_is_public(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getKartuBySekolah');
        $this->assertTrue($reflection->isPublic());
    }

    // ── printKartu ─────────────────────────────────────────────
    // Uses Peserta Eloquent model directly.

    public function test_print_kartu_accepts_peserta_ids_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'printKartu');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('pesertaIds', $params[0]->getName());
        $this->assertEquals('array', $params[0]->getType()->getName());
    }

    public function test_print_kartu_is_public(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'printKartu');
        $this->assertTrue($reflection->isPublic());
    }

    // ── getKartuPeserta ────────────────────────────────────────

    public function test_get_kartu_peserta_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getKartuPeserta');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    public function test_get_kartu_peserta_accepts_peserta_id(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getKartuPeserta');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('pesertaId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    // ── getKartuBySesi ─────────────────────────────────────────

    public function test_get_kartu_by_sesi_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getKartuBySesi');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    public function test_get_kartu_by_sesi_accepts_sesi_id(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getKartuBySesi');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('sesiId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    // ── Public API surface ─────────────────────────────────────

    public function test_service_exposes_expected_public_methods(): void
    {
        $reflection = new \ReflectionClass(KartuLoginService::class);
        $publicMethods = array_filter(
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            fn ($m) => $m->getDeclaringClass()->getName() === KartuLoginService::class
        );
        $methodNames = array_map(fn ($m) => $m->getName(), $publicMethods);

        $this->assertContains('generateKartuLogin', $methodNames);
        $this->assertContains('getKartuBySekolah', $methodNames);
        $this->assertContains('printKartu', $methodNames);
        $this->assertContains('getKartuPeserta', $methodNames);
        $this->assertContains('getKartuBySesi', $methodNames);
    }
}
