<?php

namespace Tests\Feature\Dinas;

use App\Models\KategoriSoal;
use App\Models\NarasiSoal;
use App\Models\OpsiJawaban;
use App\Models\Soal;
use App\Models\User;
use App\Support\HtmlDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
