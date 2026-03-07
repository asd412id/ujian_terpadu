<?php

namespace Tests\Feature\Dinas;

use App\Models\KategoriSoal;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoalControllerTest extends TestCase
{
    use RefreshDatabase;

    private function dinasUser(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }

    public function test_index_returns_soal_list(): void
    {
        $user = $this->dinasUser();
        Soal::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('dinas.dinas.soal.index'));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.soal.index');
    }

    public function test_index_filters_by_kategori(): void
    {
        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create();
        Soal::factory()->count(2)->create(['kategori_id' => $kategori->id]);
        Soal::factory()->count(3)->create();

        $response = $this->actingAs($user)
            ->get(route('dinas.dinas.soal.index', ['kategori' => $kategori->id]));

        $response->assertStatus(200);
    }

    public function test_index_filters_by_tipe(): void
    {
        $user = $this->dinasUser();
        Soal::factory()->pg()->count(2)->create();
        Soal::factory()->essay()->count(1)->create();

        $response = $this->actingAs($user)
            ->get(route('dinas.dinas.soal.index', ['tipe' => 'pg']));

        $response->assertStatus(200);
    }

    public function test_create_page(): void
    {
        $user = $this->dinasUser();
        $response = $this->actingAs($user)->get(route('dinas.dinas.soal.create'));
        $response->assertStatus(200);
    }

    public function test_store_pg_soal(): void
    {
        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create();

        $response = $this->actingAs($user)->post(route('dinas.dinas.soal.store'), [
            'kategori_soal_id'  => $kategori->id,
            'jenis_soal'        => 'pilihan_ganda',
            'pertanyaan'        => 'Berapa 1+1?',
            'tingkat_kesulitan' => 'mudah',
            'bobot'             => 1,
            'opsi_teks'         => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat'],
            'opsi_benar'        => ['B'],
        ]);

        $response->assertRedirect(route('dinas.dinas.soal.index'));
        $this->assertDatabaseHas('soal', ['pertanyaan' => 'Berapa 1+1?', 'tipe_soal' => 'pg']);
    }

    public function test_store_essay_soal(): void
    {
        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create();

        $response = $this->actingAs($user)->post(route('dinas.dinas.soal.store'), [
            'kategori_soal_id'  => $kategori->id,
            'jenis_soal'        => 'essay',
            'pertanyaan'        => 'Jelaskan proses fotosintesis.',
            'tingkat_kesulitan' => 'sedang',
            'bobot'             => 5,
        ]);

        $response->assertRedirect(route('dinas.dinas.soal.index'));
        $this->assertDatabaseHas('soal', ['tipe_soal' => 'essay']);
    }

    public function test_store_validation_fails(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->post(route('dinas.dinas.soal.store'), [
            'pertanyaan' => 'No category',
        ]);

        $response->assertSessionHasErrors(['kategori_soal_id', 'jenis_soal', 'tingkat_kesulitan', 'bobot']);
    }

    public function test_edit_page(): void
    {
        $user = $this->dinasUser();
        $soal = Soal::factory()->create();

        $response = $this->actingAs($user)->get(route('dinas.dinas.soal.edit', $soal));
        $response->assertStatus(200);
    }

    public function test_destroy_deactivates_soal(): void
    {
        $user = $this->dinasUser();
        $soal = Soal::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->delete(route('dinas.dinas.soal.destroy', $soal));

        $response->assertRedirect(route('dinas.dinas.soal.index'));
    }
}
