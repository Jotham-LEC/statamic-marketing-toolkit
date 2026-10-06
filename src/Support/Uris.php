<?php

namespace JothamLec\Seo\Support;

use Statamic\Facades\Blink;

/**
 * Statamic remembers each entry's URI, and each tree's, for the rest of the
 * request. Content whose address is being worked out before and after a
 * change needs them forgotten in between.
 */
final class Uris
{
    /**
     * A path compared with others: decoded, without a trailing slash (home
     * stays `/`).
     */
    public static function normalizePath(string $path): string
    {
        return '/'.trim(rawurldecode((string) parse_url($path, PHP_URL_PATH)), '/');
    }

    public static function forget(): void
    {
        Blink::store('entry-uris')->flush();
        Blink::store('structure-uris')->flush();
    }
}
