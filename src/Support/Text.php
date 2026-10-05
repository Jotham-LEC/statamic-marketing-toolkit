<?php

namespace JothamLec\Seo\Support;

use Illuminate\Support\Str;

class Text
{
    /**
     * Plain text from HTML: tags gone, entities decoded, every run of
     * whitespace (non-breaking spaces included) one space.
     */
    public static function plain(?string $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }

    /**
     * At most $length characters, cut at a word boundary with an ellipsis.
     */
    public static function limit(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space ? mb_substr($cut, 0, $space) : $cut, ' ,.;:–—-').'…';
    }

    /**
     * The text of the first paragraph of an HTML body, skipping paragraphs
     * that start with one of $skipPrefixes (a credit line, an editor's note).
     *
     * @param  list<string>  $skipPrefixes
     */
    public static function firstParagraph(string $html, array $skipPrefixes = []): ?string
    {
        preg_match_all('#<p[^>]*>(.*?)</p>#is', $html, $matches);

        foreach ($matches[1] as $paragraph) {
            $text = self::plain($paragraph);

            if ($text !== '' && ! Str::startsWith($text, $skipPrefixes)) {
                return $text;
            }
        }

        return null;
    }
}
