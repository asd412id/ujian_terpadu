<?php

namespace Tests\Feature\Sekolah;

use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KartuLoginControllerTest extends TestCase
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

    public function test_peserta_index_shows_embedded_kartu_actions(): void
    {
        $user = $this->sekolahUser();
        $peserta = Peserta::factory()->create(['sekolah_id' => $user->sekolah_id]);

        $response = $this->actingAs($user)->get(route('sekolah.peserta.index'));

        $response->assertStatus(200);
        $response->assertViewIs('sekolah.peserta.index');
        $response->assertSee('Cetak Semua Kartu');
        $response->assertSee(route('sekolah.kartu.show', $peserta));
    }

    public function test_show_single_kartu(): void
    {
        $user = $this->sekolahUser();
        $peserta = Peserta::factory()->create([
            'sekolah_id'   => $user->sekolah_id,
            'password_plain' => encrypt('TEST1234'),
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.kartu.show', $peserta));

        $response->assertStatus(200);
    }
}
