<?php

namespace App\Services;

use App\Models\PaketUjian;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use App\Repositories\LaporanRepository;

class LaporanService
{
    public function __construct(
        protected LaporanRepository $repository
    ) {}

    /**
     * Get hasil ujian with filters and pagination.
     */
    public function getHasilUjian(array $filters = []): array
    {
        $sekolahList = Sekolah::where('is_active', true)->orderBy('nama')->get();
        $paketList = PaketUjian::orderBy('nama')->get();

        $data = [];
        if (!empty($filters['sekolah_id'])) {
            $query = SesiPeserta::with(['peserta', 'sesi.paket', 'jawaban'])
                ->whereHas('sesi.paket', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']))
                ->whereIn('status', ['submit', 'dinilai']);

            if (!empty($filters['paket_id'])) {
                $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
            }

            $perPage = $filters['per_page'] ?? 30;
            $data = $query->paginate($perPage);
        }

        return compact('sekolahList', 'paketList', 'data');
    }

    /**
     * Get hasil ujian by sekolah.
     */
    public function getHasilBySekolah(string $sekolahId): mixed
    {
        return $this->repository->getHasilBySekolah($sekolahId);
    }

    /**
     * Get hasil ujian by paket.
     */
    public function getHasilByPaket(string $paketId): mixed
    {
        return $this->repository->getHasilByPaket($paketId);
    }

    /**
     * Get laporan statistics.
     */
    public function getStatistik(): array
    {
        return $this->repository->getStatistik();
    }

    /**
     * Get rekap nilai with filters.
     */
    public function getRekapNilai(array $filters = []): mixed
    {
        return $this->repository->getRekapNilai($filters);
    }

    /**
     * Export hasil ujian data for Excel generation.
     *
     * @return array  Data ready for Excel export
     */
    public function exportHasil(array $filters = []): array
    {
        $query = SesiPeserta::with(['peserta.sekolah', 'sesi.paket', 'jawaban'])
            ->whereIn('status', ['submit', 'dinilai']);

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('sesi.paket', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        if (!empty($filters['paket_id'])) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
        }

        return $query->get()->map(fn ($sp) => [
            'nama_peserta'   => $sp->peserta->nama ?? '-',
            'nis'            => $sp->peserta->nis ?? '-',
            'sekolah'        => $sp->peserta->sekolah->nama ?? '-',
            'paket'          => $sp->sesi->paket->nama ?? '-',
            'nilai_akhir'    => $sp->nilai_akhir ?? 0,
            'jumlah_benar'   => $sp->jumlah_benar ?? 0,
            'jumlah_salah'   => $sp->jumlah_salah ?? 0,
            'jumlah_kosong'  => $sp->jumlah_kosong ?? 0,
            'durasi'         => $sp->durasi_aktual_detik ? round($sp->durasi_aktual_detik / 60, 1) . ' menit' : '-',
            'submit_at'      => $sp->submit_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();
    }

    /**
     * Get detail nilai for a specific peserta.
     */
    public function getDetailNilaiPeserta(string $pesertaId): mixed
    {
        return $this->repository->getDetailNilaiPeserta($pesertaId);
    }
}
