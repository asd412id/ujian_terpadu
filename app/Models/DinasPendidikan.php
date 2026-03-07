<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DinasPendidikan extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'dinas_pendidikan';

    protected $fillable = [
        'nama', 'kode_wilayah', 'kota', 'provinsi',
        'alamat', 'telepon', 'email', 'kepala_dinas',
        'logo', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sekolah()
    {
        return $this->hasMany(Sekolah::class, 'dinas_id');
    }

    public function users()
    {
        return $this->hasManyThrough(User::class, Sekolah::class, 'dinas_id', 'sekolah_id');
    }
}
