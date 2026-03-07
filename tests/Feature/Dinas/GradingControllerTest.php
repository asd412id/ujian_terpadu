<?php

namespace Tests\Feature\Dinas;

use App\Models\JawabanPeserta;
use App\Models\SesiPeserta;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function dinasUser(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }

    public function test_index_returns_grading_page(): void
    {
        $user = $this->dinasUser();

        $response = $this->actingAs($user)->get(route('dinas.grading'));

        $response->assertStatus(200);
        $response->assertViewIs('dinas.grading.index');
        $response->assertViewHas('jawabans');
    }

    public function test_index_shows_ungraded_essays(): void
    {
        $user = $this->dinasUser();
        $soalEssay = Soal::factory()->essay()->create();
        $sp = SesiPeserta::factory()->submit()->create();

        JawabanPeserta::factory()->essay('Jawaban essay panjang')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soalEssay->id,
            'skor_manual'     => null,
        ]);

        $response = $this->actingAs($user)->get(route('dinas.grading'));

        $response->assertStatus(200);
        $response->assertViewHas('totalBelumDinilai', 1);
    }

    public function test_nilai_saves_manual_score(): void
    {
        $user = $this->dinasUser();
        $soalEssay = Soal::factory()->essay()->create();
        $sp = SesiPeserta::factory()->submit()->create();

        $jawaban = JawabanPeserta::factory()->essay('Jawaban saya')->create([
            'sesi_peserta_id' => $sp->id,
            'soal_id'         => $soalEssay->id,
            'skor_manual'     => null,
        ]);

        $response = $this->actingAs($user)->post(route('dinas.grading.nilai', $jawaban), [
            'skor_manual'     => 85,
            'catatan_penilai' => 'Cukup baik',
        ]);

        $response->assertRedirect();
        $jawaban->refresh();
        $this->assertEquals(85, $jawaban->skor_manual);
        $this->assertEquals('Cukup baik', $jawaban->catatan_penilai);
        $this->assertEquals($user->id, $jawaban->dinilai_oleh);
    }

    public function test_nilai_validation_fails(): void
    {
        $user = $this->dinasUser();
        $jawaban = JawabanPeserta::factory()->create();

        $response = $this->actingAs($user)->post(route('dinas.grading.nilai', $jawaban), [
            'skor_manual' => 150,
        ]);

        $response->assertSessionHasErrors('skor_manual');
    }
}
