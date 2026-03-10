<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RekapSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(
        protected array $rekap,
        protected array $filters,
    ) {}

    public function title(): string
    {
        return 'Rekap';
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['LAPORAN HASIL UJIAN TERPADU'];
        $rows[] = ['Tanggal Export: ' . now()->translatedFormat('d F Y, H:i') . ' WIB'];
        $rows[] = [];

        $rows[] = ['FILTER YANG DITERAPKAN'];
        $rows[] = ['Paket Ujian', $this->filters['paket_nama'] ?? 'Semua Paket'];
        $rows[] = ['Sekolah', $this->filters['sekolah_nama'] ?? 'Semua Sekolah'];
        $rows[] = ['Status', match ($this->filters['status'] ?? '') {
            'lulus' => 'Lulus',
            'tidak_lulus' => 'Tidak Lulus',
            default => 'Semua',
        }];
        $rows[] = [];

        $rows[] = ['STATISTIK'];
        $rows[] = ['Total Peserta', $this->rekap['total_peserta']];
        $rows[] = ['Sudah Ujian', $this->rekap['sudah_ujian']];
        $rows[] = ['Lulus (≥ 70)', $this->rekap['lulus']];
        $rows[] = ['Tidak Lulus (< 70)', $this->rekap['tidak_lulus']];
        $rows[] = ['Rata-rata Nilai', $this->rekap['rata_rata']];

        if ($this->rekap['total_peserta'] > 0) {
            $pctLulus = round(($this->rekap['lulus'] / $this->rekap['total_peserta']) * 100, 1);
            $rows[] = ['Persentase Lulus', $pctLulus . '%'];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A1:D1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF1E3A5F']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);

                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 10, 'italic' => true, 'color' => ['argb' => 'FF666666']],
                ]);

                $sheet->getStyle('A4')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF1E3A5F']],
                ]);
                $sheet->getStyle('A5:A7')->applyFromArray([
                    'font' => ['bold' => true],
                ]);

                $sheet->getStyle('A9')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF1E3A5F']],
                ]);

                $lastRow = 10 + 4 + ($this->rekap['total_peserta'] > 0 ? 1 : 0);
                $sheet->getStyle("A10:A{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true],
                ]);

                $sheet->getColumnDimension('A')->setWidth(25);
                $sheet->getColumnDimension('B')->setWidth(20);
            },
        ];
    }
}
