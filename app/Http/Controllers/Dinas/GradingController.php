<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\JawabanPeserta;
use App\Services\GradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GradingController extends Controller
{
    public function __construct(
        protected GradingService $gradingService
    ) {}

    public function index(Request $request)
    {
        $data = $this->gradingService->getPendingGrading($request->all());

        return view('dinas.grading.index', [
            'jawabans'          => $data['jawabans'],
            'totalBelumDinilai' => $data['totalBelumDinilai'],
            'paketList'         => $data['paketList'],
            'sekolahList'       => $data['sekolahList'],
        ]);
    }

    public function nilai(Request $request, JawabanPeserta $jawaban)
    {
        $request->validate([
            'skor_manual'     => 'required|numeric|min:0|max:100',
            'catatan_penilai' => 'nullable|string|max:500',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->gradingService->gradeJawaban(
            jawabanId: $jawaban->id,
            nilai: $request->skor_manual,
            catatan: $request->catatan_penilai,
            dinilaiOleh: $user->id,
        );

        return back()->with('success', 'Nilai essay berhasil disimpan.');
    }
}
