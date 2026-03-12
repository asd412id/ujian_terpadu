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

        $totalBelumDinilai = $jawabans->total();

        $paketList = PaketUjian::orderBy('nama')->get(['id', 'nama']);
        $sekolahList = Sekolah::where('is_active', true)->orderBy('nama')->get(['id', 'nama']);

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
     * Get grading statistics (single aggregate query instead of 3).
     */
    public function getGradingStats(): array
    {
        $row = JawabanPeserta::whereHas('soal', fn ($q) => $q->where('tipe_soal', 'essay'))
            ->selectRaw('
                COUNT(CASE WHEN jawaban_teks IS NOT NULL THEN 1 END) as total_essay,
                COUNT(CASE WHEN skor_manual IS NOT NULL THEN 1 END) as sudah_dinilai,
                COUNT(CASE WHEN skor_manual IS NULL AND jawaban_teks IS NOT NULL AND jawaban_teks != \'\' THEN 1 END) as belum_dinilai
            ')
            ->first();

        $totalEssay = (int) ($row->total_essay ?? 0);
        $sudahDinilai = (int) ($row->sudah_dinilai ?? 0);

        return [
            'total_essay'    => $totalEssay,
            'sudah_dinilai'  => $sudahDinilai,
            'belum_dinilai'  => (int) ($row->belum_dinilai ?? 0),
            'progress_pct'   => $totalEssay > 0 ? round(($sudahDinilai / $totalEssay) * 100, 1) : 0,
        ];
    }
}
