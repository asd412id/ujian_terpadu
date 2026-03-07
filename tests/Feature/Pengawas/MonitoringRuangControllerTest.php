<?php

namespace Tests\Feature\Pengawas;

use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringRuangControllerTest extends TestCase
{
    use RefreshDatabase;

    private function pengawasUser(): User
    {
        return User::factory()->pengawas()->create(['is_active' => true]);
    }

    public function test_monitoring_ruang_shows_sesi(): void
    {
        $user = $this->pengawasUser();
        $sesi = SesiUjian::factory()->berlangsung()->create(['pengawas_id' => $user->id]);
        SesiPeserta::factory()->count(5)->create(['sesi_id' => $sesi->id, 'status' => 'mengerjakan']);

        $response = $this->actingAs($user)->get(route('pengawas.sesi', $sesi));

        $response->assertStatus(200);
        $response->assertViewIs('pengawas.monitoring-ruang');
        $response->assertViewHas('sesi');
        $response->assertViewHas('statsPeserta');
    }

    public function test_monitoring_shows_correct_stats(): void
    {
        $user = $this->pengawasUser();
        $sesi = SesiUjian::factory()->berlangsung()->create(['pengawas_id' => $user->id]);
        SesiPeserta::factory()->count(3)->create(['sesi_id' => $sesi->id, 'status' => 'mengerjakan']);
        SesiPeserta::factory()->count(2)->create(['sesi_id' => $sesi->id, 'status' => 'submit']);

        $response = $this->actingAs($user)->get(route('pengawas.sesi', $sesi));

        $response->assertStatus(200);
        $response->assertViewHas('statsPeserta', fn ($stats) =>
            $stats['total'] === 5 && $stats['aktif'] === 3 && $stats['submit'] === 2
        );
    }
}
