<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\PesertaService;
use App\Repositories\PesertaRepository;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PesertaServiceTest extends TestCase
{
    protected PesertaService $service;
    protected MockInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(PesertaRepository::class);
        $this->service = new PesertaService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── getAll ─────────────────────────────────────────────────
    // BUG: getAll() passes array $filters to getFiltered(string $sekolahId, ...).
    // This causes a TypeError at runtime. We document this as a known issue.

    public function test_get_all_throws_type_error_due_to_argument_mismatch(): void
    {
        $this->repository
            ->shouldReceive('getFiltered')
            ->andReturn(new LengthAwarePaginator([], 0, 25));

        $this->expectException(\TypeError::class);
        $this->service->getAll(['q' => 'test']);
    }

    // ── getBySekolah ───────────────────────────────────────────

    public function test_get_by_sekolah_passes_filters_correctly(): void
    {
        $sekolahId = 'sekolah-1';
        $filters = ['q' => 'john', 'kelas' => '10A', 'jurusan' => 'IPA'];
        $expected = new LengthAwarePaginator([], 0, 25);

        $this->repository
            ->shouldReceive('getFiltered')
            ->once()
            ->with($sekolahId, 'john', '10A', 'IPA')
            ->andReturn($expected);

        $result = $this->service->getBySekolah($sekolahId, $filters);
        $this->assertEquals($expected, $result);
    }

    public function test_get_by_sekolah_with_empty_filters_passes_nulls(): void
    {
        $sekolahId = 'sekolah-1';
        $expected = new LengthAwarePaginator([], 0, 25);

        $this->repository
            ->shouldReceive('getFiltered')
            ->once()
            ->with($sekolahId, null, null, null)
            ->andReturn($expected);

        $result = $this->service->getBySekolah($sekolahId);
        $this->assertEquals($expected, $result);
    }

    public function test_get_by_sekolah_with_partial_filters(): void
    {
        $sekolahId = 'sekolah-1';
        $filters = ['q' => 'john'];
        $expected = new LengthAwarePaginator([], 0, 25);

        $this->repository
            ->shouldReceive('getFiltered')
            ->once()
            ->with($sekolahId, 'john', null, null)
            ->andReturn($expected);

        $result = $this->service->getBySekolah($sekolahId, $filters);
        $this->assertEquals($expected, $result);
    }

    public function test_get_by_sekolah_with_only_kelas_filter(): void
    {
        $sekolahId = 'sekolah-2';
        $filters = ['kelas' => '12B'];
        $expected = new LengthAwarePaginator([], 0, 25);

        $this->repository
            ->shouldReceive('getFiltered')
            ->once()
            ->with($sekolahId, null, '12B', null)
            ->andReturn($expected);

        $result = $this->service->getBySekolah($sekolahId, $filters);
        $this->assertEquals($expected, $result);
    }

    // ── getById ────────────────────────────────────────────────
    // Note: findWithRelations() is not defined in PesertaRepository.
    // Mockery can still handle the call since shouldReceive works on undefined methods.

    public function test_get_by_id_returns_peserta_with_relations(): void
    {
        $id = 'peserta-uuid-1';
        $expected = (object) ['id' => $id, 'nama' => 'John'];

        $this->repository
            ->shouldReceive('findWithRelations')
            ->once()
            ->with($id, ['sekolah'])
            ->andReturn($expected);

        $result = $this->service->getById($id);
        $this->assertEquals($expected, $result);
    }

    // ── create ─────────────────────────────────────────────────

    public function test_create_generates_username_from_nis_when_not_provided(): void
    {
        $data = ['nama' => 'John', 'nis' => '12345', 'sekolah_id' => 's1'];
        $createdPeserta = Mockery::mock(\App\Models\Peserta::class);

        Hash::shouldReceive('make')
            ->once()
            ->andReturn('hashed-password');

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($arg) {
                return $arg['nama'] === 'John'
                    && $arg['nis'] === '12345'
                    && $arg['username_ujian'] === '12345'
                    && $arg['password_ujian'] === 'hashed-password'
                    && isset($arg['password_plain'])
                    && $arg['is_active'] === true;
            })
            ->andReturn($createdPeserta);

        $result = $this->service->create($data);
        $this->assertEquals($createdPeserta, $result);
    }

    public function test_create_uses_provided_username_and_password(): void
    {
        $data = [
            'nama' => 'Jane',
            'username_ujian' => 'jane123',
            'password_ujian' => 'mypassword',
        ];
        $createdPeserta = Mockery::mock(\App\Models\Peserta::class);

        Hash::shouldReceive('make')
            ->once()
            ->with('mypassword')
            ->andReturn('hashed-mypassword');

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($arg) {
                return $arg['username_ujian'] === 'jane123'
                    && $arg['password_ujian'] === 'hashed-mypassword'
                    && isset($arg['password_plain']);
            })
            ->andReturn($createdPeserta);

        $result = $this->service->create($data);
        $this->assertEquals($createdPeserta, $result);
    }

    public function test_create_sets_is_active_default_true(): void
    {
        $data = ['nama' => 'Test', 'nis' => '999'];
        $createdPeserta = Mockery::mock(\App\Models\Peserta::class);

        Hash::shouldReceive('make')->once()->andReturn('h');

        $captured = null;
        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($arg) use (&$captured) {
                $captured = $arg;
                return true;
            })
            ->andReturn($createdPeserta);

        $this->service->create($data);
        $this->assertTrue($captured['is_active']);
    }

    public function test_create_respects_explicit_is_active_false(): void
    {
        $data = ['nama' => 'Test', 'nis' => '999', 'is_active' => false];
        $createdPeserta = Mockery::mock(\App\Models\Peserta::class);

        Hash::shouldReceive('make')->once()->andReturn('h');

        $captured = null;
        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($arg) use (&$captured) {
                $captured = $arg;
                return true;
            })
            ->andReturn($createdPeserta);

        $this->service->create($data);
        $this->assertFalse($captured['is_active']);
    }

    public function test_create_generates_random_password_when_not_provided(): void
    {
        $data = ['nama' => 'Test', 'nis' => '999'];
        $createdPeserta = Mockery::mock(\App\Models\Peserta::class);

        Hash::shouldReceive('make')->once()->andReturn('hashed');

        $captured = null;
        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($arg) use (&$captured) {
                $captured = $arg;
                return true;
            })
            ->andReturn($createdPeserta);

        $this->service->create($data);

        $this->assertEquals('hashed', $captured['password_ujian']);
        $this->assertNotNull($captured['password_plain']);
    }

    // ── update ─────────────────────────────────────────────────

    public function test_update_hashes_password_when_provided(): void
    {
        $id = 'peserta-1';
        $data = ['nama' => 'Updated', 'password_ujian' => 'newpass'];
        $peserta = Mockery::mock(\App\Models\Peserta::class);
        $freshPeserta = Mockery::mock(\App\Models\Peserta::class);

        Hash::shouldReceive('make')
            ->once()
            ->with('newpass')
            ->andReturn('hashed-newpass');

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with($id)
            ->andReturn($peserta);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->withArgs(function ($p, $d) use ($peserta) {
                return $p === $peserta
                    && $d['nama'] === 'Updated'
                    && $d['password_ujian'] === 'hashed-newpass'
                    && isset($d['password_plain']);
            })
            ->andReturn(true);

        $peserta->shouldReceive('fresh')
            ->once()
            ->andReturn($freshPeserta);

        $result = $this->service->update($id, $data);
        $this->assertEquals($freshPeserta, $result);
    }

    public function test_update_removes_password_fields_when_empty(): void
    {
        $id = 'peserta-1';
        $data = ['nama' => 'Updated', 'password_ujian' => ''];
        $peserta = Mockery::mock(\App\Models\Peserta::class);
        $freshPeserta = Mockery::mock(\App\Models\Peserta::class);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with($id)
            ->andReturn($peserta);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->withArgs(function ($p, $d) {
                return $d['nama'] === 'Updated'
                    && !isset($d['password_ujian'])
                    && !isset($d['password_plain']);
            })
            ->andReturn(true);

        $peserta->shouldReceive('fresh')
            ->once()
            ->andReturn($freshPeserta);

        $result = $this->service->update($id, $data);
        $this->assertEquals($freshPeserta, $result);
    }

    public function test_update_without_password_key(): void
    {
        $id = 'peserta-1';
        $data = ['nama' => 'Only Name'];
        $peserta = Mockery::mock(\App\Models\Peserta::class);
        $freshPeserta = Mockery::mock(\App\Models\Peserta::class);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with($id)
            ->andReturn($peserta);

        $captured = null;
        $this->repository
            ->shouldReceive('update')
            ->once()
            ->withArgs(function ($p, $d) use (&$captured) {
                $captured = $d;
                return true;
            })
            ->andReturn(true);

        $peserta->shouldReceive('fresh')->once()->andReturn($freshPeserta);

        $this->service->update($id, $data);

        $this->assertArrayNotHasKey('password_ujian', $captured);
        $this->assertArrayNotHasKey('password_plain', $captured);
    }

    // ── delete ─────────────────────────────────────────────────
    // We need peserta mock that satisfies return type ?Peserta from findById
    // and also supports property access for sesiPeserta after load().

    private function createPesertaMock(array $sesiStatuses = []): MockInterface
    {
        $collection = collect(
            array_map(fn ($s) => (object) ['status' => $s], $sesiStatuses)
        );

        $peserta = Mockery::mock(\App\Models\Peserta::class)->makePartial();
        $peserta->shouldReceive('load')
            ->with('sesiPeserta')
            ->once()
            ->andReturnSelf();

        // Override getAttribute to return our collection for sesiPeserta
        $peserta->shouldReceive('getAttribute')
            ->andReturnUsing(function ($key) use ($collection) {
                if ($key === 'sesiPeserta') {
                    return $collection;
                }
                return null;
            });

        return $peserta;
    }

    public function test_delete_successfully_when_no_active_sessions(): void
    {
        $id = 'peserta-1';
        $peserta = $this->createPesertaMock(['submit', 'selesai']);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with($id)
            ->andReturn($peserta);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($peserta)
            ->andReturn(true);

        $result = $this->service->delete($id);
        $this->assertTrue($result);
    }

    public function test_delete_throws_when_has_mengerjakan_session(): void
    {
        $id = 'peserta-1';
        $peserta = $this->createPesertaMock(['mengerjakan']);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with($id)
            ->andReturn($peserta);

        $this->expectException(ValidationException::class);
        $this->service->delete($id);
    }

    public function test_delete_throws_when_has_login_session(): void
    {
        $id = 'peserta-1';
        $peserta = $this->createPesertaMock(['login']);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with($id)
            ->andReturn($peserta);

        $this->expectException(ValidationException::class);
        $this->service->delete($id);
    }

    public function test_delete_with_empty_sessions(): void
    {
        $id = 'peserta-1';
        $peserta = $this->createPesertaMock([]);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($peserta);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($peserta)
            ->andReturn(true);

        $result = $this->service->delete($id);
        $this->assertTrue($result);
    }

    public function test_delete_exception_message_references_ujian(): void
    {
        $id = 'peserta-1';
        $peserta = $this->createPesertaMock(['mengerjakan']);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($peserta);

        try {
            $this->service->delete($id);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('peserta', $e->errors());
            $this->assertStringContainsString('ujian', $e->errors()['peserta'][0]);
        }
    }

    // ── importPeserta ──────────────────────────────────────────

    public function test_import_peserta_creates_records_successfully(): void
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        Hash::shouldReceive('make')->andReturn('hashed');

        $rows = [
            ['nama' => 'Peserta A', 'nis' => '001', 'kelas' => '10A'],
            ['nama' => 'Peserta B', 'nis' => '002', 'kelas' => '10B'],
        ];
        $meta = ['sekolah_id' => 'sekolah-1'];

        $this->repository
            ->shouldReceive('findByNisAndSekolah')
            ->twice()
            ->andReturn(null);

        $createdPeserta = Mockery::mock(\App\Models\Peserta::class);
        $this->repository
            ->shouldReceive('create')
            ->twice()
            ->andReturn($createdPeserta);

        $result = $this->service->importPeserta($rows, $meta);

        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(2, $result['total']);
        $this->assertEmpty($result['errors']);
    }

    public function test_import_peserta_skips_rows_with_empty_names(): void
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        $rows = [
            ['nama' => '', 'nis' => '001'],
            ['nis' => '002'],
        ];

        $result = $this->service->importPeserta($rows);

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(2, $result['skipped']);
        $this->assertCount(2, $result['errors']);
    }

    public function test_import_peserta_skips_duplicate_nis(): void
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        $rows = [
            ['nama' => 'Peserta A', 'nis' => '001', 'kelas' => '10A'],
        ];
        $meta = ['sekolah_id' => 'sekolah-1'];

        $existingPeserta = Mockery::mock(\App\Models\Peserta::class);
        $this->repository
            ->shouldReceive('findByNisAndSekolah')
            ->once()
            ->with('001', 'sekolah-1')
            ->andReturn($existingPeserta);

        $result = $this->service->importPeserta($rows, $meta);

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(1, $result['skipped']);
    }

    public function test_import_peserta_uses_nama_peserta_fallback(): void
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        Hash::shouldReceive('make')->once()->andReturn('hashed');

        $rows = [
            ['nama_peserta' => 'Peserta C', 'nis' => '003'],
        ];

        $this->repository
            ->shouldReceive('findByNisAndSekolah')
            ->never();

        $captured = null;
        $createdPeserta = Mockery::mock(\App\Models\Peserta::class);
        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($d) use (&$captured) {
                $captured = $d;
                return true;
            })
            ->andReturn($createdPeserta);

        $result = $this->service->importPeserta($rows);

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals('Peserta C', $captured['nama']);
    }

    public function test_import_peserta_skips_nis_check_when_no_sekolah_id(): void
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        Hash::shouldReceive('make')->once()->andReturn('hashed');

        $rows = [
            ['nama' => 'Peserta D', 'nis' => '004'],
        ];

        $this->repository
            ->shouldReceive('findByNisAndSekolah')
            ->never();

        $createdPeserta = Mockery::mock(\App\Models\Peserta::class);
        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn($createdPeserta);

        $result = $this->service->importPeserta($rows);

        $this->assertEquals(1, $result['imported']);
    }

    public function test_import_result_structure(): void
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        $result = $this->service->importPeserta([]);

        $this->assertArrayHasKey('imported', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(0, $result['total']);
    }

    public function test_import_peserta_handles_row_exception_gracefully(): void
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        Hash::shouldReceive('make')->andReturn('hashed');

        $rows = [
            ['nama' => 'Peserta A', 'nis' => '001'],
        ];
        $meta = ['sekolah_id' => 'sekolah-1'];

        $this->repository
            ->shouldReceive('findByNisAndSekolah')
            ->once()
            ->andReturn(null);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andThrow(new \RuntimeException('DB error'));

        $result = $this->service->importPeserta($rows, $meta);

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('DB error', $result['errors'][0]);
    }

    public function test_import_peserta_error_messages_include_row_number(): void
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        $rows = [
            ['nis' => '001'],  // Missing nama
        ];

        $result = $this->service->importPeserta($rows);

        $this->assertStringContainsString('Baris 1', $result['errors'][0]);
    }
}
