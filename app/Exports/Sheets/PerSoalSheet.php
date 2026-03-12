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

class PerSoalSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(
        protected array $data,
    ) {}

    public function title(): string
    {
        return 'Analisis Per Soal';
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = [
            'No Soal',
            'Tipe Soal',
            'Kategori Soal',
            'Pertanyaan',
            'Total Dijawab',
            'Benar',
            'Salah',
            'Kosong',
            '% Benar',
            'Rata-rata Skor',
        ];

        foreach ($this->data as $row) {
            $rows[] = [
                $row['nomor'],
                strtoupper($row['tipe']),
                $row['kategori'] ?? '-',
                $row['pertanyaan'],
                $row['total_dijawab'] ?? 0,
                $row['benar'] ?? 0,
                $row['salah'] ?? 0,
                $row['kosong'] ?? 0,
                ($row['pct_benar'] ?? 0) . '%',
                $row['rata_skor'] ?? 0,
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

                $sheet->getStyle('A1:J1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF7C3AED'],
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
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);

                if ($totalRows > 1) {
                    $sheet->getStyle("A2:J{$totalRows}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFE5E7EB'],
                            ],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    $sheet->getStyle("A2:C{$totalRows}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E2:J{$totalRows}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getColumnDimension('D')->setWidth(60);

                    for ($row = 2; $row <= $totalRows; $row++) {
                        if ($row % 2 === 0) {
                            $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => 'FFF9FAFB'],
                                ],
                            ]);
                        }
                    }
                }

                $sheet->setAutoFilter("A1:J1");
                $sheet->freezePane('A2');
            },
        ];
    }
}
