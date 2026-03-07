<?php

namespace Tests\Feature\Sekolah;

use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PesertaControllerTest extends TestCase
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

    public function test_index_returns_peserta_list(): void
    {
        $user = $this->sekolahUser();
        Peserta::factory()->count(3)->create(['sekolah_id' => $user->sekolah_id]);

        $response = $this->actingAs($user)->get(route('sekolah.peserta.index'));

        $response->assertStatus(200);
        $response->assertViewIs('sekolah.peserta.index');
    }

    public function test_index_search_by_name(): void
    {
        $user = $this->sekolahUser();
        Peserta::factory()->create(['sekolah_id' => $user->sekolah_id, 'nama' => 'Budi Santoso']);
        Peserta::factory()->create(['sekolah_id' => $user->sekolah_id, 'nama' => 'Ani Wijaya']);

        $response = $this->actingAs($user)
            ->get(route('sekolah.peserta.index', ['q' => 'Budi']));

        $response->assertStatus(200);
    }

    public function test_create_page(): void
    {
        $user = $this->sekolahUser();
        $response = $this->actingAs($user)->get(route('sekolah.peserta.create'));
        $response->assertStatus(200);
    }

    public function test_store_creates_peserta(): void
    {
        $user = $this->sekolahUser();

        $response = $this->actingAs($user)->post(route('sekolah.peserta.store'), [
            'nama'          => 'Peserta Baru',
            'nis'           => '123456',
            'nisn'          => '9876543210',
            'kelas'         => 'XII-1',
            'jurusan'       => 'IPA',
            'jenis_kelamin' => 'L',
        ]);

        $response->assertRedirect(route('sekolah.peserta.index'));
        $this->assertDatabaseHas('peserta', ['nama' => 'Peserta Baru', 'sekolah_id' => $user->sekolah_id]);

        $peserta = Peserta::where('nama', 'Peserta Baru')->first();
        $this->assertNotNull($peserta->username_ujian);
        $this->assertNotNull($peserta->password_ujian);
    }

    public function test_store_validation_fails(): void
    {
        $user = $this->sekolahUser();

        $response = $this->actingAs($user)->post(route('sekolah.peserta.store'), []);

        $response->assertSessionHasErrors('nama');
    }

    public function test_update_peserta(): void
    {
        $user = $this->sekolahUser();
        $peserta = Peserta::factory()->create(['sekolah_id' => $user->sekolah_id]);

        $response = $this->actingAs($user)->put(route('sekolah.peserta.update', $peserta), [
            'nama'  => 'Updated Name',
            'kelas' => 'XII-2',
        ]);

        $response->assertRedirect(route('sekolah.peserta.index'));
        $this->assertDatabaseHas('peserta', ['id' => $peserta->id, 'nama' => 'Updated Name']);
    }

    public function test_destroy_soft_deletes_peserta(): void
    {
        $user = $this->sekolahUser();
        $peserta = Peserta::factory()->create(['sekolah_id' => $user->sekolah_id]);

        $response = $this->actingAs($user)->delete(route('sekolah.peserta.destroy', $peserta));

        $response->assertRedirect(route('sekolah.peserta.index'));
        $this->assertSoftDeleted('peserta', ['id' => $peserta->id]);
    }

    public function test_cannot_edit_other_school_peserta(): void
    {
        $user = $this->sekolahUser();
        $otherSekolah = Sekolah::factory()->create();
        $peserta = Peserta::factory()->create(['sekolah_id' => $otherSekolah->id]);

        $response = $this->actingAs($user)->get(route('sekolah.peserta.edit', $peserta));

        $response->assertStatus(403);
    }

    public function test_import_page(): void
    {
        $user = $this->sekolahUser();
        $response = $this->actingAs($user)->get(route('sekolah.peserta.import'));
        $response->assertStatus(200);
    }
}
