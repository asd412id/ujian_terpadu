<?php

namespace Tests\Feature\Dinas;

use App\Models\Sekolah;
use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response->assertJsonStructure(['sesi', 'total']);
    }

    public function test_api_sesi_returns_json(): void
    {
        $user = $this->dinasUser();
        $sesi = SesiUjian::factory()->create();

        $response = $this->actingAs($user)->getJson(route('dinas.monitoring.sesi.api', $sesi));

        $response->assertOk();
        $response->assertJsonStructure(['sesi', 'peserta', 'stats']);
    }
}
