<?php

namespace Tests\Feature\Dinas;

use App\Models\KategoriSoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KategoriSoalControllerTest extends TestCase
{
    use RefreshDatabase;

    private function dinasUser(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }

    public function test_index_returns_kategori_list(): void
    {
        $user = $this->dinasUser();
        KategoriSoal::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('dinas.kategori.index'));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.kategori.index');
        $response->assertViewHas('kategoris');
    }

    public function test_create_page(): void
    {
        $user = $this->dinasUser();
        $response = $this->actingAs($user)->get(route('dinas.kategori.create'));
        $response->assertStatus(200);
    }

    public function test_store_creates_kategori(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->post(route('dinas.kategori.store'), [
            'nama'      => 'Matematika',
            'kode'      => 'MTK-01',
            'jenjang'   => 'SMA',
            'kelompok'  => 'Umum',
            'kurikulum' => 'Merdeka',
            'urutan'    => 1,
        ]);

        $response->assertRedirect(route('dinas.kategori.index'));
        $this->assertDatabaseHas('kategori_soal', ['nama' => 'Matematika', 'kode' => 'MTK-01']);
    }

    public function test_store_validation_fails_without_nama(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->post(route('dinas.kategori.store'), [
            'jenjang'   => 'SMA',
            'kurikulum' => 'Merdeka',
            'urutan'    => 1,
        ]);

        $response->assertSessionHasErrors('nama');
    }

    public function test_update_kategori(): void
    {
        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create(['nama' => 'Old']);

        $response = $this->actingAs($user)->put(route('dinas.kategori.update', $kategori), [
            'nama'      => 'Updated Kategori',
            'jenjang'   => 'SMP',
            'kurikulum' => 'K13',
            'urutan'    => 2,
        ]);

        $response->assertRedirect(route('dinas.kategori.index'));
        $this->assertDatabaseHas('kategori_soal', ['id' => $kategori->id, 'nama' => 'Updated Kategori']);
    }

    public function test_destroy_deactivates_kategori(): void
    {
        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->delete(route('dinas.kategori.destroy', $kategori));

        $response->assertRedirect(route('dinas.kategori.index'));
        $kategori->refresh();
        $this->assertFalse($kategori->is_active);
    }
}
