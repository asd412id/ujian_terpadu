<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SesiPeserta extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sesi_peserta';

    protected $fillable = [
        'sesi_id', 'peserta_id', 'token_ujian', 'urutan_soal', 'urutan_opsi',
        'status', 'ip_address', 'browser_info', 'device_type',
        'mulai_at', 'submit_at', 'durasi_aktual_detik',
        'soal_terjawab', 'soal_ditandai', 'nilai_akhir',
        'nilai_benar', 'jumlah_benar', 'jumlah_salah', 'jumlah_kosong',
    ];

    protected $casts = [
        'urutan_soal'          => 'array',
        'urutan_opsi'          => 'array',
        'mulai_at'             => 'datetime',
        'submit_at'            => 'datetime',
        'nilai_akhir'          => 'decimal:2',
        'nilai_benar'          => 'decimal:2',
    ];

    public function sesi()
    {
        return $this->belongsTo(SesiUjian::class, 'sesi_id');
    }

    public function peserta()
    {
        return $this->belongsTo(Peserta::class);
    }

    public function jawaban()
    {
        return $this->hasMany(JawabanPeserta::class);
    }

    public function logAktivitas()
    {
        return $this->hasMany(LogAktivitasUjian::class);
    }

    // Waktu tersisa dalam detik (server-authoritative)
    // Capped by sesi_ujian.waktu_selesai — jika sesi berakhir lebih dulu, itu yang dipakai
    public function getSisaWaktuDetikAttribute(): int
    {
        $durasi = $this->sesi->paket->durasi_menit * 60;
        if (! $this->mulai_at) return $durasi;

        $now = now();
        $elapsed = (int) $this->mulai_at->diffInSeconds($now, false);
        $sisaByDurasi = max(0, $durasi - $elapsed);

        // Cap by sesi waktu_selesai if set
        $waktuSelesai = $this->sesi->waktu_selesai;
        if ($waktuSelesai) {
            $sisaBySesi = max(0, (int) $now->diffInSeconds($waktuSelesai, false));
            return min($sisaByDurasi, $sisaBySesi);
        }

        return $sisaByDurasi;
    }
}
