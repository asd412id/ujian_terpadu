<?php

namespace Tests\Feature\Auth;

use App\Models\Peserta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PesertaLoginControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_peserta_login_page(): void
    {
        $response = $this->get(route('ujian.login'));
        $response->assertStatus(200);
        $response->assertViewIs('auth.peserta-login');
    }

    public function test_authenticated_peserta_redirected_from_login(): void
    {
        $peserta = Peserta::factory()->create();

        $response = $this->actingAs($peserta, 'peserta')
            ->get(route('ujian.login'));

        $response->assertRedirect('/');
    }

    public function test_login_with_valid_username(): void
    {
        $peserta = Peserta::factory()->create([
            'username_ujian' => 'peserta001',
            'password_ujian' => Hash::make('secret123'),
            'is_active'      => true,
        ]);

        $response = $this->post(route('ujian.login.post'), [
            'username' => 'peserta001',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('ujian.lobby'));
        $this->assertTrue(Auth::guard('peserta')->check());
    }

    public function test_login_with_nis(): void
    {
        $peserta = Peserta::factory()->create([
            'nis'            => '112233',
            'password_ujian' => Hash::make('secret123'),
            'is_active'      => true,
        ]);

        $response = $this->post(route('ujian.login.post'), [
            'username' => '112233',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('ujian.lobby'));
    }

    public function test_login_with_nisn(): void
    {
        $peserta = Peserta::factory()->create([
            'nisn'           => '9988776655',
            'password_ujian' => Hash::make('secret123'),
            'is_active'      => true,
        ]);

        $response = $this->post(route('ujian.login.post'), [
            'username' => '9988776655',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('ujian.lobby'));
    }

    public function test_login_with_invalid_password(): void
    {
        $peserta = Peserta::factory()->create([
            'username_ujian' => 'peserta001',
            'password_ujian' => Hash::make('secret123'),
            'is_active'      => true,
        ]);

        $response = $this->post(route('ujian.login.post'), [
            'username' => 'peserta001',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertFalse(Auth::guard('peserta')->check());
    }

    public function test_login_with_inactive_peserta(): void
    {
        $peserta = Peserta::factory()->create([
            'username_ujian' => 'peserta001',
            'password_ujian' => Hash::make('secret123'),
            'is_active'      => false,
        ]);

        $response = $this->post(route('ujian.login.post'), [
            'username' => 'peserta001',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_login_validation_fails_without_username(): void
    {
        $response = $this->post(route('ujian.login.post'), [
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_peserta_logout(): void
    {
        $peserta = Peserta::factory()->create();

        $response = $this->actingAs($peserta, 'peserta')
            ->post(route('ujian.logout'));

        $response->assertRedirect(route('ujian.login'));
        $this->assertFalse(Auth::guard('peserta')->check());
    }
}
