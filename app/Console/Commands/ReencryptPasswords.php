<?php

namespace App\Console\Commands;

use App\Models\Peserta;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;

class ReencryptPasswords extends Command
{
    protected $signature = 'peserta:reencrypt-passwords
                            {old_key : The old APP_KEY (base64:... format) used to encrypt existing password_plain values}
                            {--chunk=500 : Number of records to process per batch}
                            {--dry-run : Preview without making changes}';

    protected $description = 'Re-encrypt all peserta password_plain values from an old APP_KEY to the current APP_KEY';

    public function handle(): int
    {
        $oldKeyRaw = $this->argument('old_key');

        if (!str_starts_with($oldKeyRaw, 'base64:')) {
            $this->error('Key must start with "base64:"');
            return self::FAILURE;
        }

        $oldKeyBytes = base64_decode(substr($oldKeyRaw, 7));
        if (strlen($oldKeyBytes) !== 32) {
            $this->error('Invalid key length. Expected 32 bytes for AES-256-CBC.');
            return self::FAILURE;
        }

        $oldEncrypter = new Encrypter($oldKeyBytes, config('app.cipher'));
        $isDryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        $total = Peserta::whereNotNull('password_plain')->where('password_plain', '!=', '')->count();
        $this->info("Found {$total} peserta with password_plain values.");

        if ($total === 0) {
            $this->warn('Nothing to re-encrypt.');
            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->warn('DRY RUN — no changes will be made.');
        }

        // First, test decryption with the old key on a sample
        $sample = Peserta::whereNotNull('password_plain')->where('password_plain', '!=', '')->first();
        try {
            $plain = $oldEncrypter->decrypt($sample->password_plain);
            $this->info("Sample decrypt OK: {$sample->username_ujian} → password length " . strlen($plain));
        } catch (\Exception $e) {
            $this->error("Old key cannot decrypt sample record ({$sample->username_ujian}): {$e->getMessage()}");
            $this->error('Aborting. Please verify the old APP_KEY is correct.');
            return self::FAILURE;
        }

        // Also test that current key can already decrypt (skip if so)
        try {
            decrypt($sample->password_plain);
            $this->warn('Current APP_KEY can already decrypt passwords. Re-encryption may not be needed.');
            if (!$this->confirm('Continue anyway?')) {
                return self::SUCCESS;
            }
        } catch (\Exception $e) {
            // Expected — current key can't decrypt, so we proceed
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $success = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        Peserta::whereNotNull('password_plain')
            ->where('password_plain', '!=', '')
            ->chunkById($chunkSize, function ($pesertaChunk) use (
                $oldEncrypter, $isDryRun, &$success, &$skipped, &$failed, &$errors, $bar
            ) {
                foreach ($pesertaChunk as $peserta) {
                    try {
                        // Try current key first — already re-encrypted?
                        try {
                            decrypt($peserta->password_plain);
                            $skipped++;
                            $bar->advance();
                            continue;
                        } catch (\Exception $e) {
                            // Can't decrypt with current key — proceed with old key
                        }

                        // Decrypt with old key
                        $plain = $oldEncrypter->decrypt($peserta->password_plain);

                        if (!$isDryRun) {
                            // Re-encrypt with current key and update
                            $peserta->password_plain = encrypt($plain);
                            $peserta->timestamps = false;
                            $peserta->save();
                        }

                        $success++;
                    } catch (\Exception $e) {
                        $failed++;
                        $errors[] = "{$peserta->username_ujian}: {$e->getMessage()}";
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info("Re-encryption complete:");
        $this->table(
            ['Status', 'Count'],
            [
                ['Re-encrypted', $success],
                ['Skipped (already OK)', $skipped],
                ['Failed', $failed],
                ['Total', $success + $skipped + $failed],
            ]
        );

        if ($failed > 0) {
            $this->warn("Failed records:");
            foreach (array_slice($errors, 0, 20) as $err) {
                $this->line("  - {$err}");
            }
            if (count($errors) > 20) {
                $this->line("  ... and " . (count($errors) - 20) . " more.");
            }
        }

        if ($isDryRun) {
            $this->warn('This was a DRY RUN. Run without --dry-run to apply changes.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
