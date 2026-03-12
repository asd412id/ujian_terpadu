<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaketUjian extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'paket_ujian';

    protected $fillable = [
        'sekolah_id', 'created_by', 'nama', 'kode',
        'jenis_ujian', 'jenjang', 'deskripsi', 'durasi_menit',
        'jumlah_soal', 'acak_soal', 'acak_opsi',
        'tampilkan_hasil', 'boleh_kembali', 'max_peserta',
        'tanggal_mulai', 'tanggal_selesai', 'status',
    ];

    protected $casts = [
        'acak_soal'       => 'boolean',
        'acak_opsi'       => 'boolean',
        'tampilkan_hasil' => 'boolean',
        'boleh_kembali'   => 'boolean',
        'tanggal_mulai'   => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    protected static function booted(): void
    {
        // On force-delete: explicitly clean up all children before DB cascade
        // (safety net — DB cascade should handle it, but belt + suspenders)
        static::forceDeleting(function (PaketUjian $paket) {
            $sesiIds = $paket->sesi()->pluck('id');
            if ($sesiIds->isNotEmpty()) {
                $spIds = SesiPeserta::whereIn('sesi_id', $sesiIds)->pluck('id');
                if ($spIds->isNotEmpty()) {
                    LogAktivitasUjian::whereIn('sesi_peserta_id', $spIds)->delete();
                    JawabanPeserta::whereIn('sesi_peserta_id', $spIds)->delete();
                    SesiPeserta::whereIn('id', $spIds)->delete();
                }
                SesiUjian::whereIn('id', $sesiIds)->delete();
            }
            PaketSoal::where('paket_id', $paket->id)->delete();
        });
    }

    public function sekolah()
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paketSoal()
    {
        return $this->hasMany(PaketSoal::class, 'paket_id')->orderBy('nomor_urut');
    }

    public function soal()
    {
        return $this->belongsToMany(Soal::class, 'paket_soal', 'paket_id', 'soal_id')
                    ->withPivot('nomor_urut', 'bobot_override')
                    ->orderByPivot('nomor_urut');
    }

    public function sesi()
    {
        return $this->hasMany(SesiUjian::class, 'paket_id');
    }

    public function getJenisUjianLabelAttribute(): ?string
    {
        if (!$this->jenis_ujian) {
            return null;
        }

        $labels = [
            'TKA_SEKOLAH'  => 'TKA Sekolah',
            'SIMULASI_UTBK' => 'Simulasi UTBK',
            'TRYOUT'        => 'Try Out',
            'ULANGAN'       => 'Ulangan',
            'PAS'           => 'PAS',
            'PAT'           => 'PAT',
            'LAINNYA'       => 'Lainnya',
        ];

        return $labels[$this->jenis_ujian] ?? str_replace('_', ' ', $this->jenis_ujian);
    }

    public function isAktif(): bool
    {
        return $this->status === 'aktif';
    }
}
