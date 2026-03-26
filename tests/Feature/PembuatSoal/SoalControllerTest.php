<?php

namespace Tests\Feature\PembuatSoal;

use App\Models\KategoriSoal;
use App\Models\NarasiSoal;
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
}
