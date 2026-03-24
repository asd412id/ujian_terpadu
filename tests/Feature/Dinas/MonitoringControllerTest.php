<?php

namespace Tests\Feature\Dinas;

use App\Models\JawabanPeserta;
use App\Models\LogAktivitasUjian;
use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MonitoringControllerTest extends TestCase
{
    use RefreshDatabase;

    private function dinasUser(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }

    public function test_index_returns_monitoring_page(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->get(route('dinas.monitoring'));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.monitoring.index');
        $response->assertViewHas(['sekolahList', 'sesiList', 'summary']);
    }

    public function test_sekolah_monitoring(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create();

        $response = $this->actingAs($user)->get(route('dinas.monitoring.sekolah', $sekolah));

        $response->assertStatus(200);
    }

    public function test_sesi_monitoring_detail(): void
    {
        $user = $this->dinasUser();
        $sesi = SesiUjian::factory()->berlangsung()->create();

        $response = $this->actingAs($user)->get(route('dinas.monitoring.sesi', $sesi));

        $response->assertStatus(200);
        $response->assertViewHas(['sesi', 'alerts', 'pesertaList', 'stats']);
    }

    public function test_api_index_returns_json(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->getJson(route('dinas.monitoring.api'));

        $response->assertOk();
        $response->assertJsonStructure(['sesiList', 'summary']);
    }

    public function test_api_sesi_returns_json(): void
    {
        $user = $this->dinasUser();
        $sesi = SesiUjian::factory()->create();

        $response = $this->actingAs($user)->getJson(route('dinas.monitoring.sesi.api', $sesi));

        $response->assertOk();
        $response->assertJsonStructure(['stats', 'peserta_live']);
    }

    public function test_monitoring_reset_modal_uses_explicit_submit_handler(): void
    {
        $user = $this->dinasUser();
        $sesi = SesiUjian::factory()->berlangsung()->create();

        $response = $this->actingAs($user)->get(route('dinas.monitoring.sesi', $sesi));

        $response->assertOk();
        $response->assertSee('@keydown.tab.prevent="trapResetFocus($event)"', false);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('aria-labelledby="reset-modal-title"', false);
        $response->assertSee('x-ref="resetDialog"', false);
        $response->assertSee('tabindex="-1"', false);
        $response->assertSee('x-ref="resetCancelButton"', false);
        $response->assertSee('x-ref="resetConfirmButton"', false);
        $response->assertSeeInOrder([
            'x-ref="resetForm"',
            'type="button"',
            '@click="submitReset()"',
        ], false);
        $response->assertSee(':action="resetAction"', false);
        $response->assertSee('requestSubmit', false);
        $response->assertSee('Mencatat aksi reset oleh admin');
    }

    public function test_dinas_can_reset_peserta_ujian_from_monitoring(): void
    {
        $user = $this->dinasUser();
        $sesi = SesiUjian::factory()->berlangsung()->create();
        $peserta = Peserta::factory()->create();
        $sesiPeserta = SesiPeserta::factory()->for($sesi, 'sesi')->for($peserta, 'peserta')->submit()->create([
            'soal_terjawab' => 10,
            'soal_ditandai' => 2,
            'nilai_akhir' => 84.5,
            'nilai_benar' => 84.5,
            'jumlah_benar' => 8,
            'jumlah_salah' => 2,
            'jumlah_kosong' => 0,
            'urutan_soal' => ['a', 'b'],
            'urutan_opsi' => ['a' => ['1', '2']],
            'token_ujian' => 'token-reset-test',
        ]);
        $soal = Soal::factory()->create();

        JawabanPeserta::factory()->pg()->create([
            'sesi_peserta_id' => $sesiPeserta->id,
            'soal_id' => $soal->id,
        ]);
        LogAktivitasUjian::factory()->create([
            'sesi_peserta_id' => $sesiPeserta->id,
            'tipe_event' => 'submit_ujian',
        ]);

        Cache::put("paket_soal_{$sesi->paket_id}_sp_{$sesiPeserta->id}", ['cached' => true], 60);
        Cache::put("sesi_live_{$sesi->id}", ['cached' => true], 60);

        $response = $this->from(route('dinas.monitoring.sesi', $sesi))
            ->actingAs($user)
            ->post(route('dinas.monitoring.sesi.reset-peserta', [$sesi, $sesiPeserta]));

        $response->assertRedirect(route('dinas.monitoring.sesi', $sesi));
        $response->assertSessionHas('success');

        $sesiPeserta->refresh();

        $this->assertSame('terdaftar', $sesiPeserta->status);
        $this->assertNull($sesiPeserta->token_ujian);
        $this->assertNull($sesiPeserta->urutan_soal);
        $this->assertNull($sesiPeserta->urutan_opsi);
        $this->assertNull($sesiPeserta->mulai_at);
        $this->assertNull($sesiPeserta->submit_at);
        $this->assertNull($sesiPeserta->nilai_akhir);
        $this->assertNull($sesiPeserta->nilai_benar);
        $this->assertSame(0, $sesiPeserta->soal_terjawab);
        $this->assertSame(0, $sesiPeserta->soal_ditandai);
        $this->assertSame(0, JawabanPeserta::where('sesi_peserta_id', $sesiPeserta->id)->count());

        $this->assertDatabaseHas('log_aktivitas_ujian', [
            'sesi_peserta_id' => $sesiPeserta->id,
            'tipe_event' => 'reset_ujian',
        ]);
        $this->assertSame(1, LogAktivitasUjian::where('sesi_peserta_id', $sesiPeserta->id)->count());
        $this->assertNull(Cache::get("paket_soal_{$sesi->paket_id}_sp_{$sesiPeserta->id}"));
        $this->assertNull(Cache::get("sesi_live_{$sesi->id}"));
    }

    public function test_reset_rejects_peserta_from_other_sesi(): void
    {
        $user = $this->dinasUser();
        $sesi = SesiUjian::factory()->berlangsung()->create();
        $otherSesi = SesiUjian::factory()->berlangsung()->create();
        $sesiPeserta = SesiPeserta::factory()->for($otherSesi, 'sesi')->mengerjakan()->create();

        $response = $this->actingAs($user)
            ->post(route('dinas.monitoring.sesi.reset-peserta', [$sesi, $sesiPeserta]));

        $response->assertForbidden();
    }

    public function test_reset_rejects_invalid_status(): void
    {
        $user = $this->dinasUser();
        $sesi = SesiUjian::factory()->berlangsung()->create();
        $sesiPeserta = SesiPeserta::factory()->for($sesi, 'sesi')->create([
            'status' => 'terdaftar',
            'token_ujian' => null,
        ]);

        $response = $this->actingAs($user)
            ->post(route('dinas.monitoring.sesi.reset-peserta', [$sesi, $sesiPeserta]));

        $response->assertStatus(422);
    }
}
