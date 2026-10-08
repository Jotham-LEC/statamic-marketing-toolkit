<?php

namespace JothamLec\MarketingToolkit\Support;

use Illuminate\Support\Str;

class Text
{
    /**
     * Returns plain text from HTML, with the tags removed, the entities decoded, and every run of
     * whitespace (non-breaking spaces included) turned into one space.
     */
    public static function plain(?string $html): string
    {
        return Str::squish(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Returns at most $length characters, cut at a word boundary with an ellipsis.
     * It doesn't use Str::limit(), because that counts display width, adds the ellipsis
     * past $length, and leaves trailing punctuation before it.
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
     * Returns the text of the first paragraph of an HTML body that says something.
     */
    public static function firstParagraph(string $html): ?string
    {
        preg_match_all('#<p[^>]*>(.*?)</p>#is', $html, $matches);

        foreach ($matches[1] as $paragraph) {
            $text = self::plain($paragraph);

            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }
}
