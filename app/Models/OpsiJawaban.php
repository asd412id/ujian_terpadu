<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsiJawaban extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'opsi_jawaban';

    protected $fillable = [
        'soal_id', 'label', 'teks', 'gambar', 'is_benar', 'urutan',
    ];

    protected $casts = [
        'is_benar' => 'boolean',
    ];

    public function soal()
    {
        return $this->belongsTo(Soal::class);
    }
}
