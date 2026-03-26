<?php

namespace Tests\Feature\PembuatSoal;

use App\Models\KategoriSoal;
use App\Models\NarasiSoal;
use App\Models\OpsiJawaban;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoalControllerTest extends TestCase
{
    use RefreshDatabase;

    private function pembuatSoalUser(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_PEMBUAT_SOAL,
            'is_active' => true,
        ]);
    }

    public function test_index_filters_narasi_by_formatted_konten_for_current_user(): void
    {
        $user = $this->pembuatSoalUser();
        $otherUser = $this->pembuatSoalUser();
        $kategori = KategoriSoal::factory()->create();

        $matchingNarasi = NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi milik sendiri',
            'konten' => '<p>kata</p><p><strong>kunci</strong> isi pembuat soal</p>',
            'is_active' => true,
        ]);

        NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi lain',
            'konten' => '<p>isi yang berbeda</p>',
            'is_active' => true,
        ]);

        NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $otherUser->id,
            'judul' => 'Narasi user lain',
            'konten' => '<p>kata kunci isi pembuat soal</p>',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('pembuat-soal.soal.index', [
            'tab' => 'narasi',
            'narasi_search' => 'kata kunci isi pembuat soal',
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('pembuat-soal.soal.index');
        $response->assertViewHas('narasis', fn ($narasis) => $narasis->total() === 1
            && $narasis->getCollection()->contains(fn ($narasi) => $narasi->id === $matchingNarasi->id));
    }

    public function test_index_filters_narasi_by_zero_keyword_for_current_user(): void
    {
        $user = $this->pembuatSoalUser();
        $otherUser = $this->pembuatSoalUser();
        $kategori = KategoriSoal::factory()->create();

        $matchingNarasi = NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi angka nol',
            'konten' => '<p>Level 0 milik saya</p>',
            'is_active' => true,
        ]);

        NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi angka lain',
            'konten' => '<p>Level 1 milik saya</p>',
            'is_active' => true,
        ]);

        NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $otherUser->id,
            'judul' => 'Narasi user lain',
            'konten' => '<p>Level 0 milik user lain</p>',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('pembuat-soal.soal.index', [
            'tab' => 'narasi',
            'narasi_search' => '0',
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('pembuat-soal.soal.index');
        $response->assertViewHas('narasis', fn ($narasis) => $narasis->total() === 1
            && $narasis->getCollection()->contains(fn ($narasi) => $narasi->id === $matchingNarasi->id));
    }

    public function test_show_decodes_html_entities_in_soal_and_narasi_rendering_for_owner(): void
    {
        $user = $this->pembuatSoalUser();
        $kategori = KategoriSoal::factory()->create();
        $narasi = NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi quote',
            'konten' => '&lt;p&gt;Narasi &quot;pemilik&quot; untuk soal.&lt;/p&gt;',
            'is_active' => true,
        ]);
        $soal = Soal::factory()->essay()->create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'narasi_id' => $narasi->id,
            'pertanyaan' => 'Apa arti &quot;jujur&quot;?',
            'pembahasan' => 'Bahas &quot;jujur&quot; dengan contoh.',
        ]);
        OpsiJawaban::create([
            'soal_id' => $soal->id,
            'label' => 'KUNCI',
            'teks' => 'Makna &quot;jujur&quot; adalah berkata benar.',
            'urutan' => 1,
            'is_benar' => true,
        ]);

        $response = $this->actingAs($user)->get(route('pembuat-soal.soal.show', $soal));

        $response->assertStatus(200);
        $response->assertSee('Narasi "pemilik" untuk soal.', false);
        $response->assertSee('Apa arti "jujur"?', false);
        $response->assertSee('Makna "jujur" adalah berkata benar.', false);
        $response->assertSee('Bahas "jujur" dengan contoh.', false);
        $response->assertDontSee('&lt;p&gt;Narasi &quot;pemilik&quot; untuk soal.&lt;/p&gt;', false);
        $response->assertDontSee('Apa arti &quot;jujur&quot;?', false);
    }
}
