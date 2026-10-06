<?php

namespace JothamLec\MarketingToolkit\Support;

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
     * Whether the addon works with several sites: more than one site
     * (Statamic Pro, multi-site on), and the addon's Pro edition. Free works
     * as on a single site, the default one: no hreflang, rows for every
     * site, and a sitemap and robots.txt on the default site's domain only.
     */
    public static function multiple(): bool
    {
        return self::installed() && Edition::pro();
    }

    /**
     * Whether Statamic itself has more than one site, whatever the edition.
     */
    public static function installed(): bool
    {
        return Site::multiEnabled() && Site::hasMultiple();
    }

    /**
     * Whether the current site is one the addon serves files for: any of
     * them, or in Free the default site and those on its domain.
     */
    public static function served(): bool
    {
        if (self::multiple() || ! self::installed()) {
            return true;
        }

        return self::servesUrl((string) Site::current()->absoluteUrl());
    }

    /**
     * Whether $url is on a domain the addon serves (see served()).
     */
    public static function servesUrl(string $url): bool
    {
        if (self::multiple() || ! self::installed()) {
            return true;
        }

        $host = fn (string $url) => strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host($url) === $host((string) Site::default()->absoluteUrl());
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
