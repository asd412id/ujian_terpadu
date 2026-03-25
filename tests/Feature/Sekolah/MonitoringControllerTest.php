<?php

namespace Tests\Feature\Sekolah;

use App\Models\PaketUjian;
use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringControllerTest extends TestCase
{
    use RefreshDatabase;

    private function sekolahUser(): User
    {
        $sekolah = Sekolah::factory()->create();

        return User::factory()->adminSekolah()->create([
            'sekolah_id' => $sekolah->id,
            'is_active'  => true,
        ]);
    }

    public function test_index_returns_monitoring_view(): void
    {
        $user = $this->sekolahUser();
        PaketUjian::factory()->aktif()->create(['sekolah_id' => $user->sekolah_id]);

        $response = $this->actingAs($user)->get(route('sekolah.monitoring'));

        $response->assertStatus(200);
        $response->assertViewIs('sekolah.monitoring.index');
        $response->assertViewHas(['sekolah', 'sesiList', 'summary']);
    }

    public function test_api_index_returns_json(): void
    {
        $user = $this->sekolahUser();

        $response = $this->actingAs($user)->getJson(route('sekolah.monitoring.api'));

        $response->assertOk();
        $response->assertJsonStructure(['sesiList', 'summary']);
    }

    public function test_sesi_detail_returns_view_for_own_school(): void
    {
        $user = $this->sekolahUser();
        $paket = PaketUjian::factory()->aktif()->create(['sekolah_id' => $user->sekolah_id]);
        $sesi = SesiUjian::factory()->berlangsung()->create(['paket_id' => $paket->id]);
        Peserta::factory()->count(2)->create(['sekolah_id' => $user->sekolah_id])->each(function ($peserta) use ($sesi) {
            SesiPeserta::factory()->create([
                'sesi_id' => $sesi->id,
                'peserta_id' => $peserta->id,
            ]);
        });

        $response = $this->actingAs($user)->get(route('sekolah.monitoring.sesi', $sesi));

        $response->assertStatus(200);
        $response->assertViewIs('sekolah.monitoring.sesi');
        $response->assertViewHas(['sesi', 'alerts', 'pesertaList', 'stats']);
    }

    public function test_sesi_detail_forbidden_for_other_school(): void
    {
        $user = $this->sekolahUser();
        $otherSekolah = Sekolah::factory()->create();
        $paket = PaketUjian::factory()->aktif()->create(['sekolah_id' => $otherSekolah->id]);
        $sesi = SesiUjian::factory()->berlangsung()->create(['paket_id' => $paket->id]);

        $response = $this->actingAs($user)->get(route('sekolah.monitoring.sesi', $sesi));

        $response->assertForbidden();
    }

    public function test_api_sesi_returns_json_for_own_school(): void
    {
        $user = $this->sekolahUser();
        $paket = PaketUjian::factory()->aktif()->create(['sekolah_id' => $user->sekolah_id]);
        $sesi = SesiUjian::factory()->berlangsung()->create(['paket_id' => $paket->id]);

        $response = $this->actingAs($user)->getJson(route('sekolah.monitoring.sesi.api', $sesi));

        $response->assertOk();
        $response->assertJsonStructure(['stats', 'peserta_live']);
    }

    public function test_api_sesi_hides_nilai_before_selesai(): void
    {
        $user = $this->sekolahUser();
        $paket = PaketUjian::factory()->aktif()->create(['sekolah_id' => $user->sekolah_id]);
        $peserta = Peserta::factory()->create(['sekolah_id' => $user->sekolah_id]);
        $sesi = SesiUjian::factory()->berlangsung()->create(['paket_id' => $paket->id]);

        $sp = SesiPeserta::factory()->submit()->create([
            'sesi_id' => $sesi->id,
            'peserta_id' => $peserta->id,
            'nilai_akhir' => 91,
        ]);

        $response = $this->actingAs($user)->getJson(route('sekolah.monitoring.sesi.api', $sesi));

        $response->assertOk();
        $response->assertJsonPath("peserta_live.{$sp->id}.nilai_akhir", null);
    }
}
