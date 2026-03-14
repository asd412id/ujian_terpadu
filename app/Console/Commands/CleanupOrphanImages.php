<?php

namespace App\Console\Commands;

use App\Models\Soal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanImages extends Command
{
    protected $signature = 'soal:cleanup-orphan-images
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--hours=24 : Only delete files older than this many hours}';

    protected $description = 'Delete uploaded inline images that are not referenced by any soal';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $directory = 'soal/inline';

        if (!$disk->exists($directory)) {
            $this->info('No soal/inline directory found. Nothing to clean.');
            return self::SUCCESS;
        }

        $files = $disk->files($directory);
        if (empty($files)) {
            $this->info('No files in soal/inline directory.');
            return self::SUCCESS;
        }

        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');
        $cutoff = now()->subHours($hours)->getTimestamp();

        // Collect all referenced image paths from soal HTML fields
        $referencedPaths = $this->getReferencedPaths();

        $this->info(sprintf(
            'Found %d files in %s, %d referenced paths in soal records.',
            count($files),
            $directory,
            count($referencedPaths)
        ));

        $deleted = 0;
        $skipped = 0;

        foreach ($files as $file) {
            // Skip files newer than cutoff
            if ($disk->lastModified($file) > $cutoff) {
                $skipped++;
                continue;
            }

            // Skip files that are referenced in soal HTML
            if (in_array($file, $referencedPaths, true)) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would delete: {$file}");
                $deleted++;
            } else {
                $disk->delete($file);
                $deleted++;
            }
        }

        $action = $dryRun ? 'Would delete' : 'Deleted';
        $this->info("{$action} {$deleted} orphan image(s), skipped {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * Extract all image paths referenced in soal pertanyaan & pembahasan fields.
     */
    private function getReferencedPaths(): array
    {
        $paths = [];

        Soal::query()
            ->select(['pertanyaan', 'pembahasan'])
            ->whereNotNull('pertanyaan')
            ->orWhereNotNull('pembahasan')
            ->chunk(500, function ($soals) use (&$paths) {
                foreach ($soals as $soal) {
                    $paths = array_merge(
                        $paths,
                        $this->extractStoragePaths($soal->pertanyaan),
                        $this->extractStoragePaths($soal->pembahasan),
                    );
                }
            });

        return array_unique($paths);
    }

    /**
     * Extract storage file paths from inline <img> tags in HTML content.
     */
    private function extractStoragePaths(?string $html): array
    {
        if (empty($html) || !str_contains($html, '<img')) {
            return [];
        }

        $paths = [];

        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                if (preg_match('#/storage/(.+)$#', $src, $m)) {
                    $path = urldecode($m[1]);
                    if (str_starts_with($path, 'soal/')) {
                        $paths[] = $path;
                    }
                }
            }
        }

        return $paths;
    }
}
