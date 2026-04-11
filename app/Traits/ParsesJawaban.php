<?php

namespace App\Traits;

/**
 * Shared jawaban parsing logic for determining answer type and structure.
 *
 * Used by both RedisExamService (Redis hot path) and JawabanService (DB fallback)
 * to ensure consistent jawaban classification.
 */
trait ParsesJawaban
{
    /**
     * Parse jawaban to determine type and structure.
     *
     * Returns array with keys: jawaban_pg, jawaban_teks, jawaban_pasangan, is_terjawab.
     */
    private function parseJawaban(mixed $jawaban): array
    {
        $isTerjawab = !empty($jawaban);

        if (is_array($jawaban)) {
            $isPasangan = isset($jawaban[0]) && is_array($jawaban[0]);
            $isBenarSalah = !$isPasangan && !array_is_list($jawaban);

            if ($isBenarSalah) {
                return [
                    'jawaban_pg'       => $jawaban,
                    'jawaban_pasangan' => null,
                    'jawaban_teks'     => null,
                    'is_terjawab'      => $isTerjawab,
                ];
            }

            return [
                'jawaban_pg'       => $isPasangan ? null : $jawaban,
                'jawaban_pasangan' => $isPasangan ? $jawaban : null,
                'jawaban_teks'     => null,
                'is_terjawab'      => $isTerjawab,
            ];
        }

        return [
            'jawaban_pg'       => null,
            'jawaban_pasangan' => null,
            'jawaban_teks'     => (string) $jawaban,
            'is_terjawab'      => $isTerjawab && trim((string) $jawaban) !== '',
        ];
    }
}
