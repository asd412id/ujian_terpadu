<?php

namespace App\Services;

use App\Models\PaketUjian;
use App\Models\SesiUjian;

class SesiUjianService
{
    public function createSesi(PaketUjian $paket, array $data): SesiUjian
    {
        $data['paket_id'] = $paket->id;
        $data['status']   = $data['status'] ?? 'persiapan';

        return SesiUjian::create($data);
    }

    public function updateSesi(SesiUjian $sesi, array $data): SesiUjian
    {
        $sesi->update($data);
        return $sesi->fresh();
    }

    public function deleteSesi(SesiUjian $sesi): bool
    {
        if ($sesi->status === 'berlangsung') {
            throw new \RuntimeException('Tidak dapat menghapus sesi yang sedang berlangsung.');
        }

        if ($sesi->sesiPeserta()->whereIn('status', ['hadir', 'mengerjakan'])->exists()) {
            throw new \RuntimeException('Tidak dapat menghapus sesi yang sedang diikuti peserta.');
        }

        return (bool) $sesi->delete();
    }

    /**
     * Cancel all 'persiapan' sesi when paket is archived.
     * Returns count of sesi that were cancelled.
     */
    public function cancelPendingSesiByPaket(PaketUjian $paket): int
    {
        return $paket->sesi()
            ->where('status', 'persiapan')
            ->update(['status' => 'selesai']);
    }
}
