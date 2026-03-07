<?php

namespace Tests\Unit\Models;

use App\Models\KategoriSoal;
use App\Models\OpsiJawaban;
use App\Models\PaketSoal;
use App\Models\PasanganSoal;
use App\Models\Sekolah;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_soal(): void
    {
        $soal = Soal::factory()->create();
        $this->assertDatabaseHas('soal', ['id' => $soal->id]);
    }

    public function test_fillable_attributes(): void
    {
        $soal = new Soal();
        $expected = [
            'kategori_id', 'sekolah_id', 'created_by',
            'tipe_soal', 'pertanyaan', 'gambar_soal', 'posisi_gambar',
            'tingkat_kesulitan', 'bobot', 'pembahasan', 'sumber',
            'tahun_soal', 'is_active', 'is_verified', 'tags',
        ];
        $this->assertEquals($expected, $soal->getFillable());
    }

    public function test_boolean_casts(): void
    {
        $soal = Soal::factory()->create(['is_active' => 1, 'is_verified' => 0]);
        $this->assertIsBool($soal->is_active);
        $this->assertIsBool($soal->is_verified);
        $this->assertTrue($soal->is_active);
        $this->assertFalse($soal->is_verified);
    }

    public function test_tags_cast_to_array(): void
    {
        $soal = Soal::factory()->create(['tags' => ['math', 'algebra']]);
        $this->assertIsArray($soal->tags);
        $this->assertContains('math', $soal->tags);
    }

    public function test_kategori_relationship(): void
    {
        $kategori = KategoriSoal::factory()->create();
        $soal = Soal::factory()->create(['kategori_id' => $kategori->id]);

        $this->assertInstanceOf(KategoriSoal::class, $soal->kategori);
        $this->assertEquals($kategori->id, $soal->kategori->id);
    }

    public function test_sekolah_relationship(): void
    {
        $sekolah = Sekolah::factory()->create();
        $soal = Soal::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->assertInstanceOf(Sekolah::class, $soal->sekolah);
    }

    public function test_pembuat_relationship(): void
    {
        $user = User::factory()->create();
        $soal = Soal::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $soal->pembuat);
        $this->assertEquals($user->id, $soal->pembuat->id);
    }

    public function test_opsi_jawaban_relationship(): void
    {
        $soal = Soal::factory()->create();
        OpsiJawaban::factory()->count(4)->create(['soal_id' => $soal->id]);

        $this->assertCount(4, $soal->opsiJawaban);
        $this->assertInstanceOf(OpsiJawaban::class, $soal->opsiJawaban->first());
    }

    public function test_pasangan_relationship(): void
    {
        $soal = Soal::factory()->menjodohkan()->create();
        PasanganSoal::factory()->count(3)->create(['soal_id' => $soal->id]);

        $this->assertCount(3, $soal->pasangan);
        $this->assertInstanceOf(PasanganSoal::class, $soal->pasangan->first());
    }

    public function test_paket_soal_relationship(): void
    {
        $soal = Soal::factory()->create();
        PaketSoal::factory()->create(['soal_id' => $soal->id]);

        $this->assertCount(1, $soal->paketSoal);
    }

    public function test_get_all_image_urls_empty(): void
    {
        $soal = Soal::factory()->create(['gambar_soal' => null]);
        $this->assertEmpty($soal->getAllImageUrls());
    }

    public function test_get_all_image_urls_with_gambar(): void
    {
        $soal = Soal::factory()->create(['gambar_soal' => 'soal/gambar1.png']);
        $urls = $soal->getAllImageUrls();

        $this->assertNotEmpty($urls);
        $this->assertStringContainsString('soal/gambar1.png', $urls[0]);
    }

    public function test_soft_deletes(): void
    {
        $soal = Soal::factory()->create();
        $soal->delete();

        $this->assertSoftDeleted('soal', ['id' => $soal->id]);
    }
}
