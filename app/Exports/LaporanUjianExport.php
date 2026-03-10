<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanUjianExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected array $hasilData,
        protected array $rekap,
        protected array $filters,
        protected array $perSoalData = [],
    ) {}

    public function sheets(): array
    {
        $sheets = [
            new Sheets\RekapSheet($this->rekap, $this->filters),
            new Sheets\HasilUjianSheet($this->hasilData),
        ];

        if (!empty($this->perSoalData)) {
            $sheets[] = new Sheets\PerSoalSheet($this->perSoalData);
        }

        return $sheets;
    }
}
