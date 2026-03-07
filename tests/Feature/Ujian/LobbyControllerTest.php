<?php

namespace Tests\Feature\Ujian;

use App\Models\Peserta;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\PaketUjian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LobbyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_peserta_redirected(): void
    {
        $response = $this->get(route('ujian.lobby'));
        $response->assertRedirect(route('ujian.login'));
    }

    public function test_lobby_displays_available_sessions(): void
    {
        $peserta = Peserta::factory()->create();
        $paket = PaketUjian::factory()->aktif()->create();
        $sesi = SesiUjian::factory()->create(['paket_id' => $paket->id]);
        $sp = SesiPeserta::factory()->create([
            'sesi_id'    => $sesi->id,
            'peserta_id' => $peserta->id,
            'status'     => 'login',
        ]);

        $response = $this->actingAs($peserta, 'peserta')
            ->get(route('ujian.lobby'));

        $response->assertStatus(200);
        $response->assertViewIs('ujian.lobby');
        $response->assertViewHas('sesiTersedia');
    }

    public function test_lobby_shows_completed_sessions(): void
    {
        $peserta = Peserta::factory()->create();
        $paket = PaketUjian::factory()->create();
        $sesi = SesiUjian::factory()->create(['paket_id' => $paket->id]);
        SesiPeserta::factory()->submit()->create([
            'sesi_id'    => $sesi->id,
            'peserta_id' => $peserta->id,
        ]);

        $response = $this->actingAs($peserta, 'peserta')
            ->get(route('ujian.lobby'));

        $response->assertStatus(200);
        $response->assertViewHas('sesiSelesai');
    }

    public function test_lobby_empty_for_new_peserta(): void
    {
        $peserta = Peserta::factory()->create();

        $response = $this->actingAs($peserta, 'peserta')
            ->get(route('ujian.lobby'));

        $response->assertStatus(200);
        $response->assertViewHas('sesiTersedia', fn ($data) => $data->isEmpty());
    }
}
