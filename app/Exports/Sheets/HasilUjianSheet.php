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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class HasilUjianSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(
        protected array $data,
    ) {}

    public function title(): string
    {
        return 'Hasil Ujian';
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = [
            'No',
            'Nama Peserta',
            'NIS',
            'NISN',
            'Kelas',
            'Jurusan',
            'Sekolah',
            'Paket Ujian',
            'Sesi Ujian',
            'Nilai Akhir',
            'Benar',
            'Salah',
            'Kosong',
            'Total Soal',
            'Durasi (menit)',
            'Status',
            'Keterangan',
            'Waktu Mulai',
            'Waktu Submit',
        ];

        foreach ($this->data as $i => $row) {
            $rows[] = [
                $i + 1,
                $row['nama_peserta'],
                $row['nis'],
                $row['nisn'],
                $row['kelas'],
                $row['jurusan'],
                $row['sekolah'],
                $row['paket'],
                $row['sesi'],
                $row['nilai_akhir'] ?? 0,
                $row['jumlah_benar'] ?? 0,
                $row['jumlah_salah'] ?? 0,
                $row['jumlah_kosong'] ?? 0,
                $row['total_soal'] ?? 0,
                $row['durasi_menit'] ?? 0,
                $row['status'],
                $row['keterangan'],
                $row['mulai_at'],
                $row['submit_at'],
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $totalRows = count($this->data) + 1;

                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF2563EB'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFD0D5DD'],
                        ],
                    ],
                ];
                $sheet->getStyle('A1:S1')->applyFromArray($headerStyle);
                $sheet->getRowDimension(1)->setRowHeight(30);

                if ($totalRows > 1) {
                    $dataRange = "A2:S{$totalRows}";
                    $sheet->getStyle($dataRange)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFE5E7EB'],
                            ],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);

                    $sheet->getStyle("A2:A{$totalRows}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("J2:J{$totalRows}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    $sheet->getStyle("K2:N{$totalRows}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("O2:O{$totalRows}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("P2:Q{$totalRows}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    for ($row = 2; $row <= $totalRows; $row++) {
                        if ($row % 2 === 0) {
                            $sheet->getStyle("A{$row}:S{$row}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => 'FFF9FAFB'],
                                ],
                            ]);
                        }
                    }

                    for ($row = 2; $row <= $totalRows; $row++) {
                        $nilai = $sheet->getCell("J{$row}")->getValue();
                        if (is_numeric($nilai)) {
                            if ($nilai >= 80) {
                                $color = 'FF16A34A'; // green
                            } elseif ($nilai >= 70) {
                                $color = 'FF2563EB'; // blue
                            } elseif ($nilai >= 60) {
                                $color = 'FFD97706'; // amber
                            } else {
                                $color = 'FFDC2626'; // red
                            }
                            $sheet->getStyle("J{$row}")->applyFromArray([
                                'font' => ['color' => ['argb' => $color]],
                            ]);
                        }

                        $keterangan = $sheet->getCell("Q{$row}")->getValue();
                        if ($keterangan === 'Lulus') {
                            $sheet->getStyle("Q{$row}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['argb' => 'FF16A34A']],
                            ]);
                        } elseif ($keterangan === 'Tidak Lulus') {
                            $sheet->getStyle("Q{$row}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['argb' => 'FFDC2626']],
                            ]);
                        }
                    }
                }

                $sheet->setAutoFilter("A1:S1");

                $sheet->freezePane('A2');

                $sheet->getPageSetup()->setOrientation(
                    \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
                );
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
            },
        ];
    }
}
