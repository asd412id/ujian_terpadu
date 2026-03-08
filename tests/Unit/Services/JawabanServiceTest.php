<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\JawabanService;
use App\Repositories\JawabanRepository;

class JawabanServiceTest extends TestCase
{
    protected JawabanService $service;
    protected MockInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(JawabanRepository::class);
        $this->service = new JawabanService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── simpanJawaban ──────────────────────────────────────────
    // Uses SesiPeserta::findOrFail and JawabanPeserta::updateOrCreate directly.

    public function test_simpan_jawaban_signature(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'simpanJawaban');
        $params = $reflection->getParameters();

        $this->assertCount(4, $params);
        $this->assertEquals('sesiPesertaId', $params[0]->getName());
        $this->assertEquals('soalId', $params[1]->getName());
        $this->assertEquals('jawaban', $params[2]->getName());
        $this->assertEquals('idempotencyKey', $params[3]->getName());
        $this->assertTrue($params[3]->allowsNull());
    }

    // ── getJawabanBySesi ───────────────────────────────────────

    public function test_get_jawaban_by_sesi_delegates_to_repository(): void
    {
        $sesiPesertaId = 'sesi-peserta-1';
        $expected = collect(['jawaban1', 'jawaban2']);

        $this->repository
            ->shouldReceive('getBySesiPeserta')
            ->once()
            ->with($sesiPesertaId)
            ->andReturn($expected);

        $result = $this->service->getJawabanBySesi($sesiPesertaId);
        $this->assertEquals($expected, $result);
    }

    public function test_get_jawaban_by_sesi_returns_empty_collection(): void
    {
        $sesiPesertaId = 'sesi-peserta-empty';
        $expected = collect([]);

        $this->repository
            ->shouldReceive('getBySesiPeserta')
            ->once()
            ->with($sesiPesertaId)
            ->andReturn($expected);

        $result = $this->service->getJawabanBySesi($sesiPesertaId);
        $this->assertCount(0, $result);
    }

    // ── updateJawaban ──────────────────────────────────────────
    // Uses JawabanPeserta::findOrFail directly.

    public function test_update_jawaban_signature(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'updateJawaban');
        $params = $reflection->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('jawabanId', $params[0]->getName());
        $this->assertEquals('data', $params[1]->getName());
    }

    // ── parseJawaban (private) ─────────────────────────────────

    public function test_parse_jawaban_string_answer_sets_teks_and_is_terjawab(): void
    {
        $result = $this->invokeParseJawaban('A');

        $this->assertIsArray($result);
        $this->assertTrue($result['is_terjawab']);
        $this->assertEquals('A', $result['jawaban_teks']);
        $this->assertNull($result['jawaban_pg']);
        $this->assertNull($result['jawaban_pasangan']);
    }

    public function test_parse_jawaban_null_sets_not_terjawab(): void
    {
        $result = $this->invokeParseJawaban(null);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_terjawab']);
        $this->assertNull($result['jawaban_pg']);
        $this->assertNull($result['jawaban_pasangan']);
    }

    public function test_parse_jawaban_empty_string_sets_not_terjawab(): void
    {
        $result = $this->invokeParseJawaban('');

        $this->assertFalse($result['is_terjawab']);
    }

    public function test_parse_jawaban_whitespace_only_string_sets_not_terjawab(): void
    {
        $result = $this->invokeParseJawaban('   ');

        $this->assertFalse($result['is_terjawab']);
        $this->assertEquals('   ', $result['jawaban_teks']);
    }

    public function test_parse_jawaban_array_pg_sets_jawaban_pg(): void
    {
        $result = $this->invokeParseJawaban(['A', 'B', 'C']);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_terjawab']);
        $this->assertEquals(['A', 'B', 'C'], $result['jawaban_pg']);
        $this->assertNull($result['jawaban_pasangan']);
        $this->assertNull($result['jawaban_teks']);
    }

    public function test_parse_jawaban_single_pg_array(): void
    {
        $result = $this->invokeParseJawaban(['A']);

        $this->assertTrue($result['is_terjawab']);
        $this->assertEquals(['A'], $result['jawaban_pg']);
    }

    public function test_parse_jawaban_pasangan_array_detected_correctly(): void
    {
        $pasangan = [[1, 3], [2, 1]];
        $result = $this->invokeParseJawaban($pasangan);

        $this->assertTrue($result['is_terjawab']);
        $this->assertNull($result['jawaban_pg']);
        $this->assertEquals($pasangan, $result['jawaban_pasangan']);
        $this->assertNull($result['jawaban_teks']);
    }

    public function test_parse_jawaban_empty_array_sets_not_terjawab(): void
    {
        $result = $this->invokeParseJawaban([]);

        $this->assertFalse($result['is_terjawab']);
    }

    public function test_parse_jawaban_long_essay_string(): void
    {
        $essay = 'Ini adalah jawaban essay yang sangat panjang dan lengkap dengan penjelasan.';
        $result = $this->invokeParseJawaban($essay);

        $this->assertTrue($result['is_terjawab']);
        $this->assertEquals($essay, $result['jawaban_teks']);
        $this->assertNull($result['jawaban_pg']);
    }

    // ── syncOfflineAnswers ─────────────────────────────────────

    public function test_sync_offline_answers_signature(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'syncOfflineAnswers');
        $params = $reflection->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('sesiToken', $params[0]->getName());
        $this->assertEquals('answers', $params[1]->getName());
        $this->assertEquals('requestMeta', $params[2]->getName());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    // ── getStatusByToken ───────────────────────────────────────

    public function test_get_status_by_token_signature(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getStatusByToken');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('token', $params[0]->getName());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    /**
     * Helper: invoke the private parseJawaban method.
     */
    private function invokeParseJawaban(mixed $jawaban): array
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('parseJawaban');
        $method->setAccessible(true);

        return $method->invoke($this->service, $jawaban);
    }
}
