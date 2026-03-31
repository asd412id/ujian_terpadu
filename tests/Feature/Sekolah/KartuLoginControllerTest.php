<?php

namespace Tests\Feature\Sekolah;

use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KartuLoginControllerTest extends TestCase
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

    public function test_index_returns_kartu_page(): void
    {
        $user = $this->sekolahUser();
        Peserta::factory()->count(3)->create(['sekolah_id' => $user->sekolah_id]);

        $response = $this->actingAs($user)->get(route('sekolah.kartu.index'));

        $response->assertStatus(200);
        $response->assertViewIs('sekolah.kartu.index');
        $response->assertViewHas('peserta');
    }

    public function test_index_filter_by_kelas(): void
    {
        $user = $this->sekolahUser();
        Peserta::factory()->create(['sekolah_id' => $user->sekolah_id, 'kelas' => 'XII-1']);
        Peserta::factory()->create(['sekolah_id' => $user->sekolah_id, 'kelas' => 'XII-2']);

        $response = $this->actingAs($user)
            ->get(route('sekolah.kartu.index', ['kelas' => 'XII-1']));

        $response->assertStatus(200);
    }

    public function test_show_single_kartu(): void
    {
        $user = $this->sekolahUser();
        $peserta = Peserta::factory()->create([
            'sekolah_id'   => $user->sekolah_id,
            'password_plain' => encrypt('TEST1234'),
        ]);

        $response = $this->actingAs($user)->get(route('sekolah.kartu.show', $peserta));

        $response->assertStatus(200);
    }
}
