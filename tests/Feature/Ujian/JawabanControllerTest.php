<?php

namespace Tests\Feature\Ujian;

use App\Models\JawabanPeserta;
use App\Models\PaketSoal;
use App\Models\PaketUjian;
use App\Models\Peserta;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\Soal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class JawabanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveSession(): array
    {
        $peserta = Peserta::factory()->create();
        $paket = PaketUjian::factory()->aktif()->create(['durasi_menit' => 90]);
        $soal = Soal::factory()->pg()->create();
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        $sesi = SesiUjian::factory()->berlangsung()->create(['paket_id' => $paket->id]);
        $token = Str::random(64);
        $sp = SesiPeserta::factory()->mengerjakan()->create([
            'sesi_id'     => $sesi->id,
            'peserta_id'  => $peserta->id,
            'token_ujian' => $token,
            'mulai_at'    => now(),
        ]);

        return compact('peserta', 'paket', 'soal', 'sesi', 'sp', 'token');
    }

    public function test_sync_offline_with_valid_token(): void
    {
        $setup = $this->createActiveSession();

        $response = $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $setup['soal']->id,
                    'jawaban'         => ['A'],
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['synced', 'skipped', 'errors', 'server_time']);
        $this->assertEquals(1, $response->json('synced'));
    }

    public function test_sync_offline_idempotency(): void
    {
        $setup = $this->createActiveSession();
        $key = Str::uuid()->toString();

        // First sync
        $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $setup['soal']->id,
                    'jawaban'         => ['A'],
                    'idempotency_key' => $key,
                ],
            ],
        ]);

        // Second sync with same key
        $response = $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $setup['soal']->id,
                    'jawaban'         => ['B'],
                    'idempotency_key' => $key,
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertEquals(0, $response->json('synced'));
        $this->assertEquals(1, $response->json('skipped'));
    }

    public function test_sync_offline_with_invalid_token(): void
    {
        $response = $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => Str::random(64),
            'answers'    => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_sync_offline_with_essay_answer(): void
    {
        $setup = $this->createActiveSession();
        $soalEssay = Soal::factory()->essay()->create();
        PaketSoal::factory()->create(['paket_id' => $setup['paket']->id, 'soal_id' => $soalEssay->id]);

        $response = $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $soalEssay->id,
                    'jawaban'         => 'Ini adalah jawaban essay panjang...',
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('synced'));

        $jawaban = JawabanPeserta::where('soal_id', $soalEssay->id)->first();
        $this->assertEquals('Ini adalah jawaban essay panjang...', $jawaban->jawaban_teks);
    }

    public function test_status_endpoint(): void
    {
        $setup = $this->createActiveSession();

        $response = $this->getJson(route('api.ujian.status', ['token' => $setup['token']]));

        $response->assertOk();
        $response->assertJsonStructure([
            'status', 'elapsed_seconds', 'remaining_seconds',
            'soal_terjawab', 'server_timestamp', 'is_active',
        ]);
        $this->assertTrue($response->json('is_active'));
    }

    public function test_status_invalid_token_returns_404(): void
    {
        $response = $this->getJson(route('api.ujian.status', ['token' => 'invalidtoken']));
        $response->assertStatus(404);
    }

    public function test_submit_api(): void
    {
        $setup = $this->createActiveSession();

        $response = $this->postJson(route('api.ujian.submit', ['token' => $setup['token']]));

        $response->assertOk();
        $response->assertJsonStructure(['message', 'nilai_akhir', 'redirect']);

        $setup['sp']->refresh();
        $this->assertEquals('submit', $setup['sp']->status);
    }

    public function test_submit_api_with_answers(): void
    {
        $setup = $this->createActiveSession();

        $response = $this->postJson(route('api.ujian.submit', ['token' => $setup['token']]), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $setup['soal']->id,
                    'jawaban'         => ['A'],
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ]);

        $response->assertOk();
        $setup['sp']->refresh();
        $this->assertEquals('submit', $setup['sp']->status);
    }
}
