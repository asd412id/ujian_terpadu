<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\GradingService;
use App\Services\PenilaianService;
use App\Repositories\GradingRepository;
use Illuminate\Validation\ValidationException;

class GradingServiceTest extends TestCase
{
    protected GradingService $service;
    protected MockInterface $repository;
    protected MockInterface $penilaianService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(GradingRepository::class);
        $this->penilaianService = Mockery::mock(PenilaianService::class);
        $this->service = new GradingService($this->repository, $this->penilaianService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── getPendingGrading ──────────────────────────────────────
    // Uses Eloquent directly (JawabanPeserta, PaketUjian, Sekolah).

    public function test_get_pending_grading_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getPendingGrading');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    public function test_get_pending_grading_accepts_filters(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getPendingGrading');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('filters', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertEquals([], $params[0]->getDefaultValue());
    }

    // ── getEssayJawaban ────────────────────────────────────────

    public function test_get_essay_jawaban_delegates_to_repository(): void
    {
        $sesiId = 'sesi-1';
        $expected = collect(['jawaban1', 'jawaban2']);

        $this->repository
            ->shouldReceive('getEssayBySesi')
            ->once()
            ->with($sesiId)
            ->andReturn($expected);

        $result = $this->service->getEssayJawaban($sesiId);
        $this->assertEquals($expected, $result);
    }

    public function test_get_essay_jawaban_returns_empty_collection(): void
    {
        $sesiId = 'sesi-no-essay';

        $this->repository
            ->shouldReceive('getEssayBySesi')
            ->once()
            ->with($sesiId)
            ->andReturn(collect([]));

        $result = $this->service->getEssayJawaban($sesiId);
        $this->assertCount(0, $result);
    }

    // ── gradeJawaban ───────────────────────────────────────────

    public function test_grade_jawaban_throws_when_score_above_100(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->gradeJawaban('jawaban-1', 150);
    }

    public function test_grade_jawaban_throws_when_score_below_0(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->gradeJawaban('jawaban-1', -5);
    }

    public function test_grade_jawaban_throws_when_score_is_101(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->gradeJawaban('jawaban-1', 101);
    }

    public function test_grade_jawaban_throws_with_correct_message_for_invalid_score(): void
    {
        try {
            $this->service->gradeJawaban('jawaban-1', 150);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('skor_manual', $e->errors());
            $this->assertStringContainsString('0 dan 100', $e->errors()['skor_manual'][0]);
        }
    }

    public function test_grade_jawaban_does_not_throw_for_score_0(): void
    {
        // Score 0 is valid — should NOT throw ValidationException.
        // Will fail on JawabanPeserta::findOrFail (DB not available), not on validation.
        try {
            $this->service->gradeJawaban('jawaban-1', 0);
        } catch (ValidationException $e) {
            $this->fail('Should not throw ValidationException for score 0');
        } catch (\Exception $e) {
            // Expected: model not found or DB error — that's OK
            $this->assertNotInstanceOf(ValidationException::class, $e);
        }
    }

    public function test_grade_jawaban_does_not_throw_for_score_100(): void
    {
        try {
            $this->service->gradeJawaban('jawaban-1', 100);
        } catch (ValidationException $e) {
            $this->fail('Should not throw ValidationException for score 100');
        } catch (\Exception $e) {
            $this->assertNotInstanceOf(ValidationException::class, $e);
        }
    }

    public function test_grade_jawaban_does_not_throw_for_score_50_5(): void
    {
        try {
            $this->service->gradeJawaban('jawaban-1', 50.5);
        } catch (ValidationException $e) {
            $this->fail('Should not throw ValidationException for score 50.5');
        } catch (\Exception $e) {
            $this->assertNotInstanceOf(ValidationException::class, $e);
        }
    }

    // ── getGradingStats ────────────────────────────────────────
    // Uses Eloquent directly (JawabanPeserta queries).

    public function test_get_grading_stats_returns_array(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getGradingStats');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    public function test_get_grading_stats_has_no_parameters(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'getGradingStats');
        $this->assertCount(0, $reflection->getParameters());
    }

    // ── Service construction ───────────────────────────────────

    public function test_service_requires_grading_repository_and_penilaian_service(): void
    {
        $reflection = new \ReflectionClass(GradingService::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('repository', $params[0]->getName());
        $this->assertEquals('penilaianService', $params[1]->getName());
    }
}
