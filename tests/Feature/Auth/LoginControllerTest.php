<?php

namespace Tests\Feature\Auth;

use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_login_page(): void
    {
        $response = $this->get(route('login'));
        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
        $response->assertSeeText('Masuk ke Dashboard');
        $response->assertSeeText('Memverifikasi...');
        $response->assertSeeText('Sedang memverifikasi akun dan menyiapkan akses dashboard.');
        $response->assertSeeText('Masuk sebagai peserta');
        $response->assertSee('role="status"', false);
        $response->assertSee('aria-live="polite"', false);
        $response->assertDontSee('aria-describedby="login-email-error"', false);
        $response->assertDontSee('aria-describedby="login-password-error"', false);
    }

    public function test_login_page_shows_flashed_error_message(): void
    {
        $response = $this->withSession(['error' => 'Akun Anda tidak aktif.'])
            ->get(route('login'));

        $response->assertOk();
        $response->assertSeeText('Akun Anda tidak aktif.');
        $response->assertSee('role="alert"', false);
        $response->assertSee('aria-live="assertive"', false);
    }

    public function test_login_page_adds_error_descriptions_when_validation_errors_exist(): void
    {
        $errors = (new ViewErrorBag())->put('default', new MessageBag([
            'email' => ['Email wajib diisi.'],
            'password' => ['Password wajib diisi.'],
        ]));

        $response = $this->withSession(['errors' => $errors])
            ->get(route('login'));

        $response->assertOk();
        $response->assertSee('aria-describedby="login-email-error"', false);
        $response->assertSee('aria-describedby="login-password-error"', false);
        $response->assertSeeText('Email wajib diisi.');
        $response->assertSeeText('Password wajib diisi.');
    }

    public function test_authenticated_user_is_redirected_from_login_page(): void
    {
        $user = User::factory()->create(['role' => 'admin_dinas', 'is_active' => true]);
        $response = $this->actingAs($user)->get(route('login'));
        $response->assertRedirect(route('dinas.dashboard'));
    }

    public function test_login_with_valid_credentials(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create([
            'email'      => 'admin@test.com',
            'password'   => bcrypt('password123'),
            'role'       => 'admin_sekolah',
            'sekolah_id' => $sekolah->id,
            'is_active'  => true,
        ]);

        $response = $this->post(route('login.post'), [
            'email'    => 'admin@test.com',
            'password' => 'password123',
            'role'     => 'admin_sekolah',
        ]);

        $response->assertRedirect(route('sekolah.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_remember_me_sets_recaller_cookie(): void
    {
        $user = User::factory()->create([
            'email'          => 'admin@test.com',
            'password'       => bcrypt('password123'),
            'role'           => 'admin_dinas',
            'is_active'      => true,
            'remember_token' => null,
        ]);

        $response = $this->post(route('login.post'), [
            'email'    => 'admin@test.com',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dinas.dashboard'));
        $response->assertCookie(Auth::guard('web')->getRecallerName());

        $user->refresh();
        $this->assertNotNull($user->remember_token);
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

    public function test_login_with_valid_credentials_without_role(): void
    {
        $user = User::factory()->create([
            'email'     => 'admin@test.com',
            'password'  => bcrypt('password123'),
            'role'      => 'admin_dinas',
            'is_active' => true,
        ]);

        $response = $this->post(route('login.post'), [
            'email'    => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dinas.dashboard'));
        $this->assertAuthenticatedAs($user);
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
