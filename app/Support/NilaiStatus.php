<?php

namespace App\Support;

class NilaiStatus
{
    public static function label(?float $nilai): string
    {
        $nilai = (float) ($nilai ?? 0);

        return match (true) {
            $nilai >= 86 => 'Sangat Baik',
            $nilai >= 71 => 'Baik',
            $nilai >= 56 => 'Cukup',
            $nilai >= 41 => 'Kurang',
            default => 'Sangat Kurang',
        };
    }

    public static function badgeClass(?float $nilai): string
    {
        return match (self::label($nilai)) {
            'Sangat Baik' => 'bg-green-100 text-green-700',
            'Baik' => 'bg-blue-100 text-blue-700',
            'Cukup' => 'bg-amber-100 text-amber-700',
            'Kurang' => 'bg-orange-100 text-orange-700',
            default => 'bg-red-100 text-red-600',
        };
    }

    public static function textClass(?float $nilai): string
    {
        return match (self::label($nilai)) {
            'Sangat Baik' => 'text-green-600',
            'Baik' => 'text-blue-600',
            'Cukup' => 'text-amber-600',
            'Kurang' => 'text-orange-600',
            default => 'text-red-600',
        };
    }
}
