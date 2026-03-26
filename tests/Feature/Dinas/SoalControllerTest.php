<?php

namespace Tests\Feature\Dinas;

use App\Models\KategoriSoal;
use App\Models\NarasiSoal;
use App\Models\OpsiJawaban;
use App\Models\PaketUjian;
use App\Models\Peserta;
use App\Models\SesiUjian;
use App\Models\Sekolah;
use App\Models\Soal;
use App\Models\User;
use App\Support\HtmlDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SoalControllerTest extends TestCase
{
    use RefreshDatabase;

    private function dinasUser(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }

    public function test_index_returns_soal_list(): void
    {
        $user = $this->dinasUser();
        Soal::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('dinas.soal.index'));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.soal.index');
    }

    public function test_index_filters_by_kategori(): void
    {
        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create();
        Soal::factory()->count(2)->create(['kategori_id' => $kategori->id]);
        Soal::factory()->count(3)->create();

        $response = $this->actingAs($user)
            ->get(route('dinas.soal.index', ['kategori' => $kategori->id]));

        $response->assertStatus(200);
    }

    public function test_index_filters_by_tipe(): void
    {
        $user = $this->dinasUser();
        Soal::factory()->pg()->count(2)->create();
        Soal::factory()->essay()->count(1)->create();

        $response = $this->actingAs($user)
            ->get(route('dinas.soal.index', ['tipe' => 'pg']));

        $response->assertStatus(200);
    }

    public function test_create_page(): void
    {
        $user = $this->dinasUser();
        $response = $this->actingAs($user)->get(route('dinas.soal.create'));
        $response->assertStatus(200);
    }

    public function test_store_pg_soal(): void
    {
        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create();

        $response = $this->actingAs($user)->post(route('dinas.soal.store'), [
            'kategori_soal_id'  => $kategori->id,
            'jenis_soal'        => 'pilihan_ganda',
            'pertanyaan'        => 'Berapa 1+1?',
            'tingkat_kesulitan' => 'mudah',
            'bobot'             => 1,
            'opsi_teks'         => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat'],
            'opsi_benar'        => ['B'],
        ]);

        $response->assertRedirect(route('dinas.soal.index'));
        $this->assertDatabaseHas('soal', ['pertanyaan' => 'Berapa 1+1?', 'tipe_soal' => 'pg']);
    }

    public function test_store_essay_soal(): void
    {
        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create();

        $response = $this->actingAs($user)->post(route('dinas.soal.store'), [
            'kategori_soal_id'  => $kategori->id,
            'jenis_soal'        => 'essay',
            'pertanyaan'        => 'Jelaskan proses fotosintesis.',
            'tingkat_kesulitan' => 'sedang',
            'bobot'             => 5,
        ]);

        $response->assertRedirect(route('dinas.soal.index'));
        $this->assertDatabaseHas('soal', ['tipe_soal' => 'essay']);
    }

    public function test_store_validation_fails(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->post(route('dinas.soal.store'), [
            'pertanyaan' => 'No category',
        ]);

        $response->assertSessionHasErrors(['kategori_soal_id', 'jenis_soal', 'tingkat_kesulitan', 'bobot']);
    }

    public function test_edit_page(): void
    {
        $user = $this->dinasUser();
        $soal = Soal::factory()->create();

        $response = $this->actingAs($user)->get(route('dinas.soal.edit', $soal));
        $response->assertStatus(200);
    }

    public function test_index_filters_narasi_by_formatted_konten(): void
    {
        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create();

        $matchingNarasi = NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi cocok isi',
            'konten' => '<p>kata</p><p><strong>kunci</strong> isi narasi</p>',
            'is_active' => true,
        ]);

        NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi tidak cocok',
            'konten' => '<p>isi lain</p>',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dinas.soal.index', [
            'tab' => 'narasi',
            'narasi_search' => 'kata kunci isi narasi',
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('narasis', fn ($narasis) => $narasis->total() === 1
            && $narasis->getCollection()->contains(fn ($narasi) => $narasi->id === $matchingNarasi->id));
    }

    public function test_index_filters_narasi_by_zero_keyword(): void
    {
        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create();

        $matchingNarasi = NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi angka nol',
            'konten' => '<p>Bab 0 dimulai dari sini</p>',
            'is_active' => true,
        ]);

        NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi angka lain',
            'konten' => '<p>Bab 1 dimulai dari sini</p>',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dinas.soal.index', [
            'tab' => 'narasi',
            'narasi_search' => '0',
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('narasis', fn ($narasis) => $narasis->total() === 1
            && $narasis->getCollection()->contains(fn ($narasi) => $narasi->id === $matchingNarasi->id));
    }

    public function test_show_decodes_html_entities_in_soal_and_narasi_rendering(): void
    {
        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create();
        $narasi = NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi quote',
            'konten' => '&lt;p&gt;Narasi &quot;penting&quot; untuk siswa.&lt;/p&gt;',
            'is_active' => true,
        ]);
        $soal = Soal::factory()->essay()->create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'narasi_id' => $narasi->id,
            'pertanyaan' => 'Apa arti &quot;adil&quot;?',
            'pembahasan' => 'Bahas &quot;adil&quot; dengan contoh.',
        ]);
        OpsiJawaban::create([
            'soal_id' => $soal->id,
            'label' => 'KUNCI',
            'teks' => 'Makna &quot;adil&quot; adalah seimbang.',
            'urutan' => 1,
            'is_benar' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dinas.soal.show', $soal));

        $response->assertStatus(200);
        $response->assertSee('Narasi "penting" untuk siswa.', false);
        $response->assertSee('Apa arti "adil"?', false);
        $response->assertSee('Makna "adil" adalah seimbang.', false);
        $response->assertSee('Bahas "adil" dengan contoh.', false);
        $response->assertDontSee('&lt;p&gt;Narasi &quot;penting&quot; untuk siswa.&lt;/p&gt;', false);
        $response->assertDontSee('Apa arti &quot;adil&quot;?', false);
    }

    public function test_plain_text_keeps_literal_encoded_tags_visible(): void
    {
        $this->assertSame('Gunakan <div> dan <span>', HtmlDisplay::plainText('Gunakan &lt;div&gt; dan &lt;span&gt;'));
        $this->assertSame('Apa fungsi tag <option>?', HtmlDisplay::plainText('<p>Apa fungsi tag &lt;option&gt;?</p>'));
    }

    public function test_destroy_deactivates_soal(): void
    {
        $user = $this->dinasUser();
        $soal = Soal::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->delete(route('dinas.soal.destroy', $soal));

        $response->assertRedirect(route('dinas.soal.index'));
    }

    public function test_destroy_detaches_related_soal_before_soft_deleting_narasi(): void
    {
        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create();
        $narasi = NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi utama',
            'konten' => '<p>Isi narasi utama</p>',
            'is_active' => true,
        ]);
        $soal = Soal::factory()->create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'narasi_id' => $narasi->id,
            'urutan_dalam_narasi' => 2,
        ]);

        $response = $this->actingAs($user)->delete(route('dinas.narasi.destroy', $narasi));

        $response->assertRedirect(route('dinas.soal.index', ['tab' => 'narasi']));
        $this->assertSoftDeleted('narasi_soal', ['id' => $narasi->id]);
        $this->assertDatabaseHas('soal', [
            'id' => $soal->id,
            'narasi_id' => null,
            'urutan_dalam_narasi' => 0,
        ]);
    }

    public function test_destroy_all_narasi_by_kategori_only_removes_target_narasi_and_detaches_related_soal(): void
    {
        $user = $this->dinasUser();
        $targetKategori = KategoriSoal::factory()->create();
        $otherKategori = KategoriSoal::factory()->create();

        $targetNarasi = NarasiSoal::create([
            'kategori_id' => $targetKategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi target',
            'konten' => '<p>Target</p>',
            'is_active' => true,
        ]);
        $otherNarasi = NarasiSoal::create([
            'kategori_id' => $otherKategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi lain',
            'konten' => '<p>Lain</p>',
            'is_active' => true,
        ]);

        $targetSoal = Soal::factory()->create([
            'kategori_id' => $targetKategori->id,
            'created_by' => $user->id,
            'narasi_id' => $targetNarasi->id,
            'urutan_dalam_narasi' => 1,
        ]);
        $otherSoal = Soal::factory()->create([
            'kategori_id' => $otherKategori->id,
            'created_by' => $user->id,
            'narasi_id' => $otherNarasi->id,
            'urutan_dalam_narasi' => 1,
        ]);

        $response = $this->actingAs($user)->delete(route('dinas.narasi.destroy-all'), [
            'kategori' => $targetKategori->id,
        ]);

        $response->assertRedirect(route('dinas.soal.index', ['tab' => 'narasi']));
        $this->assertSoftDeleted('narasi_soal', ['id' => $targetNarasi->id]);
        $this->assertDatabaseHas('narasi_soal', ['id' => $otherNarasi->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('soal', [
            'id' => $targetSoal->id,
            'narasi_id' => null,
            'urutan_dalam_narasi' => 0,
        ]);
        $this->assertDatabaseHas('soal', [
            'id' => $otherSoal->id,
            'narasi_id' => $otherNarasi->id,
            'urutan_dalam_narasi' => 1,
        ]);
    }

    public function test_delete_narasi_removes_soal_gambar_assets_after_commit(): void
    {
        Storage::fake('public');

        $user = $this->dinasUser();
        $kategori = KategoriSoal::factory()->create();
        Storage::disk('public')->put('soal/gambar/narasi-test.png', 'dummy image');
        $narasi = NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi bergambar',
            'konten' => '<p><img src="/storage/soal/gambar/narasi-test.png" alt="narasi"></p>',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->delete(route('dinas.narasi.destroy', $narasi));

        $response->assertRedirect(route('dinas.soal.index', ['tab' => 'narasi']));
        Storage::disk('public')->assertMissing('soal/gambar/narasi-test.png');
    }

    public function test_store_manual_mode_redirects_to_peserta_page_without_auto_enrolling(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $paket = PaketUjian::factory()->create([
            'created_by' => $user->id,
            'sekolah_id' => $sekolah->id,
            'jenjang' => 'SMA',
        ]);

        Peserta::factory()->count(2)->create([
            'sekolah_id' => $sekolah->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('dinas.paket.sesi.store', $paket), [
            'nama_sesi' => 'Sesi Manual',
            'peserta_mode' => 'manual',
        ]);

        $sesi = SesiUjian::query()->where('paket_id', $paket->id)->where('nama_sesi', 'Sesi Manual')->firstOrFail();

        $response->assertRedirect(route('dinas.paket.sesi.peserta', [$paket, $sesi]));
        $this->assertTrue($sesi->is_peserta_override);
        $this->assertDatabaseCount('sesi_peserta', 0);
    }

    public function test_store_all_mode_enrolls_matching_peserta_only(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $otherSekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $paket = PaketUjian::factory()->create([
            'created_by' => $user->id,
            'sekolah_id' => $sekolah->id,
            'jenjang' => 'SMA',
        ]);

        $eligible = Peserta::factory()->count(2)->create([
            'sekolah_id' => $sekolah->id,
            'is_active' => true,
        ]);

        Peserta::factory()->create([
            'sekolah_id' => $otherSekolah->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('dinas.paket.sesi.store', $paket), [
            'nama_sesi' => 'Sesi Semua',
            'peserta_mode' => 'all',
        ]);

        $sesi = SesiUjian::query()->where('paket_id', $paket->id)->where('nama_sesi', 'Sesi Semua')->firstOrFail();

        $response->assertRedirect();
        $this->assertFalse($sesi->is_peserta_override);
        $this->assertCount(2, $sesi->fresh()->sesiPeserta);
        $this->assertEqualsCanonicalizing(
            $eligible->pluck('id')->all(),
            $sesi->fresh()->sesiPeserta->pluck('peserta_id')->all(),
        );
    }

    public function test_store_all_mode_with_kapasitas_uses_alphabetical_peserta_order(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $paket = PaketUjian::factory()->create([
            'created_by' => $user->id,
            'sekolah_id' => $sekolah->id,
            'jenjang' => 'SMA',
        ]);

        $charlie = Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'nama' => 'Charlie',
            'is_active' => true,
        ]);
        $alpha = Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'nama' => 'Alpha',
            'is_active' => true,
        ]);
        $bravo = Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'nama' => 'Bravo',
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('dinas.paket.sesi.store', $paket), [
            'nama_sesi' => 'Sesi Semua Kapasitas',
            'peserta_mode' => 'all',
            'kapasitas' => 2,
        ])->assertRedirect();

        $sesi = SesiUjian::query()->where('paket_id', $paket->id)->where('nama_sesi', 'Sesi Semua Kapasitas')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$alpha->id, $bravo->id],
            $sesi->fresh()->sesiPeserta->pluck('peserta_id')->all(),
        );
        $this->assertDatabaseMissing('sesi_peserta', [
            'sesi_id' => $sesi->id,
            'peserta_id' => $charlie->id,
        ]);
    }

    public function test_add_all_peserta_uses_search_filter_and_switches_to_manual_override(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMK']);
        $paket = PaketUjian::factory()->create([
            'created_by' => $user->id,
            'sekolah_id' => $sekolah->id,
            'jenjang' => 'SMK',
        ]);
        $sesi = SesiUjian::factory()->create([
            'paket_id' => $paket->id,
            'status' => 'persiapan',
            'is_peserta_override' => true,
        ]);

        $target = Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'jurusan' => 'Teknik Mesin',
            'is_active' => true,
        ]);

        Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'jurusan' => 'Akuntansi',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('dinas.paket.sesi.peserta.add-all', [$paket, $sesi]), [
            'search' => 'Mesin',
        ]);

        $response->assertRedirect();
        $this->assertTrue($sesi->fresh()->is_peserta_override);
        $this->assertDatabaseHas('sesi_peserta', [
            'sesi_id' => $sesi->id,
            'peserta_id' => $target->id,
        ]);
        $this->assertDatabaseCount('sesi_peserta', 1);
    }

    public function test_add_all_peserta_keeps_auto_mode_when_no_matching_peserta_found(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMK']);
        $paket = PaketUjian::factory()->create([
            'created_by' => $user->id,
            'sekolah_id' => $sekolah->id,
            'jenjang' => 'SMK',
        ]);
        $sesi = SesiUjian::factory()->create([
            'paket_id' => $paket->id,
            'status' => 'persiapan',
            'is_peserta_override' => false,
        ]);

        Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'jurusan' => 'Akuntansi',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('dinas.paket.sesi.peserta.add-all', [$paket, $sesi]), [
            'search' => 'Mesin',
        ]);

        $response->assertRedirect();
        $this->assertFalse($sesi->fresh()->is_peserta_override);
        $this->assertDatabaseCount('sesi_peserta', 0);
    }

    public function test_add_all_peserta_respects_kapasitas_limit(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $paket = PaketUjian::factory()->create([
            'created_by' => $user->id,
            'sekolah_id' => $sekolah->id,
            'jenjang' => 'SMA',
        ]);
        $sesi = SesiUjian::factory()->create([
            'paket_id' => $paket->id,
            'status' => 'persiapan',
            'kapasitas' => 2,
            'is_peserta_override' => true,
        ]);

        Peserta::factory()->count(3)->create([
            'sekolah_id' => $sekolah->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('dinas.paket.sesi.peserta.add-all', [$paket, $sesi]));

        $response->assertRedirect();
        $this->assertDatabaseCount('sesi_peserta', 2);
    }

    public function test_add_all_peserta_rejects_non_persiapan_sesi(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $paket = PaketUjian::factory()->create([
            'created_by' => $user->id,
            'sekolah_id' => $sekolah->id,
            'jenjang' => 'SMA',
        ]);
        $sesi = SesiUjian::factory()->create([
            'paket_id' => $paket->id,
            'status' => 'berlangsung',
            'is_peserta_override' => true,
        ]);

        Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('dinas.paket.sesi.peserta.add-all', [$paket, $sesi]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('sesi_peserta', 0);
    }

    public function test_manual_add_only_accepts_peserta_from_available_scope(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $otherSekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $paket = PaketUjian::factory()->create([
            'created_by' => $user->id,
            'sekolah_id' => $sekolah->id,
            'jenjang' => 'SMA',
        ]);
        $sesi = SesiUjian::factory()->create([
            'paket_id' => $paket->id,
            'status' => 'persiapan',
            'is_peserta_override' => true,
        ]);

        $validPeserta = Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'is_active' => true,
        ]);
        $invalidPeserta = Peserta::factory()->create([
            'sekolah_id' => $otherSekolah->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('dinas.paket.sesi.peserta.add', [$paket, $sesi]), [
            'peserta_ids' => [$validPeserta->id, $invalidPeserta->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sesi_peserta', [
            'sesi_id' => $sesi->id,
            'peserta_id' => $validPeserta->id,
        ]);
        $this->assertDatabaseMissing('sesi_peserta', [
            'sesi_id' => $sesi->id,
            'peserta_id' => $invalidPeserta->id,
        ]);
    }

    public function test_manual_add_rejects_non_persiapan_sesi(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $paket = PaketUjian::factory()->create([
            'created_by' => $user->id,
            'sekolah_id' => $sekolah->id,
            'jenjang' => 'SMA',
        ]);
        $sesi = SesiUjian::factory()->create([
            'paket_id' => $paket->id,
            'status' => 'selesai',
            'is_peserta_override' => true,
        ]);
        $peserta = Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('dinas.paket.sesi.peserta.add', [$paket, $sesi]), [
            'peserta_ids' => [$peserta->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('sesi_peserta', 0);
    }

    public function test_manual_add_rejects_duplicate_peserta_ids(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $paket = PaketUjian::factory()->create([
            'created_by' => $user->id,
            'sekolah_id' => $sekolah->id,
            'jenjang' => 'SMA',
        ]);
        $sesi = SesiUjian::factory()->create([
            'paket_id' => $paket->id,
            'status' => 'persiapan',
            'is_peserta_override' => true,
        ]);
        $peserta = Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'is_active' => true,
        ]);

        $response = $this->from(route('dinas.paket.sesi.peserta', [$paket, $sesi]))
            ->actingAs($user)
            ->post(route('dinas.paket.sesi.peserta.add', [$paket, $sesi]), [
                'peserta_ids' => [$peserta->id, $peserta->id],
            ]);

        $response->assertRedirect(route('dinas.paket.sesi.peserta', [$paket, $sesi]));
        $response->assertSessionHasErrors('peserta_ids.1');
        $this->assertDatabaseCount('sesi_peserta', 0);
    }

    public function test_remove_without_removable_peserta_keeps_auto_mode(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $paket = PaketUjian::factory()->create([
            'created_by' => $user->id,
            'sekolah_id' => $sekolah->id,
            'jenjang' => 'SMA',
        ]);
        $sesi = SesiUjian::factory()->create([
            'paket_id' => $paket->id,
            'status' => 'persiapan',
            'is_peserta_override' => false,
        ]);
        $peserta = Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('dinas.paket.sesi.peserta.remove', [$paket, $sesi]), [
            'peserta_ids' => [$peserta->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('info');
        $this->assertFalse($sesi->fresh()->is_peserta_override);
    }

    public function test_remove_reset_and_sync_reject_non_persiapan_sesi(): void
    {
        $user = $this->dinasUser();
        $sekolah = Sekolah::factory()->create(['jenjang' => 'SMA']);
        $paket = PaketUjian::factory()->create([
            'created_by' => $user->id,
            'sekolah_id' => $sekolah->id,
            'jenjang' => 'SMA',
        ]);
        $sesi = SesiUjian::factory()->create([
            'paket_id' => $paket->id,
            'status' => 'berlangsung',
            'is_peserta_override' => true,
        ]);
        $peserta = Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('dinas.paket.sesi.peserta.add', [$paket, $sesi]), [
            'peserta_ids' => [$peserta->id],
        ]);

        $removeResponse = $this->actingAs($user)->post(route('dinas.paket.sesi.peserta.remove', [$paket, $sesi]), [
            'peserta_ids' => [$peserta->id],
        ]);
        $resetResponse = $this->actingAs($user)->post(route('dinas.paket.sesi.peserta.reset', [$paket, $sesi]));
        $syncResponse = $this->actingAs($user)->post(route('dinas.paket.sesi.peserta.sync', [$paket, $sesi]));

        $removeResponse->assertSessionHas('error');
        $resetResponse->assertSessionHas('error');
        $syncResponse->assertSessionHas('error');
    }
}
