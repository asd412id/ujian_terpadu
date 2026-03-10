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
                $row['pertanyaan'],
                $row['total_dijawab'],
                $row['benar'],
                $row['salah'],
                $row['kosong'],
                $row['pct_benar'] . '%',
                $row['rata_skor'],
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

                $sheet->getStyle('A1:I1')->applyFromArray([
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
                    $sheet->getStyle("A2:I{$totalRows}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFE5E7EB'],
                            ],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    $sheet->getStyle("A2:B{$totalRows}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D2:I{$totalRows}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getColumnDimension('C')->setWidth(60);

                    for ($row = 2; $row <= $totalRows; $row++) {
                        if ($row % 2 === 0) {
                            $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => 'FFF9FAFB'],
                                ],
                            ]);
                        }
                    }
                }

                $sheet->setAutoFilter("A1:I1");
                $sheet->freezePane('A2');
            },
        ];
    }
}
