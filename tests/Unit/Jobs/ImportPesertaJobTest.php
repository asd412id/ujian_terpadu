<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ImportPesertaJob;
use App\Models\ImportJob;
use App\Models\Peserta;
use App\Models\Sekolah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ImportPesertaJobTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================
    // SEKOLAH IMPORT TESTS (format lama tanpa NPSN)
    // =========================================================

    private function createSekolahImportJob(Sekolah $sekolah = null): ImportJob
    {
        $sekolah ??= Sekolah::factory()->create();
        return ImportJob::factory()->create([
            'sekolah_id' => $sekolah->id,
            'tipe'       => 'peserta_excel',
            'filepath'   => 'imports/test_peserta.xlsx',
            'status'     => 'pending',
        ]);
    }

    public function test_sekolah_successful_import(): void
    {
        $importJob = $this->createSekolahImportJob();

        Storage::fake('local');
        Storage::disk('local')->put('imports/test_peserta.xlsx', 'dummy');

        $rows = [
            ['nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'],
            ['Budi Santoso', '111111', '1111111111', 'XII-1', 'IPA', 'L', '2005-05-15'],
            ['Ani Wijaya', '222222', '2222222222', 'XII-2', 'IPS', 'P', '2005-08-20'],
        ];

        Excel::shouldReceive('toArray')
            ->once()
            ->andReturn([$rows]);

        $job = new ImportPesertaJob($importJob);
        $job->handle();

        $importJob->refresh();
        $this->assertEquals('selesai', $importJob->status);
        $this->assertEquals(2, $importJob->total_rows);
        $this->assertEquals(2, $importJob->success_rows);
        $this->assertEquals(0, $importJob->error_rows);
        $this->assertNotNull($importJob->completed_at);

        $this->assertDatabaseHas('peserta', ['nama' => 'Budi Santoso', 'nis' => '111111']);
        $this->assertDatabaseHas('peserta', ['nama' => 'Ani Wijaya', 'nis' => '222222']);
    }

    public function test_sekolah_import_with_empty_name_error(): void
    {
        $importJob = $this->createSekolahImportJob();

        Storage::fake('local');
        Storage::disk('local')->put('imports/test_peserta.xlsx', 'dummy');

        $rows = [
            ['nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'],
            ['', '111111', '1111111111', 'XII-1', 'IPA', 'L', '2005-05-15'],
        ];

        Excel::shouldReceive('toArray')
            ->once()
            ->andReturn([$rows]);

        $job = new ImportPesertaJob($importJob);
        $job->handle();

        $importJob->refresh();
        $this->assertEquals('selesai', $importJob->status);
        $this->assertEquals(0, $importJob->success_rows);
        $this->assertEquals(1, $importJob->error_rows);
        $this->assertNotEmpty($importJob->errors);
        $this->assertStringContainsString('Nama tidak boleh kosong', $importJob->errors[0]['pesan']);
    }

    public function test_sekolah_import_with_duplicate_nis(): void
    {
        $sekolah = Sekolah::factory()->create();
        Peserta::factory()->create(['sekolah_id' => $sekolah->id, 'nis' => '111111']);

        $importJob = $this->createSekolahImportJob($sekolah);

        Storage::fake('local');
        Storage::disk('local')->put('imports/test_peserta.xlsx', 'dummy');

        $rows = [
            ['nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'],
            ['Another Budi', '111111', '9999999999', 'XII-1', 'IPA', 'L', '2005-05-15'],
        ];

        Excel::shouldReceive('toArray')
            ->once()
            ->andReturn([$rows]);

        $job = new ImportPesertaJob($importJob);
        $job->handle();

        $importJob->refresh();
        $this->assertEquals(0, $importJob->success_rows);
        $this->assertEquals(1, $importJob->error_rows);
        $this->assertStringContainsString('sudah terdaftar', $importJob->errors[0]['pesan']);
    }

    public function test_sekolah_status_changes_to_processing(): void
    {
        $importJob = $this->createSekolahImportJob();

        Storage::fake('local');
        Storage::disk('local')->put('imports/test_peserta.xlsx', 'dummy');

        $rows = [['nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir']];

        Excel::shouldReceive('toArray')
            ->once()
            ->andReturn([$rows]);

        $job = new ImportPesertaJob($importJob);
        $job->handle();

        $importJob->refresh();
        $this->assertEquals('selesai', $importJob->status);
        $this->assertNotNull($importJob->started_at);
    }

    public function test_sekolah_peserta_gets_username_and_password(): void
    {
        $importJob = $this->createSekolahImportJob();

        Storage::fake('local');
        Storage::disk('local')->put('imports/test_peserta.xlsx', 'dummy');

        $rows = [
            ['nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'],
            ['Test User', '999888', null, 'XII-1', null, null, null],
        ];

        Excel::shouldReceive('toArray')
            ->once()
            ->andReturn([$rows]);

        $job = new ImportPesertaJob($importJob);
        $job->handle();

        $peserta = Peserta::where('nis', '999888')->first();
        $this->assertNotNull($peserta);
        $this->assertNotNull($peserta->username_ujian);
        $this->assertNotNull($peserta->password_ujian);
        $this->assertNotNull($peserta->password_plain);
    }

    // =========================================================
    // DINAS IMPORT TESTS (format baru dengan NPSN)
    // =========================================================

    private function createDinasImportJob(): ImportJob
    {
        return ImportJob::factory()->create([
            'sekolah_id' => null,
            'tipe'       => 'peserta_excel',
            'filepath'   => 'imports/test_peserta_dinas.xlsx',
            'status'     => 'pending',
            'meta'       => ['mode' => 'update', 'source' => 'dinas'],
        ]);
    }

    public function test_dinas_successful_import_multi_school(): void
    {
        $sekolahA = Sekolah::factory()->create(['npsn' => '20100001']);
        $sekolahB = Sekolah::factory()->create(['npsn' => '20100002']);

        $importJob = $this->createDinasImportJob();

        Storage::fake('local');
        Storage::disk('local')->put('imports/test_peserta_dinas.xlsx', 'dummy');

        $rows = [
            ['npsn', 'nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'],
            ['20100001', 'Budi Santoso', '111111', '1111111111', 'XII-1', 'IPA', 'L', '2005-05-15'],
            ['20100001', 'Ani Wijaya', '222222', '2222222222', 'XII-2', 'IPS', 'P', '2005-08-20'],
            ['20100002', 'Charlie Putra', '333333', '3333333333', 'XI-1', 'IPA', 'L', '2006-01-10'],
        ];

        Excel::shouldReceive('toArray')
            ->once()
            ->andReturn([$rows]);

        $job = new ImportPesertaJob($importJob);
        $job->handle();

        $importJob->refresh();
        $this->assertEquals('selesai', $importJob->status);
        $this->assertEquals(3, $importJob->total_rows);
        $this->assertEquals(3, $importJob->success_rows);
        $this->assertEquals(0, $importJob->error_rows);

        $this->assertDatabaseHas('peserta', ['nama' => 'Budi Santoso', 'sekolah_id' => $sekolahA->id]);
        $this->assertDatabaseHas('peserta', ['nama' => 'Ani Wijaya', 'sekolah_id' => $sekolahA->id]);
        $this->assertDatabaseHas('peserta', ['nama' => 'Charlie Putra', 'sekolah_id' => $sekolahB->id]);
    }

    public function test_dinas_import_with_unknown_npsn(): void
    {
        Sekolah::factory()->create(['npsn' => '20100001']);

        $importJob = $this->createDinasImportJob();

        Storage::fake('local');
        Storage::disk('local')->put('imports/test_peserta_dinas.xlsx', 'dummy');

        $rows = [
            ['npsn', 'nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'],
            ['20100001', 'Budi Santoso', '111111', null, 'XII-1', null, 'L', null],
            ['99999999', 'Unknown School', '222222', null, 'XII-1', null, 'P', null],
        ];

        Excel::shouldReceive('toArray')
            ->once()
            ->andReturn([$rows]);

        $job = new ImportPesertaJob($importJob);
        $job->handle();

        $importJob->refresh();
        $this->assertEquals(1, $importJob->success_rows);
        $this->assertEquals(1, $importJob->error_rows);
        $this->assertStringContainsString('tidak ditemukan', $importJob->errors[0]['pesan']);
    }

    public function test_dinas_import_with_empty_npsn(): void
    {
        $importJob = $this->createDinasImportJob();

        Storage::fake('local');
        Storage::disk('local')->put('imports/test_peserta_dinas.xlsx', 'dummy');

        $rows = [
            ['npsn', 'nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'],
            ['', 'No NPSN User', '111111', null, 'XII-1', null, 'L', null],
        ];

        Excel::shouldReceive('toArray')
            ->once()
            ->andReturn([$rows]);

        $job = new ImportPesertaJob($importJob);
        $job->handle();

        $importJob->refresh();
        $this->assertEquals(0, $importJob->success_rows);
        $this->assertEquals(1, $importJob->error_rows);
        $this->assertStringContainsString('NPSN tidak boleh kosong', $importJob->errors[0]['pesan']);
    }

    public function test_dinas_replace_all_deletes_per_npsn(): void
    {
        $sekolahA = Sekolah::factory()->create(['npsn' => '20100001']);
        $sekolahB = Sekolah::factory()->create(['npsn' => '20100002']);

        // Existing peserta
        Peserta::factory()->create(['sekolah_id' => $sekolahA->id, 'nama' => 'Old A1']);
        Peserta::factory()->create(['sekolah_id' => $sekolahA->id, 'nama' => 'Old A2']);
        Peserta::factory()->create(['sekolah_id' => $sekolahB->id, 'nama' => 'Old B1']);

        $importJob = ImportJob::factory()->create([
            'sekolah_id' => null,
            'tipe'       => 'peserta_excel',
            'filepath'   => 'imports/test_peserta_dinas.xlsx',
            'status'     => 'pending',
            'meta'       => ['mode' => 'replace_all', 'source' => 'dinas'],
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('imports/test_peserta_dinas.xlsx', 'dummy');

        $rows = [
            ['npsn', 'nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'],
            ['20100001', 'New A1', '111111', null, 'XII-1', null, 'L', null],
        ];

        Excel::shouldReceive('toArray')
            ->once()
            ->andReturn([$rows]);

        $job = new ImportPesertaJob($importJob);
        $job->handle();

        $importJob->refresh();
        $this->assertEquals(1, $importJob->success_rows);

        // Old peserta from sekolah A should be deleted, replaced with new
        $this->assertDatabaseMissing('peserta', ['nama' => 'Old A1']);
        $this->assertDatabaseMissing('peserta', ['nama' => 'Old A2']);
        $this->assertDatabaseHas('peserta', ['nama' => 'New A1', 'sekolah_id' => $sekolahA->id]);

        // Sekolah B NOT in file, so Old B1 should still exist
        $this->assertDatabaseHas('peserta', ['nama' => 'Old B1', 'sekolah_id' => $sekolahB->id]);
    }

    public function test_dinas_header_validation_rejects_sekolah_format(): void
    {
        $importJob = $this->createDinasImportJob();

        Storage::fake('local');
        Storage::disk('local')->put('imports/test_peserta_dinas.xlsx', 'dummy');

        // Using sekolah format (without NPSN) for dinas import should fail
        $rows = [
            ['nama', 'nis', 'nisn', 'kelas', 'jurusan', 'jenis_kelamin', 'tanggal_lahir'],
            ['Budi', '111111', null, null, null, null, null],
        ];

        Excel::shouldReceive('toArray')
            ->once()
            ->andReturn([$rows]);

        $job = new ImportPesertaJob($importJob);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Template tidak sesuai');

        $job->handle();
    }
}
