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

        $query = SesiPeserta::with(['peserta.sekolah', 'sesi.paket', 'jawaban'])
            ->whereIn('status', ['submit', 'dinilai']);

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        if (!empty($filters['paket_id'])) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'lulus') {
                $query->where('nilai_akhir', '>=', 70);
            } elseif ($filters['status'] === 'tidak_lulus') {
                $query->where('nilai_akhir', '<', 70);
            }
        }

        $perPage = $filters['per_page'] ?? 30;
        $data = $query->latest('updated_at')->paginate($perPage);

        $rekap = $this->buildRekap($filters);

        return compact('sekolahList', 'paketList', 'data', 'rekap');
    }

    /**
     * Build rekap statistics based on current filters.
     */
    protected function buildRekap(array $filters = []): array
    {
        $query = SesiPeserta::whereIn('status', ['submit', 'dinilai']);

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        if (!empty($filters['paket_id'])) {
            $query->whereHas('sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
        }

        $results = $query->get();

        return [
            'total_peserta' => $results->count(),
            'sudah_ujian'   => $results->count(),
            'lulus'         => $results->where('nilai_akhir', '>=', 70)->count(),
            'tidak_lulus'   => $results->where('nilai_akhir', '<', 70)->count(),
            'rata_rata'     => $results->count() > 0 ? round($results->avg('nilai_akhir'), 1) : 0,
        ];
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
     * Get laporan statistics for a specific paket.
     */
    public function getStatistik(?string $paketId = null): array
    {
        if ($paketId) {
            return $this->repository->getStatistik($paketId);
        }

        $results = SesiPeserta::whereIn('status', ['submit', 'dinilai'])->get();

        return [
            'total_peserta' => $results->count(),
            'rata_rata'     => $results->count() > 0 ? round($results->avg('nilai_akhir'), 1) : 0,
            'nilai_max'     => $results->max('nilai_akhir') ?? 0,
            'nilai_min'     => $results->min('nilai_akhir') ?? 0,
            'lulus'         => $results->where('nilai_akhir', '>=', 70)->count(),
            'tidak_lulus'   => $results->where('nilai_akhir', '<', 70)->count(),
        ];
    }

    /**
     * Get rekap nilai with filters.
     */
    public function getRekapNilai(array $filters = []): mixed
    {
        return $this->repository->getRekapNilai(
            $filters['sekolah_id'] ?? null,
            $filters['paket_id'] ?? null
        );
    }

    /**
     * Export hasil ujian data for Excel generation.
     */
    public function exportHasil(array $filters = []): array
    {
        $query = SesiPeserta::with(['peserta.sekolah', 'sesi.paket', 'jawaban'])
            ->whereIn('status', ['submit', 'dinilai']);

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
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
