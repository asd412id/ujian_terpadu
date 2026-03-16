<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NarasiSoal extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'narasi_soal';

    protected $fillable = [
        'kategori_id', 'sekolah_id', 'created_by',
        'judul', 'konten', 'gambar', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function kategori()
    {
        return $this->belongsTo(KategoriSoal::class, 'kategori_id');
    }

    public function sekolah()
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function soalList()
    {
        return $this->hasMany(Soal::class, 'narasi_id')->orderBy('urutan_dalam_narasi');
    }

    public function getJumlahSoalAttribute(): int
    {
        return $this->soalList()->count();
    }
}
