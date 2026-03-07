<?php

namespace Tests\Feature\Dinas;

use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function dinasUser(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }

    public function test_index_returns_users_list(): void
    {
        $user = $this->dinasUser();
        User::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('dinas.users.index'));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.users.index');
    }

    public function test_create_page(): void
    {
        $user = $this->dinasUser();
        $response = $this->actingAs($user)->get(route('dinas.users.create'));
        $response->assertStatus(200);
    }

    public function test_store_creates_user(): void
    {
        $admin = $this->dinasUser();

        $response = $this->actingAs($admin)->post(route('dinas.users.store'), [
            'name'                  => 'Operator Sekolah',
            'email'                 => 'operator@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'admin_sekolah',
        ]);

        $response->assertRedirect(route('dinas.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'operator@test.com', 'role' => 'admin_sekolah']);
    }

    public function test_store_validation_fails_duplicate_email(): void
    {
        $admin = $this->dinasUser();
        User::factory()->create(['email' => 'existing@test.com']);

        $response = $this->actingAs($admin)->post(route('dinas.users.store'), [
            'name'                  => 'Duplicate',
            'email'                 => 'existing@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'admin_dinas',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_update_user(): void
    {
        $admin = $this->dinasUser();
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)->put(route('dinas.users.update', $user), [
            'name'  => 'New Name',
            'email' => $user->email,
            'role'  => 'admin_dinas',
        ]);

        $response->assertRedirect(route('dinas.users.index'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_update_user_password(): void
    {
        $admin = $this->dinasUser();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('dinas.users.update', $user), [
            'name'                  => $user->name,
            'email'                 => $user->email,
            'role'                  => $user->role,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('dinas.users.index'));
    }

    public function test_destroy_deactivates_user(): void
    {
        $admin = $this->dinasUser();
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->delete(route('dinas.users.destroy', $user));

        $response->assertRedirect(route('dinas.users.index'));
        $user->refresh();
        $this->assertFalse($user->is_active);
    }
}
