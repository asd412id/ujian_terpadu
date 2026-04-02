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

    private function sekolahUser(?Sekolah $sekolah = null): User
    {
        $sekolah ??= Sekolah::factory()->create(['jenjang' => 'SMA']);

        return User::factory()->adminSekolah()->create([
            'sekolah_id' => $sekolah->id,
            'is_active'  => true,
        ]);
    }

    private function createSekolahData(User $user, array $paketOverrides = [], array $sesiOverrides = [], array $spOverrides = []): array
    {
        $paket = PaketUjian::factory()->aktif()->create(array_merge([
            'sekolah_id'      => $user->sekolah_id,
            'jenjang'         => 'SMA',
            'tampilkan_hasil' => true,
        ], $paketOverrides));

        $peserta = Peserta::factory()->create([
            'sekolah_id' => $user->sekolah_id,
            'kelas'      => 'XI-2',
        ]);

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

        return compact('paket', 'peserta', 'sesi', 'sp');
    }

    // ─── Index: Basic rendering ───────────────────────────────

    public function test_index_renders_for_own_school_user(): void
    {
        $user = $this->sekolahUser();
        $this->createSekolahData($user);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        $response->assertViewIs('sekolah.laporan.index');
        $response->assertViewHas(['paketList', 'data', 'rekap', 'kelasList', 'sesiList']);
    }

    public function test_index_hides_data_when_berlangsung_sesi_has_non_submitted_peserta(): void
    {
        $user = $this->sekolahUser();
        // berlangsung sesi with peserta still 'mengerjakan' (not submitted)
        $paket = PaketUjian::factory()->aktif()->create([
            'sekolah_id'      => $user->sekolah_id,
            'jenjang'         => 'SMA',
            'tampilkan_hasil' => true,
        ]);
        $peserta = Peserta::factory()->create(['sekolah_id' => $user->sekolah_id]);
        $sesi = SesiUjian::factory()->berlangsung()->create(['paket_id' => $paket->id]);
        // Peserta is still 'mengerjakan', not submitted
        SesiPeserta::factory()->mengerjakan()->create([
            'sesi_id'       => $sesi->id,
            'peserta_id'    => $peserta->id,
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        // No submitted results to show
        $response->assertViewHas('data', fn ($data) => $data->total() === 0);
    }

    public function test_index_shows_submitted_data_from_berlangsung_sesi(): void
    {
        $user = $this->sekolahUser();
        // berlangsung sesi but peserta already submitted — should be visible
        $paket = PaketUjian::factory()->aktif()->create([
            'sekolah_id'      => $user->sekolah_id,
            'jenjang'         => 'SMA',
            'tampilkan_hasil' => true,
        ]);
        $peserta = Peserta::factory()->create(['sekolah_id' => $user->sekolah_id]);
        $sesi = SesiUjian::factory()->berlangsung()->create(['paket_id' => $paket->id]);
        SesiPeserta::factory()->submit()->create([
            'sesi_id'       => $sesi->id,
            'peserta_id'    => $peserta->id,
            'nilai_akhir'   => 82,
            'jumlah_benar'  => 10,
            'jumlah_salah'  => 2,
            'jumlah_kosong' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        // Submitted results from berlangsung sesi ARE visible (all peserta already submitted)
        $response->assertViewHas('data', fn ($data) => $data->total() === 1);
    }

    public function test_index_shows_selesai_sesi_data(): void
    {
        $user = $this->sekolahUser();
        $this->createSekolahData($user);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        $response->assertViewHas('data', fn ($data) => $data->count() === 1);
    }

    // ─── Index: sesiList is returned ──────────────────────────

    public function test_index_returns_sesi_list(): void
    {
        $user = $this->sekolahUser();
        $data = $this->createSekolahData($user);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        $response->assertViewHas('sesiList', function ($sesiList) use ($data) {
            return $sesiList->contains('id', $data['sesi']->id);
        });
    }

    // ─── Index: sesi_id filter ────────────────────────────────

    public function test_index_filters_by_sesi_id(): void
    {
        $user = $this->sekolahUser();
        $d1 = $this->createSekolahData($user);

        // Create second sesi under same paket
        $peserta2 = Peserta::factory()->create(['sekolah_id' => $user->sekolah_id]);
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
        $response = $this->actingAs($user)->get(route('sekolah.laporan'));
        $response->assertViewHas('data', fn ($data) => $data->total() === 2);

        // With sesi_id filter → 1 result
        $response = $this->actingAs($user)->get(route('sekolah.laporan', ['sesi_id' => $d1['sesi']->id]));
        $response->assertViewHas('data', fn ($data) => $data->total() === 1);
    }

    // ─── Index: kelas filter ──────────────────────────────────

    public function test_index_filters_by_kelas(): void
    {
        $user = $this->sekolahUser();
        $paket = PaketUjian::factory()->aktif()->create([
            'sekolah_id'      => $user->sekolah_id,
            'jenjang'         => 'SMA',
            'tampilkan_hasil' => true,
        ]);
        $sesi = SesiUjian::factory()->selesai()->create(['paket_id' => $paket->id]);

        $pesertaA = Peserta::factory()->create(['sekolah_id' => $user->sekolah_id, 'kelas' => 'X-1']);
        $pesertaB = Peserta::factory()->create(['sekolah_id' => $user->sekolah_id, 'kelas' => 'XI-2']);

        SesiPeserta::factory()->submit()->create([
            'sesi_id' => $sesi->id, 'peserta_id' => $pesertaA->id,
            'nilai_akhir' => 80, 'jumlah_benar' => 8, 'jumlah_salah' => 2, 'jumlah_kosong' => 0,
        ]);
        SesiPeserta::factory()->submit()->create([
            'sesi_id' => $sesi->id, 'peserta_id' => $pesertaB->id,
            'nilai_akhir' => 70, 'jumlah_benar' => 7, 'jumlah_salah' => 3, 'jumlah_kosong' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan', ['kelas' => 'X-1']));
        $response->assertViewHas('data', fn ($data) => $data->total() === 1);
    }

    // ─── tampilkan_hasil enforcement ──────────────────────────

    public function test_index_hides_paket_with_tampilkan_hasil_false(): void
    {
        $user = $this->sekolahUser();
        $this->createSekolahData($user, ['tampilkan_hasil' => false]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        // Data should be empty because tampilkan_hasil=false
        $response->assertViewHas('data', fn ($data) => $data->count() === 0);
        // Paket list should also be empty
        $response->assertViewHas('paketList', fn ($list) => $list->isEmpty());
    }

    public function test_index_shows_paket_with_tampilkan_hasil_true(): void
    {
        $user = $this->sekolahUser();
        $data = $this->createSekolahData($user, ['tampilkan_hasil' => true]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        $response->assertViewHas('data', fn ($d) => $d->count() === 1);
        $response->assertViewHas('paketList', fn ($list) => $list->contains('id', $data['paket']->id));
    }

    public function test_sesi_list_excludes_sesi_from_hidden_paket(): void
    {
        $user = $this->sekolahUser();
        // Create one visible and one hidden paket
        $visible = $this->createSekolahData($user, ['tampilkan_hasil' => true]);
        $hidden = $this->createSekolahData($user, ['tampilkan_hasil' => false]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertViewHas('sesiList', function ($sesiList) use ($visible, $hidden) {
            $ids = $sesiList->pluck('id')->toArray();
            return in_array($visible['sesi']->id, $ids)
                && ! in_array($hidden['sesi']->id, $ids);
        });
    }

    // ─── tampilkan_hasil: rekap only counts visible data ──────

    public function test_rekap_excludes_hidden_paket_data(): void
    {
        $user = $this->sekolahUser();
        $this->createSekolahData($user, ['tampilkan_hasil' => true], [], ['nilai_akhir' => 90]);
        $this->createSekolahData($user, ['tampilkan_hasil' => false], [], ['nilai_akhir' => 50]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertViewHas('rekap', function ($rekap) {
            // Only the visible paket (nilai=90) should be counted
            return $rekap['total_peserta'] === 1
                && $rekap['sangat_baik'] === 1;
        });
    }

    // ─── Cross-school isolation ───────────────────────────────

    public function test_cannot_see_other_school_data(): void
    {
        $user = $this->sekolahUser();
        $otherSekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $otherPaket = PaketUjian::factory()->aktif()->create([
            'sekolah_id'      => $otherSekolah->id,
            'jenjang'         => 'SMA',
            'tampilkan_hasil' => true,
        ]);
        $otherPeserta = Peserta::factory()->create(['sekolah_id' => $otherSekolah->id]);
        $otherSesi = SesiUjian::factory()->selesai()->create(['paket_id' => $otherPaket->id]);
        SesiPeserta::factory()->submit()->create([
            'sesi_id'      => $otherSesi->id,
            'peserta_id'   => $otherPeserta->id,
            'nilai_akhir'  => 95,
            'jumlah_benar' => 19,
            'jumlah_salah' => 1,
            'jumlah_kosong' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        $response->assertViewHas('data', fn ($data) => $data->count() === 0);
    }

    // ─── Export route ─────────────────────────────────────────

    public function test_export_route_exists(): void
    {
        $user = $this->sekolahUser();

        $response = $this->actingAs($user)->get(route('sekolah.laporan.export'));

        // Should redirect back with warning (no data)
        $response->assertStatus(302);
        $response->assertSessionHas('warning');
    }

    public function test_export_returns_xlsx_with_data(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Export uses MySQL-specific SQL (LEFT function) in buildPerSoalAnalysis.');
        }
        $user = $this->sekolahUser();
        $this->createSekolahData($user);

        $response = $this->actingAs($user)->get(route('sekolah.laporan.export'));

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheet',
            $response->headers->get('Content-Type', '')
        );
    }

    public function test_export_respects_tampilkan_hasil(): void
    {
        $user = $this->sekolahUser();
        // Only create data with tampilkan_hasil=false
        $this->createSekolahData($user, ['tampilkan_hasil' => false]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan.export'));

        // Should redirect back with warning since no exportable data
        $response->assertStatus(302);
        $response->assertSessionHas('warning');
    }

    public function test_export_with_sesi_id_filter(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Export uses MySQL-specific SQL (LEFT function) in buildPerSoalAnalysis.');
        }
        $user = $this->sekolahUser();
        $data = $this->createSekolahData($user);

        $response = $this->actingAs($user)->get(route('sekolah.laporan.export', [
            'sesi_id' => $data['sesi']->id,
        ]));

        $response->assertStatus(200);
    }

    public function test_export_with_kelas_filter(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Export uses MySQL-specific SQL (LEFT function) in buildPerSoalAnalysis.');
        }
        $user = $this->sekolahUser();
        $this->createSekolahData($user);

        $response = $this->actingAs($user)->get(route('sekolah.laporan.export', [
            'kelas' => 'XI-2',
        ]));

        $response->assertStatus(200);
    }

    // ─── Guest access ─────────────────────────────────────────

    public function test_guest_cannot_access_index(): void
    {
        $response = $this->get(route('sekolah.laporan'));
        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_export(): void
    {
        $response = $this->get(route('sekolah.laporan.export'));
        $response->assertRedirect(route('login'));
    }

    // ─── Global paket (sekolah_id=null) with jenjang match ───

    public function test_index_shows_global_paket_matching_jenjang(): void
    {
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $user = $this->sekolahUser($sekolah);

        // Global paket (sekolah_id = null, jenjang = SMA)
        $paket = PaketUjian::factory()->aktif()->create([
            'sekolah_id'      => null,
            'jenjang'         => 'SMA',
            'tampilkan_hasil' => true,
        ]);
        $peserta = Peserta::factory()->create(['sekolah_id' => $sekolah->id]);
        $sesi = SesiUjian::factory()->selesai()->create(['paket_id' => $paket->id]);
        SesiPeserta::factory()->submit()->create([
            'sesi_id'      => $sesi->id,
            'peserta_id'   => $peserta->id,
            'nilai_akhir'  => 75,
            'jumlah_benar' => 15,
            'jumlah_salah' => 5,
            'jumlah_kosong' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        $response->assertViewHas('data', fn ($data) => $data->count() === 1);
        $response->assertViewHas('paketList', fn ($list) => $list->contains('id', $paket->id));
    }

    public function test_index_hides_global_paket_mismatching_jenjang(): void
    {
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $user = $this->sekolahUser($sekolah);

        // Global paket with different jenjang
        $paket = PaketUjian::factory()->aktif()->create([
            'sekolah_id'      => null,
            'jenjang'         => 'SMP',
            'tampilkan_hasil' => true,
        ]);
        $peserta = Peserta::factory()->create(['sekolah_id' => $sekolah->id]);
        $sesi = SesiUjian::factory()->selesai()->create(['paket_id' => $paket->id]);
        SesiPeserta::factory()->submit()->create([
            'sesi_id'      => $sesi->id,
            'peserta_id'   => $peserta->id,
            'nilai_akhir'  => 75,
            'jumlah_benar' => 15,
            'jumlah_salah' => 5,
            'jumlah_kosong' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        $response->assertViewHas('data', fn ($data) => $data->count() === 0);
    }

    // ─── Global paket with jenjang SEMUA ──────────────────────

    public function test_index_shows_global_paket_with_jenjang_semua(): void
    {
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMP']);
        $user = $this->sekolahUser($sekolah);

        $paket = PaketUjian::factory()->aktif()->create([
            'sekolah_id'      => null,
            'jenjang'         => 'SEMUA',
            'tampilkan_hasil' => true,
        ]);
        $peserta = Peserta::factory()->create(['sekolah_id' => $sekolah->id]);
        $sesi = SesiUjian::factory()->selesai()->create(['paket_id' => $paket->id]);
        SesiPeserta::factory()->submit()->create([
            'sesi_id'      => $sesi->id,
            'peserta_id'   => $peserta->id,
            'nilai_akhir'  => 60,
            'jumlah_benar' => 6,
            'jumlah_salah' => 4,
            'jumlah_kosong' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.laporan'));

        $response->assertStatus(200);
        $response->assertViewHas('data', fn ($data) => $data->count() === 1);
    }
}
