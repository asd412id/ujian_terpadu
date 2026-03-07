<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaketSoal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'paket_soal';

    protected $fillable = [
        'paket_id', 'soal_id', 'nomor_urut', 'bobot_override',
    ];

    protected $casts = [
        'bobot_override' => 'decimal:2',
    ];

    public function paket()
    {
        return $this->belongsTo(PaketUjian::class, 'paket_id');
    }

    public function soal()
    {
        return $this->belongsTo(Soal::class);
    }
}
