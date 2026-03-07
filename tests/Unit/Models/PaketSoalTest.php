<?php

namespace Tests\Unit\Models;

use App\Models\PaketSoal;
use App\Models\PaketUjian;
use App\Models\Soal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaketSoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_paket_soal(): void
    {
        $paketSoal = PaketSoal::factory()->create();
        $this->assertDatabaseHas('paket_soal', ['id' => $paketSoal->id]);
    }

    public function test_fillable_attributes(): void
    {
        $paketSoal = new PaketSoal();
        $this->assertEquals(
            ['paket_id', 'soal_id', 'nomor_urut', 'bobot_override'],
            $paketSoal->getFillable()
        );
    }

    public function test_bobot_override_cast(): void
    {
        $paketSoal = PaketSoal::factory()->create(['bobot_override' => 2.50]);
        $this->assertEquals('2.50', $paketSoal->bobot_override);
    }

    public function test_paket_relationship(): void
    {
        $paket = PaketUjian::factory()->create();
        $paketSoal = PaketSoal::factory()->create(['paket_id' => $paket->id]);

        $this->assertInstanceOf(PaketUjian::class, $paketSoal->paket);
        $this->assertEquals($paket->id, $paketSoal->paket->id);
    }

    public function test_soal_relationship(): void
    {
        $soal = Soal::factory()->create();
        $paketSoal = PaketSoal::factory()->create(['soal_id' => $soal->id]);

        $this->assertInstanceOf(Soal::class, $paketSoal->soal);
        $this->assertEquals($soal->id, $paketSoal->soal->id);
    }
}
