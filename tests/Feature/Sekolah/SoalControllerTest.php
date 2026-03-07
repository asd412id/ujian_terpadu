<?php

namespace Tests\Feature\Sekolah;

use App\Models\KategoriSoal;
use App\Models\Sekolah;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoalControllerTest extends TestCase
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

    public function test_index_returns_soal_list(): void
    {
        $user = $this->sekolahUser();
        Soal::factory()->count(3)->create(['sekolah_id' => $user->sekolah_id]);

        $response = $this->actingAs($user)->get(route('sekolah.soal.index'));

        $response->assertStatus(200);
        $response->assertViewIs('sekolah.soal.index');
    }

    public function test_create_page(): void
    {
        $user = $this->sekolahUser();
        $response = $this->actingAs($user)->get(route('sekolah.soal.create'));
        $response->assertStatus(200);
    }

    public function test_store_pg_soal(): void
    {
        $user = $this->sekolahUser();
        $kategori = KategoriSoal::factory()->create();

        $response = $this->actingAs($user)->post(route('sekolah.soal.store'), [
            'kategori_soal_id'  => $kategori->id,
            'jenis_soal'        => 'pilihan_ganda',
            'pertanyaan'        => '1+1=?',
            'tingkat_kesulitan' => 'mudah',
            'bobot'             => 1,
            'opsi_teks'         => ['A' => '1', 'B' => '2', 'C' => '3', 'D' => '4'],
            'opsi_benar'        => ['B'],
        ]);

        $response->assertRedirect(route('sekolah.soal.index'));
        $this->assertDatabaseHas('soal', [
            'pertanyaan' => '1+1=?',
            'sekolah_id' => $user->sekolah_id,
        ]);
    }

    public function test_destroy_soal(): void
    {
        $user = $this->sekolahUser();
        $soal = Soal::factory()->create(['sekolah_id' => $user->sekolah_id]);

        $response = $this->actingAs($user)->delete(route('sekolah.soal.destroy', $soal));

        $response->assertRedirect(route('sekolah.soal.index'));
        $this->assertSoftDeleted('soal', ['id' => $soal->id]);
    }

    public function test_import_page(): void
    {
        $user = $this->sekolahUser();
        $response = $this->actingAs($user)->get(route('sekolah.soal.import'));
        $response->assertStatus(200);
    }
}
