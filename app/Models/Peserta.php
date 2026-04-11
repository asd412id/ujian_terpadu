<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Peserta extends Authenticatable
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'peserta';

    protected $fillable = [
        'sekolah_id', 'nisn', 'nis', 'nama', 'kelas', 'jurusan',
        'jenis_kelamin', 'tanggal_lahir', 'tempat_lahir', 'foto',
        'username_ujian', 'password_ujian', 'password_plain', 'is_active',
        'device_token',
    ];

    protected $hidden = ['password_ujian', 'password_plain', 'device_token'];

    protected $casts = [
        'is_active'     => 'boolean',
        'tanggal_lahir' => 'date',
    ];

    protected static function booted(): void
    {
        // When peserta is soft-deleted, clean up orphaned sesi_peserta records
        // (DB cascadeOnDelete won't fire on soft-delete since row stays in table)
        static::deleting(function (Peserta $peserta) {
            // Only clean up sesi_peserta that are NOT in active exam status
            $cleanupIds = $peserta->sesiPeserta()
                ->whereNotIn('status', ['login', 'mengerjakan'])
                ->pluck('id');

            if ($cleanupIds->isEmpty()) return;

            // Bulk delete related records using subquery (no N+1)
            \App\Models\JawabanPeserta::whereIn('sesi_peserta_id', $cleanupIds)->delete();
            \App\Models\LogAktivitasUjian::whereIn('sesi_peserta_id', $cleanupIds)->delete();
            \App\Models\SesiPeserta::whereIn('id', $cleanupIds)->delete();
        });
    }

    public function sekolah()
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function sesiPeserta()
    {
        return $this->hasMany(SesiPeserta::class);
    }

    public function getAuthPassword(): string
    {
        return $this->password_ujian;
    }

    // Generate username dari NISN (prioritas) > NIS > auto
    public static function generateUsername(string $nis = null, string $nisn = null, ?string $sekolahId = null): string
    {
        if ($nisn && $nisn !== '') {
            $base = preg_replace('/\s+/', '', $nisn);
        } elseif ($nis && $nis !== '') {
            $base = preg_replace('/\s+/', '', $nis);
        } else {
            $base = strtoupper(Str::random(8));
        }

        // Pastikan unik — tambah suffix jika perlu (max 100 attempts)
        $username = $base;
        $counter  = 1;
        $maxAttempts = 100;
        while (static::where('username_ujian', $username)->exists()) {
            if ($counter >= $maxAttempts) {
                // Fallback to random suffix to guarantee termination
                $username = $base . strtoupper(Str::random(6));
                break;
            }
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    // Generate password acak yang mudah dibaca
    public static function generatePassword(int $length = 8): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // hindari karakter mirip (0,O,1,I)
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}
