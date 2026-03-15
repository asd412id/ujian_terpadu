<?php

namespace App\Services;

use App\Repositories\GradingRepository;
use App\Services\PenilaianService;
use Illuminate\Validation\ValidationException;

class GradingService
{
    public function __construct(
        protected GradingRepository $repository,
        protected PenilaianService $penilaianService
    ) {}

    /**
     * Get pending essay grading with filters and pagination.
     */
    public function getPendingGrading(array $filters = []): array
    {
        $jawabans = $this->repository->getPendingGradingFiltered($filters);
        $totalBelumDinilai = $jawabans->total();
        $paketList = $this->repository->getPaketList();
        $sekolahList = $this->repository->getSekolahList();

        return compact('jawabans', 'totalBelumDinilai', 'paketList', 'sekolahList');
    }

    /**
     * Get essay jawaban for a specific sesi.
     */
    public function getEssayJawaban(string $sesiId): mixed
    {
        return $this->repository->getEssayBySesi($sesiId);
    }

    /**
     * Grade a single essay jawaban.
     *
     * @throws ValidationException
     */
    public function gradeJawaban(string $jawabanId, float $nilai, ?string $catatan = null, ?string $dinilaiOleh = null): mixed
    {
        $jawaban = $this->repository->findJawabanOrFail($jawabanId);
        $jawaban->load('soal');

        $bobot = $jawaban->soal->bobot ?? 1;
        if ($nilai < 0 || $nilai > $bobot) {
            throw ValidationException::withMessages([
                'skor_manual' => "Nilai harus antara 0 dan {$bobot}.",
            ]);
        }

        $this->repository->updateNilai($jawaban, [
            'skor_manual'     => $nilai,
            'catatan_penilai' => $catatan,
            'dinilai_oleh'    => $dinilaiOleh,
            'dinilai_at'      => now(),
        ]);

        $sesiPeserta = $jawaban->sesiPeserta;
        if ($sesiPeserta) {
            $hasil = $this->penilaianService->hitungNilai($sesiPeserta);
            $sesiPeserta->update($hasil);
        }

        return $jawaban->fresh(['soal', 'sesiPeserta.peserta']);
    }

    /**
     * Get grading statistics (single aggregate query instead of 3).
     */
    public function getGradingStats(): array
    {
        return $this->repository->getGradingStatsOptimized();
    }
}
