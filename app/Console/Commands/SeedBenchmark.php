<?php

namespace App\Console\Commands;

use App\Models\DinasPendidikan;
use App\Models\KategoriSoal;
use App\Models\OpsiJawaban;
use App\Models\PaketSoal;
use App\Models\PaketUjian;
use App\Models\Peserta;
use App\Models\Sekolah;
use App\Models\SesiPeserta;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedBenchmark extends Command
{
    protected $signature = 'benchmark:seed
                            {--peserta=3000 : Jumlah peserta yang dibuat}
                            {--soal=40 : Jumlah soal per paket}
                            {--cleanup : Hapus data benchmark yang sudah dibuat}';

    protected $description = 'Seed data benchmark untuk load testing (peserta, sesi, paket, soal)';

    private const BENCHMARK_PREFIX = 'BENCH_';

    public function handle(): int
    {
        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        $jumlahPeserta = (int) $this->option('peserta');
        $jumlahSoal = (int) $this->option('soal');

        $this->info("=== Benchmark Seeder ===");
        $this->info("Peserta: {$jumlahPeserta}, Soal: {$jumlahSoal}");

        $startTime = microtime(true);

        DB::beginTransaction();
        try {
            // 1. Dinas Pendidikan
            $this->info('[1/7] Membuat Dinas Pendidikan...');
            $dinas = DinasPendidikan::create([
                'nama'         => self::BENCHMARK_PREFIX . 'Dinas Pendidikan Benchmark',
                'kode_wilayah' => '99.99',
                'kota'         => 'Benchmark City',
                'provinsi'     => 'Benchmark Province',
                'alamat'       => 'Jl. Benchmark No. 1',
                'telepon'      => '0000000000',
                'email'        => 'bench@benchmark.test',
                'kepala_dinas' => 'Benchmark Admin',
                'is_active'    => true,
            ]);

            // 2. Sekolah
            $this->info('[2/7] Membuat Sekolah...');
            $sekolah = Sekolah::create([
                'dinas_id'       => $dinas->id,
                'nama'           => self::BENCHMARK_PREFIX . 'SMA Benchmark',
                'npsn'           => '99999999',
                'jenjang'        => 'SMA',
                'alamat'         => 'Jl. Benchmark No. 1',
                'kota'           => 'Benchmark City',
                'telepon'        => '0000000000',
                'email'          => 'sekolah@benchmark.test',
                'kepala_sekolah' => 'Benchmark Kepsek',
                'is_active'      => true,
            ]);

            // 3. User (pengawas + operator)
            $this->info('[3/7] Membuat User pengawas...');
            $user = User::create([
                'name'       => self::BENCHMARK_PREFIX . 'Pengawas',
                'email'      => 'bench-pengawas@benchmark.test',
                'password'   => Hash::make('benchmark123'),
                'role'       => 'pengawas',
                'sekolah_id' => $sekolah->id,
                'is_active'  => true,
            ]);

            // 4. Kategori + Soal + Opsi
            $this->info("[4/7] Membuat {$jumlahSoal} Soal + Opsi...");
            $kategori = KategoriSoal::create([
                'nama'      => self::BENCHMARK_PREFIX . 'Kategori Benchmark',
                'kode'      => 'BN-001',
                'jenjang'   => 'SMA',
                'kelompok'  => 'Umum',
                'kurikulum' => 'Merdeka',
                'urutan'    => 99,
                'is_active' => true,
            ]);

            $soalIds = [];
            $opsiData = [];
            for ($i = 1; $i <= $jumlahSoal; $i++) {
                $soal = Soal::create([
                    'kategori_id'       => $kategori->id,
                    'sekolah_id'        => $sekolah->id,
                    'created_by'        => $user->id,
                    'tipe_soal'         => 'pg',
                    'pertanyaan'        => "Soal benchmark nomor {$i}. Berapakah hasil dari {$i} + {$i}?",
                    'tingkat_kesulitan' => 'sedang',
                    'bobot'             => 1.00,
                    'pembahasan'        => "Jawabannya adalah " . ($i * 2),
                    'tahun_soal'        => date('Y'),
                    'is_active'         => true,
                    'is_verified'       => true,
                ]);
                $soalIds[] = $soal->id;

                $labels = ['A', 'B', 'C', 'D', 'E'];
                $benarIdx = $i % 5;
                foreach ($labels as $idx => $label) {
                    $opsiData[] = [
                        'id'       => Str::uuid()->toString(),
                        'soal_id'  => $soal->id,
                        'label'    => $label,
                        'teks'     => "Opsi {$label} untuk soal {$i}" . ($idx === $benarIdx ? ' (benar)' : ''),
                        'is_benar' => $idx === $benarIdx,
                        'urutan'   => $idx + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            // Batch insert opsi
            foreach (array_chunk($opsiData, 500) as $chunk) {
                OpsiJawaban::insert($chunk);
            }

            // 5. Paket Ujian + link soal
            $this->info('[5/7] Membuat Paket Ujian...');
            $paket = PaketUjian::create([
                'sekolah_id'      => $sekolah->id,
                'created_by'      => $user->id,
                'nama'            => self::BENCHMARK_PREFIX . 'Paket Benchmark Load Test',
                'kode'            => 'BN-0001',
                'jenis_ujian'     => 'TRYOUT',
                'jenjang'         => 'SMA',
                'deskripsi'       => 'Paket untuk benchmark load testing',
                'durasi_menit'    => 120,
                'jumlah_soal'     => $jumlahSoal,
                'acak_soal'       => false,
                'acak_opsi'       => false,
                'tampilkan_hasil' => false,
                'boleh_kembali'   => true,
                'tanggal_mulai'   => now()->subDay(),
                'tanggal_selesai' => now()->addDays(7),
                'status'          => 'aktif',
            ]);

            $paketSoalData = [];
            foreach ($soalIds as $idx => $soalId) {
                $paketSoalData[] = [
                    'id'        => Str::uuid()->toString(),
                    'paket_id'  => $paket->id,
                    'soal_id'   => $soalId,
                    'nomor_urut' => $idx + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            PaketSoal::insert($paketSoalData);

            // 6. Sesi Ujian
            $this->info('[6/7] Membuat Sesi Ujian...');
            $sesi = SesiUjian::create([
                'paket_id'      => $paket->id,
                'nama_sesi'     => self::BENCHMARK_PREFIX . 'Sesi Benchmark',
                'ruangan'       => 'Online',
                'pengawas_id'   => $user->id,
                'waktu_mulai'   => now()->subMinutes(10),
                'waktu_selesai' => now()->addHours(3),
                'status'        => 'berlangsung',
                'kapasitas'     => $jumlahPeserta,
            ]);

            // 7. Peserta + SesiPeserta (batch)
            $this->info("[7/7] Membuat {$jumlahPeserta} Peserta + SesiPeserta (batch)...");
            $bar = $this->output->createProgressBar($jumlahPeserta);
            $bar->start();

            $batchSize = 500;
            $passwordHash = Hash::make('bench123');
            $passwordPlain = encrypt('bench123');

            for ($batch = 0; $batch < ceil($jumlahPeserta / $batchSize); $batch++) {
                $pesertaBatch = [];
                $sesiPesertaBatch = [];
                $count = min($batchSize, $jumlahPeserta - ($batch * $batchSize));

                for ($i = 0; $i < $count; $i++) {
                    $num = ($batch * $batchSize) + $i + 1;
                    $pesertaId = Str::uuid()->toString();
                    $token = Str::random(64);

                    $pesertaBatch[] = [
                        'id'             => $pesertaId,
                        'sekolah_id'     => $sekolah->id,
                        'nama'           => self::BENCHMARK_PREFIX . "Peserta {$num}",
                        'nis'            => str_pad($num, 6, '0', STR_PAD_LEFT),
                        'nisn'           => '90' . str_pad($num, 8, '0', STR_PAD_LEFT),
                        'kelas'          => 'XII-' . ceil($num / 40),
                        'jurusan'        => 'IPA',
                        'jenis_kelamin'  => $num % 2 === 0 ? 'L' : 'P',
                        'tanggal_lahir'  => '2008-01-01',
                        'tempat_lahir'   => 'Benchmark City',
                        'username_ujian' => 'bench' . str_pad($num, 5, '0', STR_PAD_LEFT),
                        'password_ujian' => $passwordHash,
                        'password_plain' => $passwordPlain,
                        'is_active'      => true,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];

                    $urutanSoal = range(1, $jumlahSoal);

                    $sesiPesertaBatch[] = [
                        'id'                  => Str::uuid()->toString(),
                        'sesi_id'             => $sesi->id,
                        'peserta_id'          => $pesertaId,
                        'token_ujian'         => $token,
                        'urutan_soal'         => json_encode($urutanSoal),
                        'status'              => 'mengerjakan',
                        'ip_address'          => '127.0.0.' . (($num % 254) + 1),
                        'browser_info'        => 'k6-benchmark',
                        'device_type'         => 'desktop',
                        'mulai_at'            => now(),
                        'submit_at'           => null,
                        'durasi_aktual_detik' => null,
                        'soal_terjawab'       => 0,
                        'soal_ditandai'       => 0,
                        'nilai_akhir'         => null,
                        'nilai_benar'         => null,
                        'jumlah_benar'        => 0,
                        'jumlah_salah'        => 0,
                        'jumlah_kosong'       => $jumlahSoal,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ];
                }

                Peserta::insert($pesertaBatch);
                SesiPeserta::insert($sesiPesertaBatch);
                $bar->advance($count);
            }

            $bar->finish();
            $this->newLine();

            DB::commit();

            $elapsed = round(microtime(true) - $startTime, 2);
            $this->newLine();
            $this->info("=== Benchmark seed selesai dalam {$elapsed}s ===");
            $this->info("Dinas: {$dinas->id}");
            $this->info("Sekolah: {$sekolah->id}");
            $this->info("Paket: {$paket->id}");
            $this->info("Sesi: {$sesi->id}");
            $this->info("Peserta: {$jumlahPeserta}");
            $this->info("Soal: {$jumlahSoal}");
            $this->newLine();
            $this->info("Jalankan: php artisan benchmark:export-tokens");

            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Seed gagal: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    private function cleanup(): int
    {
        $this->warn('Menghapus semua data benchmark...');

        DB::beginTransaction();
        try {
            // Delete in reverse order of dependencies
            $pesertaIds = Peserta::where('nama', 'like', self::BENCHMARK_PREFIX . '%')->pluck('id');
            $sesiPesertaIds = SesiPeserta::whereIn('peserta_id', $pesertaIds)->pluck('id');

            $this->info('Menghapus jawaban peserta...');
            DB::table('jawaban_peserta')->whereIn('sesi_peserta_id', $sesiPesertaIds)->delete();

            $this->info('Menghapus log aktivitas...');
            DB::table('log_aktivitas_ujian')->whereIn('sesi_peserta_id', $sesiPesertaIds)->delete();

            $this->info('Menghapus sesi peserta...');
            SesiPeserta::whereIn('peserta_id', $pesertaIds)->delete();

            $paketIds = PaketUjian::where('nama', 'like', self::BENCHMARK_PREFIX . '%')->pluck('id');
            $sesiIds = SesiUjian::whereIn('paket_id', $paketIds)->pluck('id');

            $this->info('Menghapus sesi ujian...');
            SesiUjian::whereIn('id', $sesiIds)->delete();

            $this->info('Menghapus paket soal pivot...');
            PaketSoal::whereIn('paket_id', $paketIds)->delete();

            $this->info('Menghapus paket ujian...');
            PaketUjian::whereIn('id', $paketIds)->delete();

            $sekolahIds = Sekolah::where('nama', 'like', self::BENCHMARK_PREFIX . '%')->pluck('id');

            $this->info('Menghapus opsi jawaban...');
            $soalIds = Soal::whereIn('sekolah_id', $sekolahIds)->pluck('id');
            OpsiJawaban::whereIn('soal_id', $soalIds)->delete();

            $this->info('Menghapus soal...');
            Soal::whereIn('sekolah_id', $sekolahIds)->delete();

            $this->info('Menghapus kategori...');
            KategoriSoal::where('nama', 'like', self::BENCHMARK_PREFIX . '%')->delete();

            $this->info('Menghapus peserta...');
            Peserta::whereIn('id', $pesertaIds)->delete();

            $this->info('Menghapus user...');
            User::where('name', 'like', self::BENCHMARK_PREFIX . '%')->delete();

            $this->info('Menghapus sekolah...');
            Sekolah::whereIn('id', $sekolahIds)->delete();

            $this->info('Menghapus dinas...');
            DinasPendidikan::where('nama', 'like', self::BENCHMARK_PREFIX . '%')->delete();

            DB::commit();
            $this->info('Cleanup benchmark selesai!');
            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Cleanup gagal: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
