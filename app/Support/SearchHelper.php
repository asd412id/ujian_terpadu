<?php

namespace App\Support;

/**
 * Utility functions for search and query operations.
 */
class SearchHelper
{
    /**
     * Escape LIKE wildcard characters (%, _) in user-provided search input.
     *
     * Without escaping, a user searching for "%" would match ALL rows (DoS vector),
     * and "_" would match any single character (data leak).
     *
     * Usage: ->where('nama', 'like', '%' . SearchHelper::escapeLike($search) . '%')
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }

    /**
     * Wrap escaped search term with LIKE wildcards for "contains" search.
     *
     * Usage: ->where('nama', 'like', SearchHelper::containsLike($search))
     */
    public static function containsLike(string $value): string
    {
        return '%' . self::escapeLike($value) . '%';
    }
}
