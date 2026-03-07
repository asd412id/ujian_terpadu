<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Soal extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'soal';

    protected $fillable = [
        'kategori_id', 'sekolah_id', 'created_by',
        'tipe_soal', 'pertanyaan', 'gambar_soal', 'posisi_gambar',
        'tingkat_kesulitan', 'bobot', 'pembahasan', 'sumber',
        'tahun_soal', 'is_active', 'is_verified', 'tags',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'is_verified'  => 'boolean',
        'bobot'        => 'decimal:2',
        'tags'         => 'array',
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

    public function opsiJawaban()
    {
        return $this->hasMany(OpsiJawaban::class)->orderBy('urutan');
    }

    public function pasangan()
    {
        return $this->hasMany(PasanganSoal::class)->orderBy('urutan');
    }

    public function paketSoal()
    {
        return $this->hasMany(PaketSoal::class);
    }

    // Semua URL gambar untuk pre-cache offline
    public function getAllImageUrls(): array
    {
        $urls = [];
        if ($this->gambar_soal) {
            $urls[] = asset('storage/' . $this->gambar_soal);
        }
        foreach ($this->opsiJawaban as $opsi) {
            if ($opsi->gambar) {
                $urls[] = asset('storage/' . $opsi->gambar);
            }
        }
        foreach ($this->pasangan as $pas) {
            if ($pas->kiri_gambar)   $urls[] = asset('storage/' . $pas->kiri_gambar);
            if ($pas->kanan_gambar)  $urls[] = asset('storage/' . $pas->kanan_gambar);
        }
        return $urls;
    }
}
