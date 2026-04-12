<?php

namespace Tests\Feature\Ujian;

use App\Models\KategoriSoal;
use App\Models\NarasiSoal;
use App\Models\OpsiJawaban;
use App\Models\PaketSoal;
use App\Models\PaketUjian;
use App\Models\Peserta;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Models\User;
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
        $response = $this->get(route('ujian.konfirmasi', $sp));
        $response->assertRedirect(route('ujian.login'));
    }

    public function test_peserta_cannot_access_other_peserta_konfirmasi(): void
    {
        $setup = $this->createUjianSetup();
        $otherPeserta = Peserta::factory()->create();

        $response = $this->actingAs($otherPeserta, 'peserta')
            ->get(route('ujian.konfirmasi', $setup['sp']));

        $response->assertStatus(403);
    }

    public function test_konfirmasi_page_shows_desktop_only_fullscreen_notice(): void
    {
        $setup = $this->createUjianSetup();

        $response = $this->actingAs($setup['peserta'], 'peserta')
            ->get(route('ujian.konfirmasi', $setup['sp']));

        $response->assertOk();
        $response->assertViewIs('ujian.konfirmasi');
        $response->assertSee('Perhatian sebelum memulai ujian');
        $response->assertSee('Pada perangkat <strong>desktop</strong>', false);
        $response->assertSee('data-start-exam="true"', false);
        $response->assertSee('ujian-pending-fullscreen', false);
        $response->assertDontSee('activateDesktopFullscreen', false);
        $response->assertDontSee('ujian-skip-init-fullscreen');
    }

    public function test_konfirmasi_allows_terdaftar_status_to_start_exam(): void
    {
        $setup = $this->createUjianSetup();
        $setup['sp']->update(['status' => 'terdaftar']);

        $response = $this->actingAs($setup['peserta'], 'peserta')
            ->get(route('ujian.konfirmasi', $setup['sp']));

        $response->assertOk();
        $response->assertViewIs('ujian.konfirmasi');
    }

    public function test_mengerjakan_starts_ujian_and_sets_status(): void
    {
        $setup = $this->createUjianSetup();

        $response = $this->actingAs($setup['peserta'], 'peserta')
            ->get(route('ujian.mengerjakan', $setup['sp']));

        $response->assertStatus(200);
        $response->assertViewIs('ujian.soal');
        $response->assertSee('serverTimestamp', false);
        $response->assertSee('showStartFullscreenOverlay', false);
        $response->assertSee('enterExamFullscreen()', false);

        $setup['sp']->refresh();
        $this->assertEquals('mengerjakan', $setup['sp']->status);
        $this->assertNotNull($setup['sp']->mulai_at);
        $this->assertNotNull($setup['sp']->token_ujian);
    }

    public function test_mengerjakan_allows_terdaftar_status_and_generates_token(): void
    {
        $setup = $this->createUjianSetup();
        $setup['sp']->update(['status' => 'terdaftar', 'token_ujian' => null, 'mulai_at' => null]);

        $response = $this->actingAs($setup['peserta'], 'peserta')
            ->get(route('ujian.mengerjakan', $setup['sp']));

        $response->assertOk();
        $response->assertViewIs('ujian.soal');

        $setup['sp']->refresh();
        $this->assertEquals('mengerjakan', $setup['sp']->status);
        $this->assertNotNull($setup['sp']->token_ujian);
        $this->assertNotNull($setup['sp']->mulai_at);
    }

    public function test_submitted_konfirmasi_redirects_to_selesai(): void
    {
        $setup = $this->createUjianSetup();
        $setup['sp']->update(['status' => 'submit', 'submit_at' => now()]);

        $response = $this->actingAs($setup['peserta'], 'peserta')
            ->get(route('ujian.konfirmasi', $setup['sp']));

        $response->assertRedirect(route('ujian.selesai', $setup['sp']));
    }

    public function test_mengerjakan_decodes_html_entities_for_narasi_soal_and_opsi(): void
    {
        $peserta = Peserta::factory()->create();
        $paket = PaketUjian::factory()->aktif()->create(['durasi_menit' => 90]);
        $kategori = KategoriSoal::factory()->create();
        $narasi = NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => User::factory()->create()->id,
            'judul' => 'Narasi ujian',
            'konten' => '&lt;p&gt;Narasi &quot;ujian&quot; untuk peserta.&lt;/p&gt;',
            'is_active' => true,
        ]);
        $soal = Soal::factory()->pg()->create([
            'kategori_id' => $kategori->id,
            'narasi_id' => $narasi->id,
            'pertanyaan' => 'Pilih arti &quot;tepat&quot;.',
        ]);
        OpsiJawaban::create([
            'soal_id' => $soal->id,
            'label' => 'A',
            'teks' => 'Jawaban &quot;benar&quot;.',
            'urutan' => 1,
            'is_benar' => true,
        ]);
        OpsiJawaban::create([
            'soal_id' => $soal->id,
            'label' => 'B',
            'teks' => 'Jawaban lain',
            'urutan' => 2,
            'is_benar' => false,
        ]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        $sesi = SesiUjian::factory()->berlangsung()->create(['paket_id' => $paket->id]);
        $sp = SesiPeserta::factory()->create([
            'sesi_id' => $sesi->id,
            'peserta_id' => $peserta->id,
            'status' => 'login',
        ]);

        $response = $this->actingAs($peserta, 'peserta')
            ->get(route('ujian.mengerjakan', $sp));

        $response->assertOk();
        $response->assertViewIs('ujian.soal');
        $response->assertSee('Narasi "ujian" untuk peserta.', false);
        $response->assertSee('Pilih arti "tepat".', false);
        $response->assertSee('Jawaban "benar".', false);
        $response->assertDontSee('&lt;p&gt;Narasi &quot;ujian&quot; untuk peserta.&lt;/p&gt;', false);
        $response->assertDontSee('Pilih arti &quot;tepat&quot;.', false);
    }

    public function test_mengerjakan_keeps_literal_encoded_tags_visible_as_text(): void
    {
        $peserta = Peserta::factory()->create();
        $paket = PaketUjian::factory()->aktif()->create(['durasi_menit' => 90]);
        $kategori = KategoriSoal::factory()->create();
        $soal = Soal::factory()->pg()->create([
            'kategori_id' => $kategori->id,
            'pertanyaan' => '<p>Apa fungsi tag &lt;option&gt;?</p>',
        ]);
        OpsiJawaban::create([
            'soal_id' => $soal->id,
            'label' => 'A',
            'teks' => 'Contoh literal &lt;img src="https://example.com/pixel.png"&gt;.',
            'urutan' => 1,
            'is_benar' => true,
        ]);
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        $sesi = SesiUjian::factory()->berlangsung()->create(['paket_id' => $paket->id]);
        $sp = SesiPeserta::factory()->create([
            'sesi_id' => $sesi->id,
            'peserta_id' => $peserta->id,
            'status' => 'login',
        ]);

        $response = $this->actingAs($peserta, 'peserta')
            ->get(route('ujian.mengerjakan', $sp));

        $response->assertOk();
        $response->assertSee('Apa fungsi tag &lt;option&gt;?', false);
        $response->assertSee('Contoh literal &lt;img src="https://example.com/pixel.png"&gt;.', false);
        $response->assertDontSee('<option>', false);
        $response->assertDontSee('<img src="https://example.com/pixel.png">', false);
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
