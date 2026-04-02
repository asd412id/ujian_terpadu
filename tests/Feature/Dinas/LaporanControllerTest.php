<?php

namespace Tests\Feature\Dinas;

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

    private function dinasUser(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }

    private function createData(array $paketOverrides = [], array $sesiOverrides = [], array $spOverrides = []): array
    {
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $paket = PaketUjian::factory()->aktif()->create(array_merge([
            'sekolah_id'      => $sekolah->id,
            'jenjang'         => 'SMA',
            'tampilkan_hasil' => true,
        ], $paketOverrides));

        $peserta = Peserta::factory()->create(['sekolah_id' => $sekolah->id, 'kelas' => 'XI-2']);
        $sesi = SesiUjian::factory()->selesai()->create(array_merge([
            'paket_id' => $paket->id,
        ], $sesiOverrides));

        $sp = SesiPeserta::factory()->submit()->create(array_merge([
            'sesi_id'      => $sesi->id,
            'peserta_id'   => $peserta->id,
            'nilai_akhir'  => 82,
            'jumlah_benar' => 10,
            'jumlah_salah' => 2,
            'jumlah_kosong' => 0,
        ], $spOverrides));

        return compact('sekolah', 'paket', 'peserta', 'sesi', 'sp');
    }

    // ─── Index: Basic rendering ───────────────────────────────

    public function test_index_returns_laporan_page(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->get(route('dinas.laporan'));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.laporan.index');
        $response->assertViewHas(['sekolahList', 'paketList', 'sesiList', 'kelasList']);
    }

    // ─── Index: sesiList is returned ──────────────────────────

    public function test_index_returns_sesi_list(): void
    {
        $user = $this->dinasUser();
        $data = $this->createData();

        $response = $this->actingAs($user)->get(route('dinas.laporan'));

        $response->assertStatus(200);
        $response->assertViewHas('sesiList', function ($sesiList) use ($data) {
            return $sesiList->contains('id', $data['sesi']->id);
        });
    }

    // ─── Index: sesi_id filter ────────────────────────────────

    public function test_index_filters_by_sesi_id(): void
    {
        $user = $this->dinasUser();
        $d1 = $this->createData();

        // Create second sesi under same paket
        $peserta2 = Peserta::factory()->create(['sekolah_id' => $d1['sekolah']->id]);
        $sesi2 = SesiUjian::factory()->selesai()->create(['paket_id' => $d1['paket']->id]);
        SesiPeserta::factory()->submit()->create([
            'sesi_id'      => $sesi2->id,
            'peserta_id'   => $peserta2->id,
            'nilai_akhir'  => 70,
            'jumlah_benar' => 7,
            'jumlah_salah' => 3,
            'jumlah_kosong' => 0,
        ]);

        // Without filter → 2 results
        $response = $this->actingAs($user)->get(route('dinas.laporan'));
        $response->assertViewHas('data', fn ($data) => $data->total() === 2);

        // With sesi_id filter → 1 result
        $response = $this->actingAs($user)->get(route('dinas.laporan', ['sesi_id' => $d1['sesi']->id]));
        $response->assertViewHas('data', fn ($data) => $data->total() === 1);
    }

    // ─── Index: sekolah filter ────────────────────────────────

    public function test_index_with_sekolah_filter(): void
    {
        $user = $this->dinasUser();
        $d1 = $this->createData();

        $response = $this->actingAs($user)
            ->get(route('dinas.laporan', ['sekolah_id' => $d1['sekolah']->id]));

        $response->assertStatus(200);
        $response->assertViewHas('data', fn ($data) => $data->total() === 1);
    }

    // ─── Index: kelas filter ──────────────────────────────────

    public function test_index_filters_by_kelas(): void
    {
        $user = $this->dinasUser();
        $d1 = $this->createData();

        // Filter by kelas that exists
        $response = $this->actingAs($user)->get(route('dinas.laporan', ['kelas' => 'XI-2']));
        $response->assertViewHas('data', fn ($data) => $data->total() === 1);

        // Filter by kelas that doesn't exist
        $response = $this->actingAs($user)->get(route('dinas.laporan', ['kelas' => 'XII-99']));
        $response->assertViewHas('data', fn ($data) => $data->total() === 0);
    }

    // ─── Index: dinas sees ALL paket (no tampilkan_hasil filter) ──

    public function test_dinas_sees_paket_with_tampilkan_hasil_false(): void
    {
        $user = $this->dinasUser();
        $this->createData(['tampilkan_hasil' => false]);

        $response = $this->actingAs($user)->get(route('dinas.laporan'));

        $response->assertStatus(200);
        // Dinas should see the data even with tampilkan_hasil=false
        $response->assertViewHas('data', fn ($data) => $data->total() === 1);
    }

    public function test_dinas_paket_list_includes_hidden_paket(): void
    {
        $user = $this->dinasUser();
        $data = $this->createData(['tampilkan_hasil' => false]);

        $response = $this->actingAs($user)->get(route('dinas.laporan'));

        $response->assertViewHas('paketList', fn ($list) => $list->contains('id', $data['paket']->id));
    }

    // ─── Index: empty state ───────────────────────────────────

    public function test_index_without_filter_no_data(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->get(route('dinas.laporan'));

        $response->assertStatus(200);
        $response->assertViewHas('data', fn ($data) => $data->total() === 0);
    }

    // ─── Export ───────────────────────────────────────────────

    public function test_export_with_sesi_id_filter(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Export uses MySQL-specific SQL (LEFT function) in buildPerSoalAnalysis.');
        }
        $user = $this->dinasUser();
        $data = $this->createData();

        $response = $this->actingAs($user)->get(route('dinas.laporan.export', [
            'sesi_id' => $data['sesi']->id,
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheet',
            $response->headers->get('Content-Type', '')
        );
    }

    public function test_export_with_kelas_filter(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Export uses MySQL-specific SQL (LEFT function) in buildPerSoalAnalysis.');
        }
        $user = $this->dinasUser();
        $this->createData();

        $response = $this->actingAs($user)->get(route('dinas.laporan.export', [
            'kelas' => 'XI-2',
        ]));

        $response->assertStatus(200);
    }

    public function test_export_empty_redirects_with_warning(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->get(route('dinas.laporan.export'));

        $response->assertStatus(302);
        $response->assertSessionHas('warning');
    }

    // ─── Guest access ─────────────────────────────────────────

    public function test_guest_cannot_access_laporan(): void
    {
        $response = $this->get(route('dinas.laporan'));
        $response->assertRedirect(route('login'));
    }
}
