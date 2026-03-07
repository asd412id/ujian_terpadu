<?php

namespace Tests\Feature\Ujian;

use App\Models\PaketSoal;
use App\Models\PaketUjian;
use App\Models\Peserta;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\Soal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UjianControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUjianSetup(): array
    {
        $peserta = Peserta::factory()->create();
        $paket = PaketUjian::factory()->aktif()->create(['durasi_menit' => 90]);
        $soal = Soal::factory()->pg()->create();
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        $sesi = SesiUjian::factory()->berlangsung()->create(['paket_id' => $paket->id]);
        $sp = SesiPeserta::factory()->create([
            'sesi_id'    => $sesi->id,
            'peserta_id' => $peserta->id,
            'status'     => 'login',
        ]);

        return compact('peserta', 'paket', 'soal', 'sesi', 'sp');
    }

    public function test_unauthenticated_peserta_redirected(): void
    {
        $sp = SesiPeserta::factory()->create();
        $response = $this->get(route('ujian.mulai', $sp));
        $response->assertRedirect(route('ujian.login'));
    }

    public function test_peserta_cannot_access_other_peserta_ujian(): void
    {
        $setup = $this->createUjianSetup();
        $otherPeserta = Peserta::factory()->create();

        $response = $this->actingAs($otherPeserta, 'peserta')
            ->get(route('ujian.mulai', $setup['sp']));

        $response->assertStatus(403);
    }

    public function test_index_starts_ujian_and_sets_status(): void
    {
        $setup = $this->createUjianSetup();

        $response = $this->actingAs($setup['peserta'], 'peserta')
            ->get(route('ujian.mulai', $setup['sp']));

        $response->assertStatus(200);
        $response->assertViewIs('ujian.soal');

        $setup['sp']->refresh();
        $this->assertEquals('mengerjakan', $setup['sp']->status);
        $this->assertNotNull($setup['sp']->mulai_at);
        $this->assertNotNull($setup['sp']->token_ujian);
    }

    public function test_submitted_ujian_redirects_to_selesai(): void
    {
        $setup = $this->createUjianSetup();
        $setup['sp']->update(['status' => 'submit', 'submit_at' => now()]);

        $response = $this->actingAs($setup['peserta'], 'peserta')
            ->get(route('ujian.mulai', $setup['sp']));

        $response->assertRedirect(route('ujian.selesai', $setup['sp']));
    }

    public function test_submit_ujian(): void
    {
        $setup = $this->createUjianSetup();
        $setup['sp']->update([
            'status'   => 'mengerjakan',
            'mulai_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($setup['peserta'], 'peserta')
            ->post(route('ujian.submit', $setup['sp']));

        $response->assertRedirect(route('ujian.selesai', $setup['sp']));

        $setup['sp']->refresh();
        $this->assertEquals('submit', $setup['sp']->status);
        $this->assertNotNull($setup['sp']->submit_at);
    }

    public function test_submit_already_submitted_redirects(): void
    {
        $setup = $this->createUjianSetup();
        $setup['sp']->update(['status' => 'submit', 'submit_at' => now()]);

        $response = $this->actingAs($setup['peserta'], 'peserta')
            ->post(route('ujian.submit', $setup['sp']));

        $response->assertRedirect(route('ujian.selesai', $setup['sp']));
    }

    public function test_selesai_page_shows_summary(): void
    {
        $setup = $this->createUjianSetup();
        $setup['sp']->update([
            'status'    => 'submit',
            'mulai_at'  => now()->subMinutes(30),
            'submit_at' => now(),
        ]);

        $response = $this->actingAs($setup['peserta'], 'peserta')
            ->get(route('ujian.selesai', $setup['sp']));

        $response->assertStatus(200);
        $response->assertViewIs('ujian.selesai');
        $response->assertViewHas('ringkasan');
    }
}
