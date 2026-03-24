<?php

namespace Tests\Feature\Ujian;

use App\Models\JawabanPeserta;
use App\Models\PaketSoal;
use App\Models\PaketUjian;
use App\Models\Peserta;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Models\OpsiJawaban;
use App\Models\PasanganSoal;
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

        $response->assertStatus(401);
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

    public function test_sync_offline_updates_full_benar_salah_answer_when_idempotency_changes(): void
    {
        $setup = $this->createActiveSession();
        $soalBs = Soal::factory()->benarSalah()->create();
        PaketSoal::factory()->create(['paket_id' => $setup['paket']->id, 'soal_id' => $soalBs->id]);
        OpsiJawaban::factory()->create(['soal_id' => $soalBs->id, 'label' => 'A', 'is_benar' => true]);
        OpsiJawaban::factory()->create(['soal_id' => $soalBs->id, 'label' => 'B', 'is_benar' => false]);

        $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $soalBs->id,
                    'jawaban'         => ['A' => 'benar'],
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ])->assertOk();

        $response = $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $soalBs->id,
                    'jawaban'         => ['A' => 'benar', 'B' => 'salah'],
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('synced'));

        $jawaban = JawabanPeserta::where('soal_id', $soalBs->id)->first();
        $this->assertSame(['A' => 'benar', 'B' => 'salah'], $jawaban->jawaban_pg);
    }

    public function test_sync_offline_accepts_string_pair_ids_for_menjodohkan_answers(): void
    {
        $setup = $this->createActiveSession();
        $soalMenjodohkan = Soal::factory()->menjodohkan()->create();
        PaketSoal::factory()->create(['paket_id' => $setup['paket']->id, 'soal_id' => $soalMenjodohkan->id]);
        $pairA = PasanganSoal::factory()->create(['soal_id' => $soalMenjodohkan->id]);
        $pairB = PasanganSoal::factory()->create(['soal_id' => $soalMenjodohkan->id]);

        $response = $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $soalMenjodohkan->id,
                    'jawaban'         => [[$pairA->id, $pairA->id], [$pairB->id, $pairB->id]],
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('synced'));

        $jawaban = JawabanPeserta::where('soal_id', $soalMenjodohkan->id)->first();
        $this->assertSame([[$pairA->id, $pairA->id], [$pairB->id, $pairB->id]], $jawaban->jawaban_pasangan);
    }

    public function test_sync_offline_can_clear_existing_pg_answer_with_explicit_empty_payload(): void
    {
        $setup = $this->createActiveSession();

        $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $setup['soal']->id,
                    'jawaban'         => ['A'],
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ])->assertOk();

        $clearResponse = $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $setup['soal']->id,
                    'jawaban'         => '',
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ]);

        $clearResponse->assertOk();
        $this->assertEquals(1, $clearResponse->json('synced'));

        $jawaban = JawabanPeserta::where('soal_id', $setup['soal']->id)->first();
        $this->assertSame('', $jawaban->jawaban_teks);
        $this->assertNull($jawaban->jawaban_pg);
        $this->assertNull($jawaban->jawaban_pasangan);
        $this->assertFalse($jawaban->is_terjawab);
    }

    public function test_sync_offline_can_clear_existing_benar_salah_answer_with_explicit_empty_payload(): void
    {
        $setup = $this->createActiveSession();
        $soalBs = Soal::factory()->benarSalah()->create();
        PaketSoal::factory()->create(['paket_id' => $setup['paket']->id, 'soal_id' => $soalBs->id]);
        OpsiJawaban::factory()->create(['soal_id' => $soalBs->id, 'label' => 'A', 'is_benar' => true]);

        $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $soalBs->id,
                    'jawaban'         => ['A' => 'benar'],
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ])->assertOk();

        $clearResponse = $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $soalBs->id,
                    'jawaban'         => '',
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ]);

        $clearResponse->assertOk();
        $this->assertEquals(1, $clearResponse->json('synced'));

        $jawaban = JawabanPeserta::where('soal_id', $soalBs->id)->first();
        $this->assertSame('', $jawaban->jawaban_teks);
        $this->assertNull($jawaban->jawaban_pg);
        $this->assertNull($jawaban->jawaban_pasangan);
        $this->assertFalse($jawaban->is_terjawab);
    }

    public function test_sync_offline_can_clear_existing_pg_kompleks_answer_with_explicit_empty_payload(): void
    {
        $setup = $this->createActiveSession();
        $soalPgk = Soal::factory()->pgKompleks()->create();
        PaketSoal::factory()->create(['paket_id' => $setup['paket']->id, 'soal_id' => $soalPgk->id]);
        OpsiJawaban::factory()->create(['soal_id' => $soalPgk->id, 'label' => 'A', 'is_benar' => true]);
        OpsiJawaban::factory()->create(['soal_id' => $soalPgk->id, 'label' => 'B', 'is_benar' => false]);

        $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $soalPgk->id,
                    'jawaban'         => ['A', 'B'],
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ])->assertOk();

        $clearResponse = $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $soalPgk->id,
                    'jawaban'         => '',
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ]);

        $clearResponse->assertOk();
        $this->assertEquals(1, $clearResponse->json('synced'));

        $jawaban = JawabanPeserta::where('soal_id', $soalPgk->id)->first();
        $this->assertSame('', $jawaban->jawaban_teks);
        $this->assertNull($jawaban->jawaban_pg);
        $this->assertNull($jawaban->jawaban_pasangan);
        $this->assertFalse($jawaban->is_terjawab);
    }

    public function test_sync_offline_can_clear_existing_menjodohkan_answer_with_explicit_empty_payload(): void
    {
        $setup = $this->createActiveSession();
        $soalMenjodohkan = Soal::factory()->menjodohkan()->create();
        PaketSoal::factory()->create(['paket_id' => $setup['paket']->id, 'soal_id' => $soalMenjodohkan->id]);
        $pairA = PasanganSoal::factory()->create(['soal_id' => $soalMenjodohkan->id]);

        $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $soalMenjodohkan->id,
                    'jawaban'         => [[$pairA->id, $pairA->id]],
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ])->assertOk();

        $clearResponse = $this->postJson(route('api.ujian.sync'), [
            'sesi_token' => $setup['token'],
            'answers'    => [
                [
                    'soal_id'         => $soalMenjodohkan->id,
                    'jawaban'         => '',
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ]);

        $clearResponse->assertOk();
        $this->assertEquals(1, $clearResponse->json('synced'));

        $jawaban = JawabanPeserta::where('soal_id', $soalMenjodohkan->id)->first();
        $this->assertSame('', $jawaban->jawaban_teks);
        $this->assertNull($jawaban->jawaban_pg);
        $this->assertNull($jawaban->jawaban_pasangan);
        $this->assertFalse($jawaban->is_terjawab);
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

    public function test_status_invalid_token_returns_401(): void
    {
        $response = $this->getJson(route('api.ujian.status', ['token' => 'invalidtoken']));
        $response->assertStatus(401);
    }

    public function test_sync_offline_rejects_routine_sync_after_exam_time_expires(): void
    {
        $setup = $this->createActiveSession();
        $setup['sp']->update(['mulai_at' => now()->subMinutes(91)]);

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

        $response->assertStatus(422);
        $response->assertJson([
            'accepted' => false,
            'synced'   => 0,
            'errors'   => ['Waktu ujian telah habis'],
        ]);
        $this->assertDatabaseMissing('jawaban_peserta', [
            'sesi_peserta_id' => $setup['sp']->id,
            'soal_id'         => $setup['soal']->id,
        ]);
    }

    public function test_sync_offline_allows_final_submit_sync_after_exam_time_expires(): void
    {
        $setup = $this->createActiveSession();
        $setup['sp']->update(['mulai_at' => now()->subMinutes(91)]);

        $response = $this->postJson(route('api.ujian.sync'), [
            'sesi_token'   => $setup['token'],
            'final_submit' => true,
            'answers'      => [
                [
                    'soal_id'         => $setup['soal']->id,
                    'jawaban'         => ['A'],
                    'idempotency_key' => Str::uuid()->toString(),
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'accepted' => true,
            'synced'   => 1,
        ]);
        $this->assertDatabaseHas('jawaban_peserta', [
            'sesi_peserta_id' => $setup['sp']->id,
            'soal_id'         => $setup['soal']->id,
            'is_terjawab'     => true,
        ]);
    }

    public function test_submit_api(): void
    {
        $setup = $this->createActiveSession();

        $response = $this->postJson(route('api.ujian.submit', ['token' => $setup['token']]));

        $response->assertOk();
        $response->assertJsonStructure(['message', 'redirect']);

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
