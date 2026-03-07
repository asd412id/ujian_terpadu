<?php

namespace Tests\Feature\Dinas;

use App\Models\PaketUjian;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function dinasUser(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }

    public function test_index_returns_laporan_page(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->get(route('dinas.laporan'));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.laporan.index');
        $response->assertViewHas('sekolahList');
        $response->assertViewHas('paketList');
    }

    public function test_index_with_sekolah_filter(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create();
        $paket = PaketUjian::factory()->create(['sekolah_id' => $sekolah->id]);
        $sesi = SesiUjian::factory()->create(['paket_id' => $paket->id]);
        SesiPeserta::factory()->submit()->create(['sesi_id' => $sesi->id]);

        $response = $this->actingAs($user)
            ->get(route('dinas.laporan', ['sekolah_id' => $sekolah->id]));

        $response->assertStatus(200);
        $response->assertViewHas('data');
    }

    public function test_index_without_filter_no_data(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->get(route('dinas.laporan'));

        $response->assertStatus(200);
        $response->assertViewHas('data', []);
    }
}
