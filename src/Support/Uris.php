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
    public static function forget(): void
    {
        Blink::store('entry-uris')->flush();
        Blink::store('structure-uris')->flush();
    }
}
