<?php

namespace Tests\Unit\Models;

use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_user(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_fillable_attributes(): void
    {
        $user = new User();
        $this->assertEquals(
            ['name', 'email', 'password', 'role', 'sekolah_id', 'is_active', 'last_login_at', 'avatar'],
            $user->getFillable()
        );
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $user = User::factory()->create(['is_active' => 1]);
        $this->assertIsBool($user->is_active);
        $this->assertTrue($user->is_active);
    }

    public function test_last_login_at_cast_to_datetime(): void
    {
        $user = User::factory()->create(['last_login_at' => now()]);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->last_login_at);
    }

    public function test_password_is_hidden(): void
    {
        $user = User::factory()->create();
        $this->assertNotContains('password', array_keys($user->toArray()));
    }

    public function test_sekolah_relationship(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->assertInstanceOf(Sekolah::class, $user->sekolah);
        $this->assertEquals($sekolah->id, $user->sekolah->id);
    }

    public function test_is_super_admin(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertFalse($user->isAdminDinas());
        $this->assertFalse($user->isAdminSekolah());
        $this->assertFalse($user->isPengawas());
    }

    public function test_is_admin_dinas(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_DINAS]);
        $this->assertTrue($user->isAdminDinas());
        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_is_admin_sekolah(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_SEKOLAH]);
        $this->assertTrue($user->isAdminSekolah());
    }

    public function test_is_pengawas(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PENGAWAS]);
        $this->assertTrue($user->isPengawas());
    }

    public function test_is_dinas_returns_true_for_super_admin_and_admin_dinas(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $adminDinas = User::factory()->create(['role' => User::ROLE_ADMIN_DINAS]);
        $adminSekolah = User::factory()->create(['role' => User::ROLE_ADMIN_SEKOLAH]);

        $this->assertTrue($superAdmin->isDinas());
        $this->assertTrue($adminDinas->isDinas());
        $this->assertFalse($adminSekolah->isDinas());
    }

    public function test_get_dashboard_route(): void
    {
        $this->assertEquals('dinas.dashboard', User::factory()->make(['role' => 'super_admin'])->getDashboardRoute());
        $this->assertEquals('dinas.dashboard', User::factory()->make(['role' => 'admin_dinas'])->getDashboardRoute());
        $this->assertEquals('sekolah.dashboard', User::factory()->make(['role' => 'admin_sekolah'])->getDashboardRoute());
        $this->assertEquals('pengawas.dashboard', User::factory()->make(['role' => 'pengawas'])->getDashboardRoute());
        $this->assertEquals('login', User::factory()->make(['role' => 'unknown'])->getDashboardRoute());
    }
}
