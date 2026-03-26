<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

class HtmlDisplay
{
    public static function containsHtml(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return $value !== strip_tags($value);
    }

    public static function decode(?string $value): string
    {
        return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function plainText(?string $value, ?int $limit = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (static::containsHtml($value)) {
            $normalized = static::stripMarkupToText($value);
        } elseif (static::isEncodedRichText($value)) {
            $normalized = static::stripMarkupToText(static::decode($value), true);
        } else {
            $normalized = static::collapseWhitespace(static::decode($value));
        }

        return $limit === null ? $normalized : Str::limit($normalized, $limit);
    }

    public static function render(?string $value): HtmlString
    {
        if ($value === null || $value === '') {
            return new HtmlString('');
        }

        if (static::containsHtml($value)) {
            return new HtmlString(static::decodeSafeTextEntities(Purifier::clean($value, 'tiptap')));
        }

        if (static::isEncodedRichText($value)) {
            return new HtmlString(static::decodeSafeTextEntities(Purifier::clean(static::decode($value), 'tiptap')));
        }

        return new HtmlString(nl2br(htmlspecialchars(static::normalizeTextForRender(static::decode($value)), ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8')));
    }

    private static function isEncodedRichText(?string $value): bool
    {
        if ($value === null || $value === '' || static::containsHtml($value) || ! str_contains($value, '&lt;')) {
            return false;
        }

        $trimmed = trim($value);

        if (! str_starts_with($trimmed, '&lt;') || ! static::startsWithEncodedRichTextTag($trimmed)) {
            return false;
        }

        $decoded = static::decode($trimmed);

        if (! static::containsHtml($decoded)) {
            return false;
        }

        $cleaned = Purifier::clean($decoded, 'tiptap');
        $plainDecoded = static::stripMarkupToText($decoded, true);
        $plainCleaned = static::stripMarkupToText($cleaned, true);

        if ($plainCleaned === '' || $plainDecoded !== $plainCleaned) {
            return false;
        }

        return true;
    }

    private static function stripMarkupToText(string $value, bool $alreadyDecoded = false): string
    {
        $normalized = preg_replace('/<(\/?(p|div|li|ul|ol|h[1-6]|blockquote|section|article|tr|td|th))\b[^>]*>|<br\s*\/?\s*>/iu', ' ', $value) ?? $value;
        $normalized = strip_tags($normalized);
        $normalized = $alreadyDecoded ? $normalized : static::decode($normalized);

        return static::collapseWhitespace($normalized);
    }

    private static function collapseWhitespace(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($normalized);
    }

    private static function normalizeTextForRender(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);
        $normalized = preg_replace('/[^\S\n]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\n{3,}/u', "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }

    private static function decodeSafeTextEntities(string $value): string
    {
        return str_replace(
            ['&quot;', '&#34;', '&apos;', '&#39;', '&#039;', '&nbsp;'],
            ['"', '"', "'", "'", "'", ' '],
            $value,
        );
    }

    private static function startsWithEncodedRichTextTag(string $value): bool
    {
        return (bool) preg_match('/^&lt;(p|div|ul|ol|li|blockquote|section|article|h[1-6]|table|thead|tbody|tr|td|th)\b/i', $value);
    }
}
