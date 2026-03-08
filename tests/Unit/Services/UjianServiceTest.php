<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\UjianService;
use App\Services\PenilaianService;
use App\Repositories\SesiUjianRepository;
use App\Repositories\JawabanRepository;

class UjianServiceTest extends TestCase
{
    protected UjianService $service;
    protected MockInterface $sesiUjianRepository;
    protected MockInterface $jawabanRepository;
    protected MockInterface $penilaianService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sesiUjianRepository = Mockery::mock(SesiUjianRepository::class);
        $this->jawabanRepository = Mockery::mock(JawabanRepository::class);
        $this->penilaianService = Mockery::mock(PenilaianService::class);
        $this->service = new UjianService(
            $this->sesiUjianRepository,
            $this->jawabanRepository,
            $this->penilaianService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Service construction ───────────────────────────────────

    public function test_service_requires_three_dependencies(): void
    {
        $reflection = new \ReflectionClass(UjianService::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('sesiUjianRepository', $params[0]->getName());
        $this->assertEquals('jawabanRepository', $params[1]->getName());
        $this->assertEquals('penilaianService', $params[2]->getName());
    }

    // ── startUjian ─────────────────────────────────────────────
    // Uses SesiPeserta::findOrFail, JawabanPeserta, Cache, LogAktivitasUjian directly.

    public function test_start_ujian_signature(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'startUjian');
        $params = $reflection->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('sesiPesertaId', $params[0]->getName());
        $this->assertEquals('pesertaId', $params[1]->getName());
        $this->assertEquals('requestMeta', $params[2]->getName());
        $this->assertTrue($params[2]->isDefaultValueAvailable());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    // ── getSoalUjian ───────────────────────────────────────────

    public function test_get_soal_ujian_signature(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getSoalUjian');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('sesiPesertaId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    // ── getStatusUjian ─────────────────────────────────────────

    public function test_get_status_ujian_signature(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getStatusUjian');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('sesiPesertaId', $params[0]->getName());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    // ── selesaikanUjian ────────────────────────────────────────

    public function test_selesaikan_ujian_signature(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'selesaikanUjian');
        $params = $reflection->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('sesiPesertaId', $params[0]->getName());
        $this->assertEquals('pesertaId', $params[1]->getName());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    // ── getHasilUjian ──────────────────────────────────────────

    public function test_get_hasil_ujian_signature(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getHasilUjian');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('sesiPesertaId', $params[0]->getName());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    // ── detectDevice (private utility) ─────────────────────────

    public function test_detect_device_returns_desktop_for_windows_ua(): void
    {
        $result = $this->invokeDetectDevice('Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $this->assertEquals('desktop', $result);
    }

    public function test_detect_device_returns_mobile_for_android(): void
    {
        $result = $this->invokeDetectDevice('Mozilla/5.0 (Linux; Android 11; Pixel 5)');
        $this->assertEquals('mobile', $result);
    }

    public function test_detect_device_returns_mobile_for_iphone(): void
    {
        $result = $this->invokeDetectDevice('Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) Mobile');
        $this->assertEquals('mobile', $result);
    }

    public function test_detect_device_returns_tablet_for_ipad(): void
    {
        $result = $this->invokeDetectDevice('Mozilla/5.0 (iPad; CPU OS 15_0 like Mac OS X)');
        $this->assertEquals('tablet', $result);
    }

    public function test_detect_device_returns_tablet_for_tablet_keyword(): void
    {
        $result = $this->invokeDetectDevice('Mozilla/5.0 (Linux; Tablet SM-T500)');
        $this->assertEquals('tablet', $result);
    }

    public function test_detect_device_returns_desktop_for_empty_string(): void
    {
        $result = $this->invokeDetectDevice('');
        $this->assertEquals('desktop', $result);
    }

    public function test_detect_device_returns_desktop_for_mac_ua(): void
    {
        $result = $this->invokeDetectDevice('Mozilla/5.0 (Macintosh; Intel Mac OS X 12_0)');
        $this->assertEquals('desktop', $result);
    }

    public function test_detect_device_returns_desktop_for_linux_ua(): void
    {
        $result = $this->invokeDetectDevice('Mozilla/5.0 (X11; Linux x86_64)');
        $this->assertEquals('desktop', $result);
    }

    public function test_detect_device_returns_mobile_for_mobile_keyword(): void
    {
        $result = $this->invokeDetectDevice('Some Browser Mobile/1.0');
        $this->assertEquals('mobile', $result);
    }

    public function test_detect_device_is_case_insensitive(): void
    {
        $result = $this->invokeDetectDevice('ANDROID MOBILE');
        $this->assertEquals('mobile', $result);
    }

    /**
     * Helper: invoke the private detectDevice method.
     */
    private function invokeDetectDevice(string $userAgent): string
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('detectDevice');
        $method->setAccessible(true);

        return $method->invoke($this->service, $userAgent);
    }
}
