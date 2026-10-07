<?php

namespace JothamLec\MarketingToolkit\Support;

use Closure;
use Illuminate\Http\Request;
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
     * Whether Statamic has several sites (Statamic Pro, multi-site on).
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
     * The handles of the sites the signed-in user may work on (Statamic's
     * `access {site} site` permission; a super user, every one), or every
     * site's while the addon works as on a single site.
     *
     * @return list<string>
     */
    public static function accessible(): array
    {
        return self::multiple() ? Site::authorized()->map->handle()->values()->all() : self::handles();
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
     * Whether the addresses built for this request may be cached and served
     * to every request. A site whose URL is relative (`url: '/'`) takes its
     * domain from the request's Host header, which a client sets to anything:
     * cached after a save, one request with `Host: evil.test` would give
     * every visitor a sitemap of evil.test addresses until the next save. So
     * with such a site only a request on a host the install names (an
     * absolute site URL's, else APP_URL's) is cached; others are built afresh,
     * for that request alone. With every site's URL absolute nothing depends
     * on the Host, and everything is cached.
     */
    public static function trustsHost(Request $request): bool
    {
        $urls = Site::all()->map(fn ($site) => (string) $site->url());
        $absolute = $urls->filter(fn (string $url) => preg_match('#^https?://#i', $url) === 1);

        if ($absolute->count() === $urls->count()) {
            return true;
        }

        return $absolute->push((string) config('app.url'))
            ->map(fn (string $url) => strtolower((string) parse_url($url, PHP_URL_HOST)))
            ->contains(strtolower($request->getHost()));
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
