<?php

namespace App\Console\Commands;

use App\Models\SesiPeserta;
use Illuminate\Console\Command;

class ExportBenchmarkTokens extends Command
{
    protected $signature = 'benchmark:export-tokens
                            {--output=benchmark/tokens.json : Output file path}';

    protected $description = 'Export token_ujian benchmark peserta ke JSON untuk k6';

    public function handle(): int
    {
        $this->info('Mengexport token benchmark...');

        $tokens = SesiPeserta::query()
            ->whereHas('peserta', function ($q) {
                $q->where('nama', 'like', 'BENCH_%');
            })
            ->where('status', 'mengerjakan')
            ->with(['peserta:id,nama,username_ujian', 'sesi:id,paket_id', 'sesi.paket:id,jumlah_soal'])
            ->get()
            ->map(function (SesiPeserta $sp) {
                $soalIds = $sp->sesi->paket->soal()->pluck('soal.id')->toArray();
                return [
                    'sesi_peserta_id' => $sp->id,
                    'token'           => $sp->token_ujian,
                    'username'        => $sp->peserta->username_ujian,
                    'nama'            => $sp->peserta->nama,
                    'soal_ids'        => $soalIds,
                ];
            });

        if ($tokens->isEmpty()) {
            $this->error('Tidak ada data benchmark. Jalankan: php artisan benchmark:seed');
            return self::FAILURE;
        }

        $output = $this->option('output');
        $dir = dirname($output);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'generated_at' => now()->toIso8601String(),
            'total'        => $tokens->count(),
            'soal_ids'     => $tokens->first()['soal_ids'],
            'tokens'       => $tokens->map(fn ($t) => [
                'sesi_peserta_id' => $t['sesi_peserta_id'],
                'token'           => $t['token'],
                'username'        => $t['username'],
            ])->values()->toArray(),
        ];

        file_put_contents(base_path($output), json_encode($data, JSON_PRETTY_PRINT));

        $this->info("Exported {$tokens->count()} tokens ke {$output}");
        $this->info("Soal IDs: " . count($data['soal_ids']));

        return self::SUCCESS;
    }
}
