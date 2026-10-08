<?php

namespace JothamLec\MarketingToolkit\Support;

use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Blink;
use Statamic\Facades\Site;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Helps work out content addresses. Statamic remembers each entry's URI, and
 * each tree's, for the rest of the request, so content whose address is worked
 * out before and after a change needs them forgotten in between.
 */
final class Uris
{
    /**
     * Returns a path in a form that compares with others, which is decoded and without a trailing
     * slash (the home page stays `/`).
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

    /**
     * Determines whether a term's address is a page. Statamic answers a term's URI only
     * when its template exists (LocalizedTerm::toResponse()) and 404s it
     * otherwise, so a taxonomy without a `{taxonomy}.show` view has addresses
     * but no pages, and renaming one of its terms moves nothing worth a redirect.
     */
    public static function termHasPage(Term $term): bool
    {
        $localized = $term instanceof LocalizedTerm ? $term : $term->in(Site::default()->handle());

        return $localized !== null && view()->exists($localized->template());
    }
}
