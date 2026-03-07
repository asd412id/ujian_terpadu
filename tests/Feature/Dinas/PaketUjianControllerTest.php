<?php

namespace Tests\Feature\Dinas;

use App\Models\PaketSoal;
use App\Models\PaketUjian;
use App\Models\Sekolah;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaketUjianControllerTest extends TestCase
{
    use RefreshDatabase;

    private function dinasUser(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }

    public function test_index_returns_paket_list(): void
    {
        $user = $this->dinasUser();
        PaketUjian::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('dinas.paket.index'));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.paket.index');
    }

    public function test_create_page(): void
    {
        $user = $this->dinasUser();
        $response = $this->actingAs($user)->get(route('dinas.paket.create'));
        $response->assertStatus(200);
    }

    public function test_store_creates_paket(): void
    {
        $user = $this->dinasUser();
        Sekolah::factory()->create();

        $response = $this->actingAs($user)->post(route('dinas.paket.store'), [
            'nama'         => 'Ujian PAS Matematika',
            'jenis_ujian'  => 'PAS',
            'jenjang'      => 'SMA',
            'durasi_menit' => 90,
        ]);

        $this->assertDatabaseHas('paket_ujian', ['nama' => 'Ujian PAS Matematika']);
    }

    public function test_store_validation_fails(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->post(route('dinas.paket.store'), []);

        $response->assertSessionHasErrors(['nama', 'jenis_ujian', 'jenjang', 'durasi_menit']);
    }

    public function test_show_paket(): void
    {
        $user = $this->dinasUser();
        $paket = PaketUjian::factory()->create();

        $response = $this->actingAs($user)->get(route('dinas.paket.show', $paket));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.paket.show');
    }

    public function test_update_paket(): void
    {
        $user = $this->dinasUser();
        $paket = PaketUjian::factory()->create();

        $response = $this->actingAs($user)->put(route('dinas.paket.update', $paket), [
            'nama'         => 'Updated Paket',
            'jenis_ujian'  => 'PAS',
            'jenjang'      => 'SMA',
            'durasi_menit' => 120,
        ]);

        $response->assertRedirect(route('dinas.paket.show', $paket));
        $this->assertDatabaseHas('paket_ujian', ['id' => $paket->id, 'nama' => 'Updated Paket']);
    }

    public function test_destroy_archives_paket(): void
    {
        $user = $this->dinasUser();
        $paket = PaketUjian::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($user)->delete(route('dinas.paket.destroy', $paket));

        $response->assertRedirect(route('dinas.paket.index'));
        $paket->refresh();
        $this->assertEquals('arsip', $paket->status);
    }

    public function test_publish_paket_with_soal(): void
    {
        $user = $this->dinasUser();
        $paket = PaketUjian::factory()->create(['status' => 'draft']);
        PaketSoal::factory()->create(['paket_id' => $paket->id]);

        $response = $this->actingAs($user)->post(route('dinas.paket.publish', $paket));

        $paket->refresh();
        $this->assertEquals('aktif', $paket->status);
    }

    public function test_publish_fails_without_soal(): void
    {
        $user = $this->dinasUser();
        $paket = PaketUjian::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($user)->post(route('dinas.paket.publish', $paket));

        $response->assertSessionHas('error');
        $paket->refresh();
        $this->assertEquals('draft', $paket->status);
    }

    public function test_soal_add_to_paket(): void
    {
        $user = $this->dinasUser();
        $paket = PaketUjian::factory()->create(['jumlah_soal' => 0]);
        $soal = Soal::factory()->create();

        $response = $this->actingAs($user)->post(route('dinas.paket.soal.add', $paket), [
            'soal_id' => $soal->id,
        ]);

        $this->assertDatabaseHas('paket_soal', ['paket_id' => $paket->id, 'soal_id' => $soal->id]);
        $paket->refresh();
        $this->assertEquals(1, $paket->jumlah_soal);
    }

    public function test_soal_remove_from_paket(): void
    {
        $user = $this->dinasUser();
        $paket = PaketUjian::factory()->create(['jumlah_soal' => 1]);
        $soal = Soal::factory()->create();
        PaketSoal::factory()->create(['paket_id' => $paket->id, 'soal_id' => $soal->id]);

        $response = $this->actingAs($user)->delete(route('dinas.paket.soal.remove', [$paket, $soal]));

        $this->assertDatabaseMissing('paket_soal', ['paket_id' => $paket->id, 'soal_id' => $soal->id]);
    }
}
