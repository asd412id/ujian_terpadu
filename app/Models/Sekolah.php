<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sekolah extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sekolah';

    protected $fillable = [
        'dinas_id', 'nama', 'npsn', 'jenjang',
        'alamat', 'kota', 'telepon', 'email',
        'kepala_sekolah', 'logo', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function dinas()
    {
        return $this->belongsTo(DinasPendidikan::class, 'dinas_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function peserta()
    {
        return $this->hasMany(Peserta::class);
    }

    public function paketUjian()
    {
        return $this->hasMany(PaketUjian::class);
    }

    public function soal()
    {
        return $this->hasMany(Soal::class);
    }
}
