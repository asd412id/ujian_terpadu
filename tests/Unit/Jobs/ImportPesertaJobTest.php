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

    private function createImportJob(Sekolah $sekolah = null): ImportJob
    {
        $sekolah ??= Sekolah::factory()->create();
        return ImportJob::factory()->create([
            'sekolah_id' => $sekolah->id,
            'tipe'       => 'peserta_excel',
            'filepath'   => 'imports/test_peserta.xlsx',
            'status'     => 'pending',
        ]);
    }

    public function test_successful_import(): void
    {
        $importJob = $this->createImportJob();

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

    public function test_import_with_empty_name_error(): void
    {
        $importJob = $this->createImportJob();

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

    public function test_import_with_duplicate_nis(): void
    {
        $sekolah = Sekolah::factory()->create();
        Peserta::factory()->create(['sekolah_id' => $sekolah->id, 'nis' => '111111']);

        $importJob = $this->createImportJob($sekolah);

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

    public function test_status_changes_to_processing(): void
    {
        $importJob = $this->createImportJob();

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

    public function test_peserta_gets_username_and_password(): void
    {
        $importJob = $this->createImportJob();

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
}
