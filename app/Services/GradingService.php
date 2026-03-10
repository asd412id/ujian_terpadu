<?php

namespace App\Services;

use App\Models\JawabanPeserta;
use App\Models\PaketUjian;
use App\Models\Sekolah;
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
        $query = JawabanPeserta::with(['soal.kategori', 'sesiPeserta.peserta.sekolah', 'sesiPeserta.sesi.paket'])
            ->whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereHas('sesiPeserta', fn ($q) => $q->whereIn('status', ['submit', 'dinilai']))
            ->whereNull('skor_manual')
            ->where(function ($q) {
                $q->whereNotNull('jawaban_teks')
                  ->where('jawaban_teks', '!=', '');
            });

        if (!empty($filters['paket_id'])) {
            $query->whereHas('sesiPeserta.sesi', fn ($q) => $q->where('paket_id', $filters['paket_id']));
        }

        if (!empty($filters['sekolah_id'])) {
            $query->whereHas('sesiPeserta.peserta', fn ($q) => $q->where('sekolah_id', $filters['sekolah_id']));
        }

        $perPage = $filters['per_page'] ?? 15;
        $jawabans = $query->latest()->paginate($perPage);

        $totalBelumDinilai = JawabanPeserta::whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereHas('sesiPeserta', fn ($q) => $q->whereIn('status', ['submit', 'dinilai']))
            ->whereNull('skor_manual')
            ->where(function ($q) {
                $q->whereNotNull('jawaban_teks')
                  ->where('jawaban_teks', '!=', '');
            })
            ->count();

        $paketList = PaketUjian::orderBy('nama')->get();
        $sekolahList = Sekolah::where('is_active', true)->orderBy('nama')->get();

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
        // Validate score range
        if ($nilai < 0 || $nilai > 100) {
            throw ValidationException::withMessages([
                'skor_manual' => 'Nilai harus antara 0 dan 100.',
            ]);
        }

        $jawaban = JawabanPeserta::findOrFail($jawabanId);

        $jawaban->update([
            'skor_manual'     => $nilai,
            'catatan_penilai' => $catatan,
            'dinilai_oleh'    => $dinilaiOleh,
            'dinilai_at'      => now(),
        ]);

        // Recalculate total score for the sesi peserta using PenilaianService
        $sesiPeserta = $jawaban->sesiPeserta;
        if ($sesiPeserta) {
            $hasil = $this->penilaianService->hitungNilai($sesiPeserta);
            $sesiPeserta->update($hasil);
        }

        return $jawaban->fresh(['soal', 'sesiPeserta.peserta']);
    }

    /**
     * Get grading statistics.
     */
    public function getGradingStats(): array
    {
        $totalEssay = JawabanPeserta::whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereNotNull('jawaban_teks')
            ->count();

        $sudahDinilai = JawabanPeserta::whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereNotNull('skor_manual')
            ->count();

        $belumDinilai = JawabanPeserta::whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->whereNull('skor_manual')
            ->whereNotNull('jawaban_teks')
            ->count();

        return [
            'total_essay'    => $totalEssay,
            'sudah_dinilai'  => $sudahDinilai,
            'belum_dinilai'  => $belumDinilai,
            'progress_pct'   => $totalEssay > 0 ? round(($sudahDinilai / $totalEssay) * 100, 1) : 0,
        ];
    }
}
