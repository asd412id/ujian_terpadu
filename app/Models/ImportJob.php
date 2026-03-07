<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'import_jobs';

    protected $fillable = [
        'created_by', 'sekolah_id', 'tipe', 'filename', 'filepath',
        'status', 'total_rows', 'processed_rows', 'success_rows',
        'error_rows', 'errors', 'catatan', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'errors'       => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sekolah()
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_rows === 0) return 0;
        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }
}
