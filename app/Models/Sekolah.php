<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sekolah extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'sekolah';

    protected $fillable = [
        'dinas_id', 'nama', 'npsn', 'jenjang',
        'alamat', 'kota', 'telepon', 'email',
        'kepala_sekolah', 'logo', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Soft-delete: cascade deactivate peserta & clean up operator users
        static::deleting(function (Sekolah $sekolah) {
            if ($sekolah->isForceDeleting()) {
                // Hard delete: cascade everything
                $sekolah->peserta()->withTrashed()->forceDelete();
                $sekolah->users()->where('role', User::ROLE_ADMIN_SEKOLAH)->forceDelete();
            } else {
                // Soft delete: soft-delete peserta, hard-delete operator users
                $sekolah->peserta()->delete();
                $sekolah->users()->where('role', User::ROLE_ADMIN_SEKOLAH)->delete();
            }
        });

        // Capture deleted_at before restore nulls it (for cascade filter)
        static::restoring(function (Sekolah $sekolah) {
            $sekolah->_restoringDeletedAt = $sekolah->deleted_at;
        });

        // Restore peserta that were soft-deleted at/after the sekolah was deleted
        static::restored(function (Sekolah $sekolah) {
            $deletedAt = $sekolah->_restoringDeletedAt ?? now()->subMinute();
            unset($sekolah->_restoringDeletedAt);

            $sekolah->peserta()->onlyTrashed()
                ->where('deleted_at', '>=', $deletedAt)
                ->restore();
        });
    }

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
