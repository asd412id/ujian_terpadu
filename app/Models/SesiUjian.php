<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SesiUjian extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sesi_ujian';

    protected $fillable = [
        'paket_id', 'nama_sesi', 'ruangan', 'pengawas_id',
        'waktu_mulai', 'waktu_selesai', 'status', 'kapasitas',
    ];

    protected $casts = [
        'waktu_mulai'   => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function paket()
    {
        return $this->belongsTo(PaketUjian::class, 'paket_id');
    }

    public function pengawas()
    {
        return $this->belongsTo(User::class, 'pengawas_id');
    }

    public function sesiPeserta()
    {
        return $this->hasMany(SesiPeserta::class, 'sesi_id');
    }

    public function peserta()
    {
        return $this->belongsToMany(Peserta::class, 'sesi_peserta', 'sesi_id', 'peserta_id')
                    ->withPivot('status', 'nilai_akhir', 'submit_at');
    }

    public function getJumlahAktifAttribute(): int
    {
        return $this->sesiPeserta()
                    ->whereIn('status', ['login', 'mengerjakan'])
                    ->count();
    }

    public function getJumlahSubmitAttribute(): int
    {
        return $this->sesiPeserta()
                    ->where('status', 'submit')
                    ->count();
    }
}
