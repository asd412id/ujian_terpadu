<?php

namespace App\Models;

use App\Support\HtmlDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'judul', 'konten', 'konten_plain', 'gambar', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function normalizeSearchText(?string $text): string
    {
        return HtmlDisplay::plainText($text);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = static::normalizeSearchText($search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $narasiQuery) use ($search) {
            $narasiQuery->where('judul', 'like', "%{$search}%")
                ->orWhere('konten_plain', 'like', "%{$search}%");
        });
    }

    protected function konten(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => [
                'konten' => $value,
                'konten_plain' => $value === null ? null : static::normalizeSearchText($value),
            ],
        );
    }

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
