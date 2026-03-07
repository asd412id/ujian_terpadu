<?php

namespace Tests\Unit\Models;

use App\Models\ImportJob;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_import_job(): void
    {
        $job = ImportJob::factory()->create();
        $this->assertDatabaseHas('import_jobs', ['id' => $job->id]);
    }

    public function test_fillable_attributes(): void
    {
        $job = new ImportJob();
        $expected = [
            'created_by', 'sekolah_id', 'tipe', 'filename', 'filepath',
            'status', 'total_rows', 'processed_rows', 'success_rows',
            'error_rows', 'errors', 'catatan', 'started_at', 'completed_at',
        ];
        $this->assertEquals($expected, $job->getFillable());
    }

    public function test_errors_cast_to_array(): void
    {
        $errors = [['row' => 1, 'message' => 'NIS kosong']];
        $job = ImportJob::factory()->create(['errors' => $errors]);

        $job->refresh();
        $this->assertIsArray($job->errors);
        $this->assertCount(1, $job->errors);
        $this->assertEquals('NIS kosong', $job->errors[0]['message']);
    }

    public function test_datetime_casts(): void
    {
        $job = ImportJob::factory()->processing()->create();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $job->started_at);
    }

    public function test_creator_relationship(): void
    {
        $user = User::factory()->create();
        $job = ImportJob::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $job->creator);
    }

    public function test_sekolah_relationship(): void
    {
        $sekolah = Sekolah::factory()->create();
        $job = ImportJob::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->assertInstanceOf(Sekolah::class, $job->sekolah);
    }

    public function test_processing_factory_state(): void
    {
        $job = ImportJob::factory()->processing()->create();
        $this->assertEquals('processing', $job->status);
        $this->assertNotNull($job->started_at);
    }

    public function test_selesai_factory_state(): void
    {
        $job = ImportJob::factory()->selesai()->create();
        $this->assertEquals('selesai', $job->status);
        $this->assertNotNull($job->completed_at);
    }
}
