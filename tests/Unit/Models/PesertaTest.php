<?php

namespace Tests\Unit\Models;

use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PesertaTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_peserta(): void
    {
        $peserta = Peserta::factory()->create();

        $this->assertDatabaseHas('peserta', ['id' => $peserta->id]);
    }

    public function test_fillable_attributes(): void
    {
        $peserta = new Peserta();
        $expected = [
            'sekolah_id', 'nisn', 'nis', 'nama', 'kelas', 'jurusan',
            'jenis_kelamin', 'tanggal_lahir', 'tempat_lahir', 'foto',
            'username_ujian', 'password_ujian', 'password_plain', 'is_active',
        ];
        $this->assertEquals($expected, $peserta->getFillable());
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $peserta = Peserta::factory()->create(['is_active' => 1]);
        $this->assertIsBool($peserta->is_active);
    }

    public function test_tanggal_lahir_cast_to_date(): void
    {
        $peserta = Peserta::factory()->create(['tanggal_lahir' => '2005-05-15']);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $peserta->tanggal_lahir);
    }

    public function test_password_is_hidden(): void
    {
        $peserta = Peserta::factory()->create();
        $array = $peserta->toArray();
        $this->assertArrayNotHasKey('password_ujian', $array);
        $this->assertArrayNotHasKey('password_plain', $array);
    }

    public function test_sekolah_relationship(): void
    {
        $sekolah = Sekolah::factory()->create();
        $peserta = Peserta::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->assertInstanceOf(Sekolah::class, $peserta->sekolah);
        $this->assertEquals($sekolah->id, $peserta->sekolah->id);
    }

    public function test_sesi_peserta_relationship(): void
    {
        $peserta = Peserta::factory()->create();
        $sesiPeserta = SesiPeserta::factory()->create(['peserta_id' => $peserta->id]);

        $this->assertCount(1, $peserta->sesiPeserta);
        $this->assertInstanceOf(SesiPeserta::class, $peserta->sesiPeserta->first());
    }

    public function test_generate_username_from_nis(): void
    {
        $username = Peserta::generateUsername('123456', null, null);
        $this->assertEquals('123456', $username);
    }

    public function test_generate_username_from_nisn_when_no_nis(): void
    {
        $username = Peserta::generateUsername(null, '9876543210', null);
        $this->assertEquals('9876543210', $username);
    }

    public function test_generate_username_auto_when_no_nis_and_nisn(): void
    {
        $username = Peserta::generateUsername(null, null, null);
        $this->assertEquals(8, strlen($username));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $username);
    }

    public function test_generate_username_uniqueness(): void
    {
        $sekolah = Sekolah::factory()->create();
        Peserta::factory()->create([
            'sekolah_id' => $sekolah->id,
            'username_ujian' => '123456',
        ]);

        $username = Peserta::generateUsername('123456', null, $sekolah->id);
        $this->assertEquals('1234561', $username);
    }

    public function test_generate_password_default_length(): void
    {
        $password = Peserta::generatePassword();
        $this->assertEquals(8, strlen($password));
    }

    public function test_generate_password_custom_length(): void
    {
        $password = Peserta::generatePassword(12);
        $this->assertEquals(12, strlen($password));
    }

    public function test_generate_password_only_readable_chars(): void
    {
        $password = Peserta::generatePassword(100);
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]+$/', $password);
    }

    public function test_soft_deletes(): void
    {
        $peserta = Peserta::factory()->create();
        $peserta->delete();

        $this->assertSoftDeleted('peserta', ['id' => $peserta->id]);
        $this->assertNotNull(Peserta::withTrashed()->find($peserta->id));
    }
}
