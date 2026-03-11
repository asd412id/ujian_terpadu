<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Models\PaketUjian;
use App\Services\PaketUjianService;
use App\Services\SesiUjianService;
use App\Repositories\PaketUjianRepository;
use App\Repositories\KategoriSoalRepository;
use App\Repositories\SekolahRepository;
use App\Repositories\SoalRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaketUjianServiceTest extends TestCase
{
    protected PaketUjianService $service;
    protected MockInterface $repository;
    protected MockInterface $kategoriRepository;
    protected MockInterface $sekolahRepository;
    protected MockInterface $soalRepository;
    protected MockInterface $sesiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(PaketUjianRepository::class);
        $this->kategoriRepository = Mockery::mock(KategoriSoalRepository::class);
        $this->sekolahRepository = Mockery::mock(SekolahRepository::class);
        $this->soalRepository = Mockery::mock(SoalRepository::class);
        $this->sesiService = Mockery::mock(SesiUjianService::class);
        $this->service = new PaketUjianService(
            $this->repository,
            $this->kategoriRepository,
            $this->sekolahRepository,
            $this->soalRepository,
            $this->sesiService
        );
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
        $paginator = new LengthAwarePaginator([], 0, 10);

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->with(10)
            ->andReturn($paginator);

        $result = $this->service->getAllPaginated(10);

        $this->assertSame($paginator, $result);
    }

    // ── getActiveKategoris ───────────────────────────────────────────

    public function test_get_active_kategoris_delegates_to_kategori_repository(): void
    {
        $collection = new Collection();

        $this->kategoriRepository
            ->shouldReceive('getActive')
            ->once()
            ->andReturn($collection);

        $result = $this->service->getActiveKategoris();

        $this->assertSame($collection, $result);
    }

    // ── getActiveSekolahs ────────────────────────────────────────────

    public function test_get_active_sekolahs_delegates_to_sekolah_repository(): void
    {
        $collection = new Collection();

        $this->sekolahRepository
            ->shouldReceive('getFiltered')
            ->once()
            ->with(true)
            ->andReturn($collection);

        $result = $this->service->getActiveSekolahs();

        $this->assertSame($collection, $result);
    }

    // ── getById ──────────────────────────────────────────────────────

    public function test_get_by_id_returns_paket_with_relations(): void
    {
        $paket = Mockery::mock(PaketUjian::class);

        $this->repository
            ->shouldReceive('findWithRelations')
            ->once()
            ->with('paket-1')
            ->andReturn($paket);

        $result = $this->service->getById('paket-1');

        $this->assertSame($paket, $result);
    }

    public function test_get_by_id_returns_null_when_not_found(): void
    {
        $this->repository
            ->shouldReceive('findWithRelations')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $result = $this->service->getById('nonexistent');

        $this->assertNull($result);
    }

    // ── getBankSoalForPaket ──────────────────────────────────────────

    public function test_get_bank_soal_for_paket_excludes_already_attached(): void
    {
        $paket = new PaketUjian();
        $paket->forceFill(['id' => 'paket-1']);

        $paginator = new LengthAwarePaginator([], 0, 10);

        $this->repository
            ->shouldReceive('getSoalIdsByPaket')
            ->once()
            ->with('paket-1')
            ->andReturn(['soal-1', 'soal-2']);

        $this->soalRepository
            ->shouldReceive('getByPaketUjian')
            ->once()
            ->with('paket-1', ['soal-1', 'soal-2'], 10)
            ->andReturn($paginator);

        $result = $this->service->getBankSoalForPaket($paket);

        $this->assertSame($paginator, $result);
    }

    // ── createPaket ──────────────────────────────────────────────────

    public function test_create_paket_sets_defaults_and_creates(): void
    {
        $user = Mockery::mock();
        $user->id = 'user-1';
        Auth::shouldReceive('user')->once()->andReturn($user);

        $paket = Mockery::mock(PaketUjian::class);

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['nama_paket'] === 'Ujian Matematika'
                    && $data['created_by'] === 'user-1'
                    && $data['status'] === 'draft'
                    && $data['jumlah_soal'] === 0
                    && !empty($data['kode']);
            }))
            ->andReturn($paket);

        $result = $this->service->createPaket(['nama_paket' => 'Ujian Matematika']);

        $this->assertSame($paket, $result);
    }

    public function test_create_paket_with_sesi_creates_default_sesi(): void
    {
        $user = Mockery::mock();
        $user->id = 'user-1';
        Auth::shouldReceive('user')->once()->andReturn($user);

        $paket = Mockery::mock(PaketUjian::class);
        $paket->shouldReceive('getAttribute')->with('id')->andReturn('paket-new');
        $paket->shouldReceive('__get')->with('id')->andReturn('paket-new');

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn($paket);

        $this->repository
            ->shouldReceive('createSesi')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['paket_id'] === 'paket-new'
                    && $data['nama_sesi'] === 'Sesi 1'
                    && $data['ruangan'] === 'Lab A'
                    && $data['status'] === 'persiapan';
            }));

        $result = $this->service->createPaket(
            ['nama_paket' => 'Test'],
            'Sesi 1',
            'Lab A'
        );

        $this->assertSame($paket, $result);
    }

    public function test_create_paket_without_sesi_does_not_create_sesi(): void
    {
        $user = Mockery::mock();
        $user->id = 'user-1';
        Auth::shouldReceive('user')->once()->andReturn($user);

        $paket = Mockery::mock(PaketUjian::class);

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn($paket);

        $this->repository->shouldNotReceive('createSesi');

        $result = $this->service->createPaket(['nama_paket' => 'Test']);

        $this->assertSame($paket, $result);
    }

    // ── updatePaket ──────────────────────────────────────────────────

    public function test_update_paket_delegates_to_repository(): void
    {
        $paket = Mockery::mock(PaketUjian::class);
        $data = ['nama_paket' => 'Updated Name'];

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($paket, $data)
            ->andReturn(true);

        $result = $this->service->updatePaket($paket, $data);

        $this->assertSame($paket, $result);
    }

    // ── deletePaket ──────────────────────────────────────────────────

    public function test_delete_paket_delegates_to_repository(): void
    {
        $paket = Mockery::mock(PaketUjian::class);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($paket)
            ->andReturn(true);

        $result = $this->service->deletePaket($paket);

        $this->assertTrue($result);
    }

    // ── publishPaket ─────────────────────────────────────────────────

    public function test_publish_paket_with_soal_succeeds(): void
    {
        $paket = Mockery::mock(PaketUjian::class);

        $this->repository
            ->shouldReceive('getSoalCount')
            ->once()
            ->with($paket)
            ->andReturn(5);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($paket, ['status' => 'aktif'])
            ->andReturn(true);

        $result = $this->service->publishPaket($paket);

        $this->assertSame($paket, $result);
    }

    public function test_publish_paket_without_soal_throws_validation_exception(): void
    {
        $paket = Mockery::mock(PaketUjian::class);

        $this->repository
            ->shouldReceive('getSoalCount')
            ->once()
            ->with($paket)
            ->andReturn(0);

        $this->expectException(ValidationException::class);

        $this->service->publishPaket($paket);
    }

    // ── archivePaket ─────────────────────────────────────────────────

    public function test_archive_paket_sets_status_arsip(): void
    {
        $paket = Mockery::mock(PaketUjian::class);

        $this->sesiService
            ->shouldReceive('cancelPendingSesiByPaket')
            ->once()
            ->with($paket);

        $paket->shouldReceive('delete')->once();

        $result = $this->service->archivePaket($paket);

        $this->assertSame($paket, $result);
    }

    // ── draftPaket ───────────────────────────────────────────────────

    public function test_draft_paket_sets_status_draft(): void
    {
        $paket = Mockery::mock(PaketUjian::class);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($paket, ['status' => 'draft'])
            ->andReturn(true);

        $result = $this->service->draftPaket($paket);

        $this->assertSame($paket, $result);
    }

    // ── addSoalToPaket ───────────────────────────────────────────────

    public function test_add_soal_to_paket_delegates_to_repository(): void
    {
        $paket = Mockery::mock(PaketUjian::class);

        $this->repository
            ->shouldReceive('attachSoal')
            ->once()
            ->with($paket, 'soal-1')
            ->andReturn(true);

        $result = $this->service->addSoalToPaket($paket, 'soal-1');

        $this->assertTrue($result);
    }

    public function test_add_soal_to_paket_returns_false_if_already_exists(): void
    {
        $paket = Mockery::mock(PaketUjian::class);

        $this->repository
            ->shouldReceive('attachSoal')
            ->once()
            ->with($paket, 'soal-1')
            ->andReturn(false);

        $result = $this->service->addSoalToPaket($paket, 'soal-1');

        $this->assertFalse($result);
    }

    // ── removeSoalFromPaket ──────────────────────────────────────────

    public function test_remove_soal_from_paket_delegates_to_repository(): void
    {
        $paket = Mockery::mock(PaketUjian::class);

        $this->repository
            ->shouldReceive('detachSoal')
            ->once()
            ->with($paket, 'soal-1')
            ->andReturn(true);

        $result = $this->service->removeSoalFromPaket($paket, 'soal-1');

        $this->assertTrue($result);
    }

    // ── manageSoal ───────────────────────────────────────────────────

    public function test_manage_soal_syncs_soal_with_simple_ids(): void
    {
        $paket = Mockery::mock(PaketUjian::class);
        $soalRelation = Mockery::mock();
        $paket->shouldReceive('soal')->andReturn($soalRelation);
        $paket->shouldReceive('fresh')->with(['soal'])->andReturn($paket);

        $soalRelation->shouldReceive('sync')
            ->once()
            ->with(Mockery::on(function ($syncData) {
                return isset($syncData['soal-a'])
                    && $syncData['soal-a']['urutan'] === 1
                    && isset($syncData['soal-b'])
                    && $syncData['soal-b']['urutan'] === 2;
            }));

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with('paket-1')
            ->andReturn($paket);

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());

        $result = $this->service->manageSoal('paket-1', ['soal-a', 'soal-b']);

        $this->assertSame($paket, $result);
    }

    public function test_manage_soal_syncs_with_array_items_and_bobot(): void
    {
        $paket = Mockery::mock(PaketUjian::class);
        $soalRelation = Mockery::mock();
        $paket->shouldReceive('soal')->andReturn($soalRelation);
        $paket->shouldReceive('fresh')->with(['soal'])->andReturn($paket);

        $soalRelation->shouldReceive('sync')
            ->once()
            ->with(Mockery::on(function ($syncData) {
                return isset($syncData['soal-x'])
                    && $syncData['soal-x']['urutan'] === 3
                    && $syncData['soal-x']['bobot_override'] === 2.5;
            }));

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with('paket-1')
            ->andReturn($paket);

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());

        $result = $this->service->manageSoal('paket-1', [
            ['soal_id' => 'soal-x', 'urutan' => 3, 'bobot_override' => 2.5],
        ]);

        $this->assertSame($paket, $result);
    }

    // ── getSoalByPaket ───────────────────────────────────────────────

    public function test_get_soal_by_paket_returns_paket_soal_collection(): void
    {
        $paketSoal = new Collection(['soal1', 'soal2']);

        $paket = Mockery::mock(PaketUjian::class);
        $paket->shouldReceive('getAttribute')->with('paketSoal')->andReturn($paketSoal);

        $this->repository
            ->shouldReceive('findWithRelations')
            ->once()
            ->with('paket-1')
            ->andReturn($paket);

        $result = $this->service->getSoalByPaket('paket-1');

        $this->assertSame($paketSoal, $result);
    }

    public function test_get_soal_by_paket_returns_empty_collection_when_not_found(): void
    {
        $this->repository
            ->shouldReceive('findWithRelations')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $result = $this->service->getSoalByPaket('nonexistent');

        $this->assertCount(0, $result);
    }

    // ── getForSekolah ────────────────────────────────────────────────

    public function test_get_for_sekolah_delegates_to_repository(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 20);

        $this->repository
            ->shouldReceive('getForSekolah')
            ->once()
            ->with('sekolah-1', null, 20)
            ->andReturn($paginator);

        $result = $this->service->getForSekolah('sekolah-1');

        $this->assertSame($paginator, $result);
    }

    // ── getDetail ────────────────────────────────────────────────────

    public function test_get_detail_delegates_to_repository(): void
    {
        $paket = Mockery::mock(PaketUjian::class);

        $this->repository
            ->shouldReceive('findWithSesiPeserta')
            ->once()
            ->with('paket-1', null)
            ->andReturn($paket);

        $result = $this->service->getDetail('paket-1');

        $this->assertSame($paket, $result);
    }

    // ── registerPeserta ──────────────────────────────────────────────

    public function test_register_peserta_delegates_to_repository(): void
    {
        $this->repository
            ->shouldReceive('daftarPesertaToSesi')
            ->once()
            ->with('sesi-1', ['peserta-1', 'peserta-2'])
            ->andReturn(2);

        $result = $this->service->registerPeserta('sesi-1', ['peserta-1', 'peserta-2']);

        $this->assertEquals(2, $result);
    }
}
