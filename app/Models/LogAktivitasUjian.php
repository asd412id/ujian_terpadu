<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogAktivitasUjian extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'log_aktivitas_ujian';

    public $timestamps = false;

    protected $fillable = [
        'sesi_peserta_id', 'tipe_event', 'detail', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'detail'     => 'array',
        'created_at' => 'datetime',
    ];

    public function sesiPeserta()
    {
        return $this->belongsTo(SesiPeserta::class);
    }
}
