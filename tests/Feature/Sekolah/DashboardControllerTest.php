<?php

namespace Tests\Feature\Sekolah;

use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
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

    public function test_unauthenticated_redirected(): void
    {
        $response = $this->get(route('sekolah.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_returns_view(): void
    {
        $user = $this->sekolahUser();

        $response = $this->actingAs($user)->get(route('sekolah.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('sekolah.dashboard');
        $response->assertViewHas('stats');
        $response->assertViewHas('sekolah');
    }

    public function test_user_without_sekolah_redirected_to_dinas(): void
    {
        $user = User::factory()->adminSekolah()->create([
            'sekolah_id' => null,
            'is_active'  => true,
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.dashboard'));

        $response->assertRedirect(route('dinas.dashboard'));
    }
}
