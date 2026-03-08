<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Models\Soal;
use App\Services\SoalService;
use App\Repositories\SoalRepository;
use App\Repositories\KategoriSoalRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SoalServiceTest extends TestCase
{
    protected SoalService $service;
    protected MockInterface $repository;
    protected MockInterface $kategoriRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(SoalRepository::class);
        $this->kategoriRepository = Mockery::mock(KategoriSoalRepository::class);
        $this->service = new SoalService($this->repository, $this->kategoriRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── getFilteredSoal ──────────────────────────────────────────────

    public function test_get_filtered_soal_passes_all_filters_to_repository(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 20);

        $this->repository
            ->shouldReceive('getFilteredSoal')
            ->once()
            ->with('kategori-1', 'pg', 'mudah', 'test', 15)
            ->andReturn($paginator);

        $result = $this->service->getFilteredSoal('kategori-1', 'pg', 'mudah', 'test', 15);

        $this->assertSame($paginator, $result);
    }

    public function test_get_filtered_soal_with_null_filters(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 20);

        $this->repository
            ->shouldReceive('getFilteredSoal')
            ->once()
            ->with(null, null, null, null, 20)
            ->andReturn($paginator);

        $result = $this->service->getFilteredSoal();

        $this->assertSame($paginator, $result);
    }

    // ── getActiveKategori ────────────────────────────────────────────

    public function test_get_active_kategori_delegates_to_kategori_repository(): void
    {
        $collection = new Collection();

        $this->kategoriRepository
            ->shouldReceive('getActive')
            ->once()
            ->andReturn($collection);

        $result = $this->service->getActiveKategori();

        $this->assertSame($collection, $result);
    }

    // ── getSoalById ──────────────────────────────────────────────────

    public function test_get_soal_by_id_returns_soal_with_relations(): void
    {
        $soal = Mockery::mock(Soal::class);

        $this->repository
            ->shouldReceive('findWithRelations')
            ->once()
            ->with('soal-id-1', ['opsiJawaban', 'pasangan', 'kategori'])
            ->andReturn($soal);

        $result = $this->service->getSoalById('soal-id-1');

        $this->assertSame($soal, $result);
    }

    public function test_get_soal_by_id_returns_null_when_not_found(): void
    {
        $this->repository
            ->shouldReceive('findWithRelations')
            ->once()
            ->with('nonexistent', ['opsiJawaban', 'pasangan', 'kategori'])
            ->andReturn(null);

        $result = $this->service->getSoalById('nonexistent');

        $this->assertNull($result);
    }

    // ── createSoal ───────────────────────────────────────────────────

    public function test_create_soal_pg_creates_soal_and_saves_opsi(): void
    {
        $validated = [
            'kategori_soal_id'  => 'kat-1',
            'jenis_soal'        => 'pilihan_ganda',
            'pertanyaan'        => 'Apa ibu kota Indonesia?',
            'tingkat_kesulitan' => 'mudah',
            'bobot'             => 1,
        ];

        $user = Mockery::mock();
        $user->id = 'user-1';
        $user->sekolah_id = 'sekolah-1';
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('hasFile')->with('gambar_pertanyaan')->andReturn(false);
        $request->shouldReceive('input')->with('opsi', [])->andReturn([
            ['teks' => 'Jakarta', 'benar' => '1'],
            ['teks' => 'Bandung', 'benar' => '0'],
        ]);
        $request->shouldReceive('file')->with('opsi.0.gambar')->andReturn(null);
        $request->shouldReceive('file')->with('opsi.1.gambar')->andReturn(null);

        $soal = Mockery::mock(Soal::class);

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['kategori_id'] === 'kat-1'
                    && $data['tipe_soal'] === 'pg'
                    && $data['pertanyaan'] === 'Apa ibu kota Indonesia?'
                    && $data['created_by'] === 'user-1'
                    && $data['sekolah_id'] === 'sekolah-1';
            }))
            ->andReturn($soal);

        $this->repository
            ->shouldReceive('saveOpsiJawaban')
            ->once()
            ->with($soal, Mockery::on(function ($opsi) {
                return count($opsi) === 2
                    && $opsi[0]['label'] === 'A'
                    && $opsi[0]['teks'] === 'Jakarta'
                    && $opsi[0]['is_benar'] === true
                    && $opsi[1]['label'] === 'B'
                    && $opsi[1]['is_benar'] === false;
            }));

        $result = $this->service->createSoal($validated, $request);

        $this->assertSame($soal, $result);
    }

    public function test_create_soal_isian_does_not_save_opsi(): void
    {
        $validated = [
            'kategori_soal_id'  => 'kat-1',
            'jenis_soal'        => 'isian',
            'pertanyaan'        => 'Siapa presiden pertama RI?',
            'tingkat_kesulitan' => 'mudah',
            'bobot'             => 1,
        ];

        $user = Mockery::mock();
        $user->id = 'user-1';
        $user->sekolah_id = 'sekolah-1';
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('hasFile')->with('gambar_pertanyaan')->andReturn(false);

        $soal = Mockery::mock(Soal::class);

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn($soal);

        // For isian type, saveOpsiJawaban and savePasangan should NOT be called
        $this->repository->shouldNotReceive('saveOpsiJawaban');
        $this->repository->shouldNotReceive('savePasangan');

        $result = $this->service->createSoal($validated, $request);

        $this->assertSame($soal, $result);
    }

    public function test_create_soal_menjodohkan_saves_pasangan(): void
    {
        $validated = [
            'kategori_soal_id'  => 'kat-1',
            'jenis_soal'        => 'menjodohkan',
            'pertanyaan'        => 'Jodohkan berikut:',
            'tingkat_kesulitan' => 'sedang',
            'bobot'             => 2,
        ];

        $user = Mockery::mock();
        $user->id = 'user-1';
        $user->sekolah_id = 'sekolah-1';
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('hasFile')->with('gambar_pertanyaan')->andReturn(false);
        $request->shouldReceive('input')->with('pasangan_kiri_teks', [])->andReturn(['Kiri 1', 'Kiri 2']);
        $request->shouldReceive('input')->with('pasangan_kanan_teks', [])->andReturn(['Kanan 1', 'Kanan 2']);

        $soal = Mockery::mock(Soal::class);

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn($soal);

        $this->repository
            ->shouldReceive('savePasangan')
            ->once()
            ->with($soal, Mockery::on(function ($data) {
                return count($data) === 2
                    && $data[0]['kiri_teks'] === 'Kiri 1'
                    && $data[0]['kanan_teks'] === 'Kanan 1'
                    && $data[1]['kiri_teks'] === 'Kiri 2'
                    && $data[1]['kanan_teks'] === 'Kanan 2';
            }));

        $result = $this->service->createSoal($validated, $request);

        $this->assertSame($soal, $result);
    }

    // ── updateSoal ───────────────────────────────────────────────────

    public function test_update_soal_clears_old_opsi_and_saves_new(): void
    {
        $soal = Mockery::mock(Soal::class)->makePartial();
        $soal->gambar_pertanyaan = null;

        $validated = [
            'kategori_soal_id'  => 'kat-2',
            'jenis_soal'        => 'pilihan_ganda',
            'pertanyaan'        => 'Updated question?',
            'tingkat_kesulitan' => 'sulit',
            'bobot'             => 2,
        ];

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('hasFile')->with('gambar_pertanyaan')->andReturn(false);
        $request->shouldReceive('input')->with('opsi', [])->andReturn([
            ['teks' => 'Opsi A', 'benar' => '1'],
            ['teks' => 'Opsi B', 'benar' => '0'],
        ]);
        $request->shouldReceive('file')->with('opsi.0.gambar')->andReturn(null);
        $request->shouldReceive('file')->with('opsi.1.gambar')->andReturn(null);

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());

        $this->repository->shouldReceive('update')->once()->with($soal, Mockery::type('array'))->andReturn(true);
        $this->repository->shouldReceive('deleteOpsiJawaban')->once()->with($soal);
        $this->repository->shouldReceive('deletePasangan')->once()->with($soal);
        $this->repository->shouldReceive('saveOpsiJawaban')->once()->with($soal, Mockery::type('array'));

        $result = $this->service->updateSoal($soal, $validated, $request);

        $this->assertSame($soal, $result);
    }

    public function test_update_soal_deletes_old_image_when_replacing(): void
    {
        $soal = Mockery::mock(Soal::class)->makePartial();
        $soal->gambar_pertanyaan = 'soal/gambar/old.jpg';

        $validated = [
            'kategori_soal_id'  => 'kat-1',
            'jenis_soal'        => 'essay',
            'pertanyaan'        => 'Essay question?',
            'tingkat_kesulitan' => 'mudah',
            'bobot'             => 1,
        ];

        $uploadedFile = Mockery::mock(\Illuminate\Http\UploadedFile::class);
        $uploadedFile->shouldReceive('store')
            ->with('soal/gambar', 'public')
            ->andReturn('soal/gambar/new.jpg');

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('hasFile')->with('gambar_pertanyaan')->andReturn(true);
        $request->shouldReceive('file')->with('gambar_pertanyaan')->andReturn($uploadedFile);

        Storage::shouldReceive('disk')->with('public')->andReturnSelf();
        Storage::shouldReceive('delete')->once()->with('soal/gambar/old.jpg');

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());

        $this->repository->shouldReceive('update')->once()->andReturn(true);
        $this->repository->shouldReceive('deleteOpsiJawaban')->once();
        $this->repository->shouldReceive('deletePasangan')->once();

        $result = $this->service->updateSoal($soal, $validated, $request);

        $this->assertSame($soal, $result);
    }

    // ── deleteSoal ───────────────────────────────────────────────────

    public function test_delete_soal_calls_repository_delete(): void
    {
        $soal = Mockery::mock(Soal::class)->makePartial();
        $soal->gambar_pertanyaan = null;

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($soal)
            ->andReturn(true);

        $result = $this->service->deleteSoal($soal);

        $this->assertTrue($result);
    }

    public function test_delete_soal_removes_image_file(): void
    {
        $soal = Mockery::mock(Soal::class)->makePartial();
        $soal->gambar_pertanyaan = 'soal/gambar/image.jpg';

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());

        Storage::shouldReceive('disk')->with('public')->andReturnSelf();
        Storage::shouldReceive('delete')->once()->with('soal/gambar/image.jpg');

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($soal)
            ->andReturn(true);

        $result = $this->service->deleteSoal($soal);

        $this->assertTrue($result);
    }

    // ── getSoalByPaketUjian ──────────────────────────────────────────

    public function test_get_soal_by_paket_ujian_passes_args_to_repository(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 10);

        $this->repository
            ->shouldReceive('getByPaketUjian')
            ->once()
            ->with('paket-1', ['soal-a', 'soal-b'], 15)
            ->andReturn($paginator);

        $result = $this->service->getSoalByPaketUjian('paket-1', ['soal-a', 'soal-b'], 15);

        $this->assertSame($paginator, $result);
    }

    public function test_get_soal_by_paket_ujian_with_defaults(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 10);

        $this->repository
            ->shouldReceive('getByPaketUjian')
            ->once()
            ->with('paket-1', [], 10)
            ->andReturn($paginator);

        $result = $this->service->getSoalByPaketUjian('paket-1');

        $this->assertSame($paginator, $result);
    }

    // ── getBySekolah ─────────────────────────────────────────────────

    public function test_get_by_sekolah_passes_filters_to_repository(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 20);

        $this->repository
            ->shouldReceive('getFilteredBySekolah')
            ->once()
            ->with('sekolah-1', 'search term', 'kat-1', 'pg')
            ->andReturn($paginator);

        $result = $this->service->getBySekolah('sekolah-1', [
            'q'        => 'search term',
            'kategori' => 'kat-1',
            'jenis'    => 'pg',
        ]);

        $this->assertSame($paginator, $result);
    }

    public function test_get_by_sekolah_with_empty_filters(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 20);

        $this->repository
            ->shouldReceive('getFilteredBySekolah')
            ->once()
            ->with('sekolah-1', null, null, null)
            ->andReturn($paginator);

        $result = $this->service->getBySekolah('sekolah-1', []);

        $this->assertSame($paginator, $result);
    }

    // ── importSoal ───────────────────────────────────────────────────

    public function test_import_soal_returns_summary(): void
    {
        $rows = [
            ['teks_soal' => 'Soal 1', 'tipe_soal' => 'pg', 'bobot' => 1, 'a' => 'Opsi A', 'b' => 'Opsi B', 'jawaban_benar' => 'A'],
            ['teks_soal' => 'Soal 2', 'tipe_soal' => 'pg', 'bobot' => 1, 'a' => 'Opsi A', 'jawaban_benar' => 'A'],
        ];
        $meta = ['kategori_id' => 'kat-1', 'created_by' => 'user-1'];

        $opsiRelation1 = Mockery::mock();
        $opsiRelation1->shouldReceive('create')->andReturn(Mockery::mock());

        $soal1 = Mockery::mock(Soal::class)->makePartial();
        $soal1->shouldReceive('opsiJawaban')->andReturn($opsiRelation1);

        $opsiRelation2 = Mockery::mock();
        $opsiRelation2->shouldReceive('create')->andReturn(Mockery::mock());

        $soal2 = Mockery::mock(Soal::class)->makePartial();
        $soal2->shouldReceive('opsiJawaban')->andReturn($opsiRelation2);

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        $this->repository->shouldReceive('create')->twice()->andReturn($soal1, $soal2);

        $result = $this->service->importSoal($rows, $meta);

        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(2, $result['total']);
    }

    public function test_import_soal_skips_rows_with_empty_teks(): void
    {
        $rows = [
            ['teks_soal' => '', 'tipe_soal' => 'pg'],
            ['pertanyaan' => null, 'tipe_soal' => 'pg'],
        ];

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        $this->repository->shouldNotReceive('create');

        $result = $this->service->importSoal($rows);

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(2, $result['skipped']);
        $this->assertCount(2, $result['errors']);
    }
}
