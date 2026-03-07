<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasanganSoal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pasangan_soal';

    protected $fillable = [
        'soal_id', 'kiri_teks', 'kiri_gambar',
        'kanan_teks', 'kanan_gambar', 'urutan',
    ];

    public function soal()
    {
        return $this->belongsTo(Soal::class);
    }
}
