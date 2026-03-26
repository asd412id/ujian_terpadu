<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('narasi_soal', function (Blueprint $table) {
            $table->longText('konten_plain')->nullable()->after('konten');
        });

        DB::table('narasi_soal')
            ->select(['id', 'konten'])
            ->orderBy('id')
            ->cursor()
            ->each(function ($narasi) {
                DB::table('narasi_soal')
                    ->where('id', $narasi->id)
                    ->update([
                        'konten_plain' => $this->normalizeText($narasi->konten),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('narasi_soal', function (Blueprint $table) {
            $table->dropColumn('konten_plain');
        });
    }

    private function normalizeText(?string $konten): string
    {
        $decoded = html_entity_decode($konten ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/<(\/?(p|div|li|ul|ol|h[1-6]|blockquote|section|article|tr|td|th))\b[^>]*>|<br\s*\/?\s*>/iu', ' ', $decoded) ?? $decoded;
        $normalized = strip_tags($normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
};
