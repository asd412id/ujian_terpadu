<?php

namespace Tests\Feature\Sekolah;

use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PesertaPageTest extends TestCase
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

    public function test_index_shows_embedded_kartu_actions(): void
    {
        $user = $this->sekolahUser();
        $peserta = Peserta::factory()->create([
            'sekolah_id' => $user->sekolah_id,
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.peserta.index'));

        $response->assertStatus(200);
        $response->assertSee('Cetak Semua Kartu');
        $response->assertSee('Cetak Kartu');
        $response->assertDontSee('Kartu Login</span>');
    }
}
