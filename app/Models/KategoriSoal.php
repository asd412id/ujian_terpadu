<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriSoal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kategori_soal';

    protected $fillable = [
        'nama', 'kode', 'jenjang', 'kelompok',
        'kurikulum', 'urutan', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function soal()
    {
        return $this->hasMany(Soal::class, 'kategori_id');
    }
}
