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

class LaporanControllerTest extends TestCase
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

    public function test_index_renders_for_own_school_user(): void
    {
        $user = $this->sekolahUser();
        $paket = PaketUjian::factory()->aktif()->create(['sekolah_id' => $user->sekolah_id]);
        $peserta = Peserta::factory()->create(['sekolah_id' => $user->sekolah_id]);
        $sesi = SesiUjian::factory()->selesai()->create(['paket_id' => $paket->id]);

        SesiPeserta::factory()->submit()->create([
            'sesi_id' => $sesi->id,
            'peserta_id' => $peserta->id,
            'nilai_akhir' => 82,
            'jumlah_benar' => 10,
            'jumlah_salah' => 2,
            'jumlah_kosong' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        $response->assertViewIs('sekolah.laporan.index');
        $response->assertViewHas(['paketList', 'data', 'rekap']);
    }

    public function test_index_hides_unselesai_sesi_data(): void
    {
        $user = $this->sekolahUser();
        $paket = PaketUjian::factory()->aktif()->create(['sekolah_id' => $user->sekolah_id]);
        $peserta = Peserta::factory()->create(['sekolah_id' => $user->sekolah_id]);
        $sesi = SesiUjian::factory()->berlangsung()->create(['paket_id' => $paket->id]);

        SesiPeserta::factory()->submit()->create([
            'sesi_id' => $sesi->id,
            'peserta_id' => $peserta->id,
            'nilai_akhir' => 82,
            'jumlah_benar' => 10,
            'jumlah_salah' => 2,
            'jumlah_kosong' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        $response->assertViewHas('data', fn ($data) => $data->count() === 0);
    }

    public function test_index_shows_selesai_sesi_data(): void
    {
        $user = $this->sekolahUser();
        $paket = PaketUjian::factory()->aktif()->create(['sekolah_id' => $user->sekolah_id]);
        $peserta = Peserta::factory()->create(['sekolah_id' => $user->sekolah_id]);
        $sesi = SesiUjian::factory()->selesai()->create(['paket_id' => $paket->id]);

        SesiPeserta::factory()->submit()->create([
            'sesi_id' => $sesi->id,
            'peserta_id' => $peserta->id,
            'nilai_akhir' => 82,
            'jumlah_benar' => 10,
            'jumlah_salah' => 2,
            'jumlah_kosong' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        $response->assertViewHas('data', fn ($data) => $data->count() === 1);
    }
}
