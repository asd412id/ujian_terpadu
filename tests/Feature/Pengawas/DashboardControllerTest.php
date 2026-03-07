<?php

namespace Tests\Feature\Pengawas;

use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function pengawasUser(): User
    {
        return User::factory()->pengawas()->create(['is_active' => true]);
    }

    public function test_unauthenticated_redirected(): void
    {
        $response = $this->get(route('pengawas.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_assigned_sessions(): void
    {
        $user = $this->pengawasUser();
        SesiUjian::factory()->berlangsung()->create(['pengawas_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('pengawas.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('pengawas.dashboard');
        $response->assertViewHas('sesiList');
        $response->assertViewHas('stats');
    }

    public function test_dashboard_empty_for_new_pengawas(): void
    {
        $user = $this->pengawasUser();

        $response = $this->actingAs($user)->get(route('pengawas.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('stats', fn ($stats) => $stats['total_sesi'] === 0);
    }
}
