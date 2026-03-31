<?php

namespace Tests\Feature\Dinas;

use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PesertaPageTest extends TestCase
{
    use RefreshDatabase;

    private function dinasUser(): User
    {
        return User::factory()->adminDinas()->create([
            'is_active' => true,
        ]);
    }

    public function test_index_shows_embedded_kartu_actions(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create();
        $peserta = Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
        ]);

        $response = $this->actingAs($user)->get(route('dinas.peserta.index'));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.peserta.index');
        $response->assertSee('Cetak Semua Kartu');
        $response->assertSee(route('dinas.kartu.show', $peserta));
        $response->assertDontSee('Kartu Peserta');
        $response->assertSee('Cetak Semua Kartu');
    }
}
