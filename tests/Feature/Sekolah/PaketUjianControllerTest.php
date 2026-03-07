<?php

namespace Tests\Feature\Sekolah;

use App\Models\PaketUjian;
use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaketUjianControllerTest extends TestCase
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

    public function test_index_returns_paket_list(): void
    {
        $user = $this->sekolahUser();
        PaketUjian::factory()->aktif()->create(['sekolah_id' => $user->sekolah_id]);

        $response = $this->actingAs($user)->get(route('sekolah.paket'));

        $response->assertStatus(200);
        $response->assertViewIs('sekolah.paket.index');
    }

    public function test_show_paket_detail(): void
    {
        $user = $this->sekolahUser();
        $paket = PaketUjian::factory()->create(['sekolah_id' => $user->sekolah_id]);

        $response = $this->actingAs($user)->get(route('sekolah.paket.show', $paket));

        $response->assertStatus(200);
        $response->assertViewIs('sekolah.paket.show');
    }

    public function test_daftar_peserta_to_sesi(): void
    {
        $user = $this->sekolahUser();
        $paket = PaketUjian::factory()->aktif()->create(['sekolah_id' => $user->sekolah_id]);
        $sesi = SesiUjian::factory()->create(['paket_id' => $paket->id]);
        $peserta = Peserta::factory()->count(3)->create(['sekolah_id' => $user->sekolah_id]);

        $response = $this->actingAs($user)->post(route('sekolah.paket.daftar', $paket), [
            'sesi_id'     => $sesi->id,
            'peserta_ids' => $peserta->pluck('id')->toArray(),
        ]);

        $response->assertRedirect();
        $this->assertEquals(3, SesiPeserta::where('sesi_id', $sesi->id)->count());
    }

    public function test_daftar_peserta_validation_fails(): void
    {
        $user = $this->sekolahUser();
        $paket = PaketUjian::factory()->create(['sekolah_id' => $user->sekolah_id]);

        $response = $this->actingAs($user)->post(route('sekolah.paket.daftar', $paket), []);

        $response->assertSessionHasErrors(['sesi_id', 'peserta_ids']);
    }
}
