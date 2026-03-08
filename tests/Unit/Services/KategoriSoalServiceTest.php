<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Models\KategoriSoal;
use App\Services\KategoriSoalService;
use App\Repositories\KategoriSoalRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class KategoriSoalServiceTest extends TestCase
{
    protected KategoriSoalService $service;
    protected MockInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(KategoriSoalRepository::class);
        $this->service = new KategoriSoalService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── getAllPaginated ───────────────────────────────────────────────

    public function test_get_all_paginated_delegates_to_repository(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 30);

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->with(30)
            ->andReturn($paginator);

        $result = $this->service->getAllPaginated();

        $this->assertSame($paginator, $result);
    }

    public function test_get_all_paginated_with_custom_per_page(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 10);

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->with(10)
            ->andReturn($paginator);

        $result = $this->service->getAllPaginated(10);

        $this->assertSame($paginator, $result);
    }

    // ── getActive ────────────────────────────────────────────────────

    public function test_get_active_delegates_to_repository(): void
    {
        $collection = new Collection();

        $this->repository
            ->shouldReceive('getActive')
            ->once()
            ->andReturn($collection);

        $result = $this->service->getActive();

        $this->assertSame($collection, $result);
    }

    // ── getById ──────────────────────────────────────────────────────

    public function test_get_by_id_returns_kategori(): void
    {
        $kategori = Mockery::mock(KategoriSoal::class);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with('kat-1')
            ->andReturn($kategori);

        $result = $this->service->getById('kat-1');

        $this->assertSame($kategori, $result);
    }

    public function test_get_by_id_returns_null_when_not_found(): void
    {
        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $result = $this->service->getById('nonexistent');

        $this->assertNull($result);
    }

    // ── createKategori ───────────────────────────────────────────────

    public function test_create_kategori_delegates_to_repository(): void
    {
        $kategori = Mockery::mock(KategoriSoal::class);
        $data = ['nama' => 'Matematika', 'urutan' => 1];

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($kategori);

        $result = $this->service->createKategori($data);

        $this->assertSame($kategori, $result);
    }

    // ── updateKategori ───────────────────────────────────────────────

    public function test_update_kategori_delegates_to_repository(): void
    {
        $kategori = Mockery::mock(KategoriSoal::class);
        $data = ['nama' => 'Matematika Updated'];

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($kategori, $data)
            ->andReturn(true);

        $result = $this->service->updateKategori($kategori, $data);

        $this->assertSame($kategori, $result);
    }

    // ── deleteKategori ───────────────────────────────────────────────

    public function test_delete_kategori_delegates_to_repository(): void
    {
        $kategori = Mockery::mock(KategoriSoal::class);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($kategori)
            ->andReturn(true);

        $result = $this->service->deleteKategori($kategori);

        $this->assertTrue($result);
    }

    public function test_delete_kategori_returns_false_on_failure(): void
    {
        $kategori = Mockery::mock(KategoriSoal::class);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($kategori)
            ->andReturn(false);

        $result = $this->service->deleteKategori($kategori);

        $this->assertFalse($result);
    }
}
