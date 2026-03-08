<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Models\DinasPendidikan;
use App\Models\Sekolah;
use App\Services\SekolahService;
use App\Repositories\SekolahRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

class SekolahServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SekolahService $service;
    protected MockInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(SekolahRepository::class);
        $this->service = new SekolahService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── getAllPaginated ───────────────────────────────────────────────

    public function test_get_all_paginated_delegates_to_repository(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 20);

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->with(20)
            ->andReturn($paginator);

        $result = $this->service->getAllPaginated();

        $this->assertSame($paginator, $result);
    }

    public function test_get_all_paginated_with_custom_per_page(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 15);

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->with(15)
            ->andReturn($paginator);

        $result = $this->service->getAllPaginated(15);

        $this->assertSame($paginator, $result);
    }

    // ── getById ──────────────────────────────────────────────────────

    public function test_get_by_id_returns_sekolah_with_stats(): void
    {
        $sekolah = Mockery::mock(Sekolah::class);

        $this->repository
            ->shouldReceive('findWithStats')
            ->once()
            ->with('sekolah-1')
            ->andReturn($sekolah);

        $result = $this->service->getById('sekolah-1');

        $this->assertSame($sekolah, $result);
    }

    public function test_get_by_id_returns_null_when_not_found(): void
    {
        $this->repository
            ->shouldReceive('findWithStats')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $result = $this->service->getById('nonexistent');

        $this->assertNull($result);
    }

    // ── createSekolah ────────────────────────────────────────────────

    public function test_create_sekolah_sets_dinas_id_and_creates(): void
    {
        // Create a real DinasPendidikan record since DinasPendidikan::first()
        // is a static Eloquent call that cannot be cleanly mocked.
        $dinas = DinasPendidikan::factory()->create();

        $sekolah = Mockery::mock(Sekolah::class);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($dinas) {
                return $data['nama'] === 'SD Negeri 1'
                    && $data['npsn'] === '12345678'
                    && $data['dinas_id'] === $dinas->id;
            }))
            ->andReturn($sekolah);

        $result = $this->service->createSekolah([
            'nama' => 'SD Negeri 1',
            'npsn' => '12345678',
        ]);

        $this->assertSame($sekolah, $result);
    }

    // ── updateSekolah ────────────────────────────────────────────────

    public function test_update_sekolah_delegates_to_repository(): void
    {
        $sekolah = Mockery::mock(Sekolah::class);
        $data = ['nama' => 'SD Negeri 1 Updated'];

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($sekolah, $data)
            ->andReturn(true);

        $result = $this->service->updateSekolah($sekolah, $data);

        $this->assertSame($sekolah, $result);
    }

    // ── deleteSekolah ────────────────────────────────────────────────

    public function test_delete_sekolah_delegates_to_repository(): void
    {
        $sekolah = Mockery::mock(Sekolah::class);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($sekolah)
            ->andReturn(true);

        $result = $this->service->deleteSekolah($sekolah);

        $this->assertTrue($result);
    }

    public function test_delete_sekolah_returns_false_on_failure(): void
    {
        $sekolah = Mockery::mock(Sekolah::class);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($sekolah)
            ->andReturn(false);

        $result = $this->service->deleteSekolah($sekolah);

        $this->assertFalse($result);
    }

    // ── getActiveSekolahs ────────────────────────────────────────────

    public function test_get_active_sekolahs_delegates_to_repository(): void
    {
        $collection = new Collection();

        $this->repository
            ->shouldReceive('getFiltered')
            ->once()
            ->with(true)
            ->andReturn($collection);

        $result = $this->service->getActiveSekolahs();

        $this->assertSame($collection, $result);
    }

    // ── getWithStats ─────────────────────────────────────────────────

    public function test_get_with_stats_delegates_to_repository(): void
    {
        $collection = new Collection();

        $this->repository
            ->shouldReceive('getWithStats')
            ->once()
            ->andReturn($collection);

        $result = $this->service->getWithStats();

        $this->assertSame($collection, $result);
    }
}
