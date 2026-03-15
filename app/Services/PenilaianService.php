<?php

namespace App\Services;

use App\Models\SesiPeserta;
use App\Models\JawabanPeserta;
use App\Repositories\JawabanRepository;

class PenilaianService
{
    public function __construct(
        protected JawabanRepository $jawabanRepository
    ) {}

    public function hitungNilai(SesiPeserta $sesiPeserta): array
    {
        $sesiPeserta->loadMissing(['jawaban.soal.opsiJawaban', 'sesi.paket.paketSoal.soal']);
        $paket = $sesiPeserta->sesi->paket;

        $jumlahBenar  = 0;
        $jumlahSalah  = 0;
        $jumlahKosong = 0;
        $totalBobot   = 0;
        $nilaiBenar   = 0;

        // totalBobot dihitung dari SEMUA soal di paket, bukan hanya yang dijawab
        foreach ($paket->paketSoal as $ps) {
            $totalBobot += $ps->bobot_override ?? $ps->soal->bobot ?? 0;
        }

        $updates = [];

        foreach ($sesiPeserta->jawaban as $jawaban) {
            $soal  = $jawaban->soal;
            $bobot = $paket->paketSoal->where('soal_id', $soal->id)->first()?->bobot_override
                  ?? $soal->bobot;

            if (! $jawaban->is_terjawab) {
                continue;
            }

            $skor = $this->hitungSkorSatu($jawaban, $bobot);

            if ($jawaban->soal->tipe_soal === 'essay') {
                $updates[] = ['id' => $jawaban->id, 'skor_auto' => 0];
                if ($jawaban->skor_manual !== null) {
                    $nilaiBenar += $jawaban->skor_manual;
                    if ($jawaban->skor_manual > 0) {
                        $jumlahBenar++;
                    } else {
                        $jumlahSalah++;
                    }
                }
                continue;
            }

            $updates[] = ['id' => $jawaban->id, 'skor_auto' => $skor];

            if ($skor >= $bobot) {
                $jumlahBenar++;
                $nilaiBenar += $skor;
            } elseif ($skor > 0) {
                $jumlahSalah++;
                $nilaiBenar += $skor;
            } else {
                $jumlahSalah++;
            }
        }

        // Batch update skor_auto in chunks instead of N individual updates
        $this->jawabanRepository->batchUpdateSkorAuto($updates);

        $totalSoal    = $paket->paketSoal->count();
        $jumlahKosong = $totalSoal - $jumlahBenar - $jumlahSalah;
        if ($jumlahKosong < 0) $jumlahKosong = 0;
        $nilaiAkhir   = $totalBobot > 0 ? round(($nilaiBenar / $totalBobot) * 100, 2) : 0;

        return [
            'nilai_akhir'  => $nilaiAkhir,
            'nilai_benar'  => $nilaiBenar,
            'jumlah_benar' => $jumlahBenar,
            'jumlah_salah' => $jumlahSalah,
            'jumlah_kosong'=> $jumlahKosong,
            'status'       => 'submit',
        ];
    }

    private function hitungSkorSatu(JawabanPeserta $jawaban, float $bobot): float
    {
        $soal = $jawaban->soal;

        return match ($soal->tipe_soal) {
            'pg'          => $this->skorPG($jawaban, $bobot),
            'pg_kompleks' => $this->skorPGKompleks($jawaban, $bobot),
            'benar_salah' => $this->skorBenarSalah($jawaban, $bobot),
            'menjodohkan' => $this->skorMenjodohkan($jawaban, $bobot),
            'isian'       => $this->skorIsian($jawaban, $bobot),
            default       => 0,
        };
    }

    private function skorPG(JawabanPeserta $jawaban, float $bobot): float
    {
        $benar = $jawaban->soal->opsiJawaban->where('is_benar', true)->pluck('label')->first();
        $pilihan = $jawaban->jawaban_pg[0] ?? null;
        return $pilihan === $benar ? $bobot : 0;
    }

    private function skorPGKompleks(JawabanPeserta $jawaban, float $bobot): float
    {
        $jawabanBenar = $jawaban->soal->opsiJawaban
            ->where('is_benar', true)
            ->pluck('label')
            ->sort()
            ->values()
            ->toArray();

        $pilihan = collect($jawaban->jawaban_pg ?? [])->sort()->values()->toArray();

        if ($pilihan === $jawabanBenar) return $bobot;

        // Partial scoring: 50% jika sebagian benar
        $benarCount = count(array_intersect($pilihan, $jawabanBenar));
        $totalBenar = count($jawabanBenar);
        if ($benarCount > 0 && count($pilihan) <= $totalBenar) {
            return round(($benarCount / $totalBenar) * $bobot * 0.5, 2);
        }

        return 0;
    }

    private function skorBenarSalah(JawabanPeserta $jawaban, float $bobot): float
    {
        $opsiList = $jawaban->soal->opsiJawaban;
        $totalPernyataan = $opsiList->count();
        if ($totalPernyataan === 0) return 0;

        // jawaban_pg stores object like {"1":"benar","2":"salah","3":"benar"}
        $jawabanPeserta = $jawaban->jawaban_pg;
        if (!is_array($jawabanPeserta)) return 0;

        $benarCount = 0;
        foreach ($opsiList as $opsi) {
            $pesertaJawab = $jawabanPeserta[$opsi->label] ?? null;
            if ($pesertaJawab === null) continue;

            $kunciBenar = (bool) $opsi->is_benar;
            $pesertaPilihBenar = ($pesertaJawab === 'benar');

            if ($pesertaPilihBenar === $kunciBenar) {
                $benarCount++;
            }
        }

        return round(($benarCount / $totalPernyataan) * $bobot, 2);
    }

    private function skorMenjodohkan(JawabanPeserta $jawaban, float $bobot): float
    {
        $pasanganList = $jawaban->soal->pasangan;
        $pasanganPilihan = collect($jawaban->jawaban_pasangan ?? []);

        $total = $pasanganList->count();
        if ($total === 0) return 0;

        // Build correct mapping from pasangan records
        // Each pasangan row represents one correct pair (kiri ↔ kanan)
        // Frontend sends pairs as [kiri_pasangan_id, kanan_pasangan_id]
        // A pair is correct when kiri and kanan come from the same pasangan record (kiri === kanan)
        $correctIds = $pasanganList->pluck('id')->flip();

        $benar = 0;
        foreach ($pasanganPilihan as $pair) {
            if (is_array($pair) && count($pair) === 2) {
                [$kiri, $kanan] = $pair;
                if ($kiri === $kanan && $correctIds->has($kiri)) {
                    $benar++;
                }
            }
        }

        return $total > 0 ? round(($benar / $total) * $bobot, 2) : 0;
    }

    private function skorIsian(JawabanPeserta $jawaban, float $bobot): float
    {
        $kunciJawaban = $jawaban->soal->opsiJawaban->where('is_benar', true)->pluck('teks')->first();
        if (! $kunciJawaban) return 0;

        $jawab = strtolower(trim($jawaban->jawaban_teks ?? ''));
        $kunci = strtolower(trim($kunciJawaban));

        return $jawab === $kunci ? $bobot : 0;
    }
}
