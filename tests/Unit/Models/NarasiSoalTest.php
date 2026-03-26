<?php

namespace Tests\Unit\Models;

use App\Models\KategoriSoal;
use App\Models\NarasiSoal;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class NarasiSoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_konten_mutator_skips_konten_plain_when_column_missing(): void
    {
        Schema::table('narasi_soal', function (Blueprint $table) {
            $table->dropColumn('konten_plain');
        });
        NarasiSoal::flushKontenPlainColumnSupportCache();

        $narasi = NarasiSoal::create([
            'kategori_id' => KategoriSoal::factory()->create()->id,
            'created_by' => User::factory()->create()->id,
            'judul' => 'Narasi tanpa kolom plain',
            'konten' => '<p>Isi <strong>narasi</strong> tetap tersimpan.</p>',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('narasi_soal', [
            'id' => $narasi->id,
            'judul' => 'Narasi tanpa kolom plain',
        ]);
    }

    public function test_search_scope_falls_back_to_raw_konten_when_plain_column_missing(): void
    {
        Schema::table('narasi_soal', function (Blueprint $table) {
            $table->dropColumn('konten_plain');
        });
        NarasiSoal::flushKontenPlainColumnSupportCache();

        $kategori = KategoriSoal::factory()->create();
        $user = User::factory()->create();

        $matchingNarasi = NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi lebah',
            'konten' => '<p>Lebah madu yang bijaksana membantu semut kecil.</p>',
            'is_active' => true,
        ]);

        NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi lain',
            'konten' => '<p>Cerita lain tanpa kata kunci yang dicari.</p>',
            'is_active' => true,
        ]);

        $results = NarasiSoal::query()
            ->search('lebah semut kecil')
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains(fn (NarasiSoal $narasi) => $narasi->id === $matchingNarasi->id));
    }

    public function test_search_scope_falls_back_to_raw_konten_when_plain_column_value_is_missing(): void
    {
        NarasiSoal::flushKontenPlainColumnSupportCache();

        $kategori = KategoriSoal::factory()->create();
        $user = User::factory()->create();
        $now = now();
        $matchingId = (string) Str::uuid();

        DB::table('narasi_soal')->insert([
            'id' => $matchingId,
            'kategori_id' => $kategori->id,
            'sekolah_id' => null,
            'created_by' => $user->id,
            'judul' => 'Narasi migrasi parsial',
            'konten' => '<p>Lebah madu menolong semut kecil di hutan.</p>',
            'konten_plain' => null,
            'gambar' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        NarasiSoal::create([
            'kategori_id' => $kategori->id,
            'created_by' => $user->id,
            'judul' => 'Narasi lain',
            'konten' => '<p>Cerita lain tanpa kecocokan.</p>',
            'is_active' => true,
        ]);

        $results = NarasiSoal::query()
            ->search('lebah semut kecil')
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains(fn (NarasiSoal $narasi) => $narasi->id === $matchingId));
    }
}
