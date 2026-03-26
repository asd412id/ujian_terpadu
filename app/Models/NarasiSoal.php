<?php

namespace App\Models;

use App\Support\HtmlDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class NarasiSoal extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected static array $kontenPlainColumnSupportCache = [];

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

        $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $query->where(function (Builder $narasiQuery) use ($search, $terms) {
            $narasiQuery->where('judul', 'like', "%{$search}%");

            if (static::supportsKontenPlainColumn()) {
                $narasiQuery->orWhere(function (Builder $kontenQuery) use ($search, $terms) {
                    $kontenQuery->where('konten_plain', 'like', "%{$search}%")
                        ->orWhere(function (Builder $fallbackQuery) use ($terms, $search) {
                            $fallbackQuery->where(function (Builder $missingPlainQuery) {
                                $missingPlainQuery->whereNull('konten_plain')
                                    ->orWhere('konten_plain', '');
                            });

                            foreach ($terms !== [] ? $terms : [$search] as $term) {
                                $fallbackQuery->where('konten', 'like', "%{$term}%");
                            }
                        });
                });

                return;
            }

            $narasiQuery->orWhere(function (Builder $fallbackQuery) use ($terms, $search) {
                foreach ($terms !== [] ? $terms : [$search] as $term) {
                    $fallbackQuery->where('konten', 'like', "%{$term}%");
                }
            });
        });
    }

    protected function konten(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): array {
                $attributes = ['konten' => $value];

                if (static::supportsKontenPlainColumn()) {
                    $attributes['konten_plain'] = $value === null ? null : static::normalizeSearchText($value);
                }

                return $attributes;
            },
        );
    }

    public static function flushKontenPlainColumnSupportCache(): void
    {
        static::$kontenPlainColumnSupportCache = [];
    }

    protected static function supportsKontenPlainColumn(): bool
    {
        $model = new static;
        $connection = $model->getConnectionName();
        $table = $model->getTable();
        $cacheKey = ($connection ?? 'default') . ':' . $table;

        if (!array_key_exists($cacheKey, static::$kontenPlainColumnSupportCache)) {
            $schema = Schema::connection($connection);
            static::$kontenPlainColumnSupportCache[$cacheKey] = $schema->hasTable($table)
                && $schema->hasColumn($table, 'konten_plain');
        }

        return static::$kontenPlainColumnSupportCache[$cacheKey];
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
