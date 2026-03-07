<?php

namespace Tests\Feature\Dinas;

use App\Models\DinasPendidikan;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SekolahControllerTest extends TestCase
{
    use RefreshDatabase;

    private function dinasUser(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }

    public function test_index_returns_sekolah_list(): void
    {
        $user = $this->dinasUser();
        Sekolah::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('dinas.sekolah.index'));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.sekolah.index');
    }

    public function test_create_page(): void
    {
        $user = $this->dinasUser();
        $response = $this->actingAs($user)->get(route('dinas.sekolah.create'));
        $response->assertStatus(200);
    }

    public function test_store_creates_sekolah(): void
    {
        $user = $this->dinasUser();
        DinasPendidikan::factory()->create();

        $response = $this->actingAs($user)->post(route('dinas.sekolah.store'), [
            'nama'    => 'SMA Negeri 1',
            'npsn'    => '12345678',
            'jenjang' => 'SMA',
            'kota'    => 'Jakarta',
        ]);

        $response->assertRedirect(route('dinas.sekolah.index'));
        $this->assertDatabaseHas('sekolah', ['nama' => 'SMA Negeri 1']);
    }

    public function test_store_validation_fails(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->post(route('dinas.sekolah.store'), [
            'npsn' => '123',
        ]);

        $response->assertSessionHasErrors(['nama', 'jenjang']);
    }

    public function test_show_sekolah(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create();

        $response = $this->actingAs($user)->get(route('dinas.sekolah.show', $sekolah));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.sekolah.show');
    }

    public function test_update_sekolah(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create();

        $response = $this->actingAs($user)->put(route('dinas.sekolah.update', $sekolah), [
            'nama'    => 'SMA Updated',
            'jenjang' => 'SMA',
        ]);

        $response->assertRedirect(route('dinas.sekolah.index'));
        $this->assertDatabaseHas('sekolah', ['id' => $sekolah->id, 'nama' => 'SMA Updated']);
    }

    public function test_destroy_deactivates_sekolah(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->delete(route('dinas.sekolah.destroy', $sekolah));

        $response->assertRedirect(route('dinas.sekolah.index'));
        $sekolah->refresh();
        $this->assertFalse($sekolah->is_active);
    }
}
