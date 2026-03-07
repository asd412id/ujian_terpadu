<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JawabanPeserta extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'jawaban_peserta';

    protected $fillable = [
        'sesi_peserta_id', 'soal_id',
        'jawaban_pg', 'jawaban_teks', 'jawaban_pasangan', 'file_essay',
        'is_ditandai', 'is_terjawab',
        'skor_auto', 'skor_manual', 'dinilai_oleh', 'dinilai_at',
        'catatan_penilai', 'waktu_jawab', 'durasi_jawab_detik',
        'idempotency_key',
    ];

    protected $casts = [
        'jawaban_pg'      => 'array',
        'jawaban_pasangan'=> 'array',
        'is_ditandai'     => 'boolean',
        'is_terjawab'     => 'boolean',
        'skor_auto'       => 'decimal:2',
        'skor_manual'     => 'decimal:2',
        'waktu_jawab'     => 'datetime',
        'dinilai_at'      => 'datetime',
    ];

    public function sesiPeserta()
    {
        return $this->belongsTo(SesiPeserta::class);
    }

    public function soal()
    {
        return $this->belongsTo(Soal::class);
    }

    public function penilai()
    {
        return $this->belongsTo(User::class, 'dinilai_oleh');
    }
}
