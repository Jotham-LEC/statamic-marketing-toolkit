<?php

namespace JothamLec\Seo\Support;

use Closure;
use ReflectionProperty;
use Statamic\Facades\Site;
use Statamic\Sites\Sites as StatamicSites;

/**
 * Statamic's sites as the redirects, the 404 log and reports see them. On a
 * single site their rows name no site (null: every site), exactly as before
 * multi-site support; with more than one site they name the one they are for.
 */
final class Sites
{
    /**
     * Whether there is more than one site (Statamic Pro, multi-site on).
     */
    public static function multiple(): bool
    {
        return Site::multiEnabled() && Site::hasMultiple();
    }

    /**
     * The handle a row stores for $site: null while there is only one site.
     */
    public static function scope(?string $site): ?string
    {
        return self::multiple() && $site !== null && Site::get($site) ? $site : null;
    }

    /**
     * Every site's handle.
     *
     * @return list<string>
     */
    public static function handles(): array
    {
        return Site::all()->map->handle()->values()->all();
    }

    /**
     * Site handle => name, for a select.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return Site::all()->mapWithKeys(fn ($site) => [$site->handle() => (string) $site->name()])->all();
    }

    /**
     * Runs $work with $site as Statamic's current site (null: as it is), then
     * puts back what was there, so the site stays worked out from the request
     * afterwards rather than pinned.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public static function as(?string $site, Closure $work): mixed
    {
        if ($site === null || ! Site::get($site)) {
            return $work();
        }

        $sites = Site::getFacadeRoot();
        $current = new ReflectionProperty(StatamicSites::class, 'current');
        $previous = $current->getValue($sites);

        Site::setCurrent($site);

        try {
            return $work();
        } finally {
            $current->setValue($sites, $previous);
        }
    }
}
