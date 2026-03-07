<?php

namespace Tests\Feature\Auth;

use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_login_page(): void
    {
        $response = $this->get(route('login'));
        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    public function test_authenticated_user_is_redirected_from_login_page(): void
    {
        $user = User::factory()->create(['role' => 'admin_dinas', 'is_active' => true]);
        $response = $this->actingAs($user)->get(route('login'));
        $response->assertRedirect('/');
    }

    public function test_login_with_valid_credentials(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create([
            'email'     => 'admin@test.com',
            'password'  => bcrypt('password123'),
            'role'      => 'admin_sekolah',
            'sekolah_id' => $sekolah->id,
            'is_active' => true,
        ]);

        $response = $this->post(route('login.post'), [
            'email'    => 'admin@test.com',
            'password' => 'password123',
            'role'     => 'admin_sekolah',
        ]);

        $response->assertRedirect(route('sekolah.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email'    => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'admin_dinas',
            'is_active' => true,
        ]);

        $response = $this->post(route('login.post'), [
            'email'    => 'admin@test.com',
            'password' => 'wrongpassword',
            'role'     => 'admin_dinas',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_with_inactive_account(): void
    {
        User::factory()->create([
            'email'    => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'admin_dinas',
            'is_active' => false,
        ]);

        $response = $this->post(route('login.post'), [
            'email'    => 'admin@test.com',
            'password' => 'password123',
            'role'     => 'admin_dinas',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_with_wrong_role(): void
    {
        User::factory()->create([
            'email'    => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'admin_sekolah',
            'is_active' => true,
        ]);

        $response = $this->post(route('login.post'), [
            'email'    => 'admin@test.com',
            'password' => 'password123',
            'role'     => 'admin_dinas',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create([
            'email'         => 'admin@test.com',
            'password'      => bcrypt('password123'),
            'role'          => 'admin_dinas',
            'is_active'     => true,
            'last_login_at' => null,
        ]);

        $this->post(route('login.post'), [
            'email'    => 'admin@test.com',
            'password' => 'password123',
            'role'     => 'admin_dinas',
        ]);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
    }

    public function test_login_validation_fails_without_role(): void
    {
        $response = $this->post(route('login.post'), [
            'email'    => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('role');
    }

    public function test_logout(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
