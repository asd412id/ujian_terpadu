<?php

namespace Tests\Feature\Dinas;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function dinasUser(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }

    public function test_unauthenticated_redirected(): void
    {
        $response = $this->get(route('dinas.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_non_dinas_forbidden(): void
    {
        $user = User::factory()->pengawas()->create(['is_active' => true]);
        $response = $this->actingAs($user)->get(route('dinas.dashboard'));
        $response->assertStatus(403);
    }

    public function test_dashboard_returns_stats(): void
    {
        Cache::flush();
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->get(route('dinas.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.dashboard');
        $response->assertViewHas('stats');
        $response->assertViewHas('sesiAktif');
    }
}
