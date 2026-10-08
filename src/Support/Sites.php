<?php

namespace JothamLec\MarketingToolkit\Support;

use Closure;
use Illuminate\Http\Request;
use ReflectionProperty;
use Statamic\Facades\Site;
use Statamic\Sites\Sites as StatamicSites;

/**
 * Describes Statamic's sites as the redirects, the 404 log and reports see them. On a single
 * site, their rows name no site (null means every site), exactly as before multi-site support.
 * With more than one site, they name the one they are for.
 */
final class Sites
{
    /**
     * Determines whether Statamic has several sites (with Statamic Pro and multi-site on).
     */
    public static function multiple(): bool
    {
        return Site::multiEnabled() && Site::hasMultiple();
    }

    /**
     * Returns the handle a row stores for $site, which is null while there is only one site.
     */
    public static function scope(?string $site): ?string
    {
        return self::multiple() && $site !== null && Site::get($site) ? $site : null;
    }

    /**
     * @return list<string>
     */
    public static function handles(): array
    {
        return Site::all()->map->handle()->values()->all();
    }

    /**
     * Returns the handles of the sites the signed-in user may work on (Statamic's
     * `access {site} site` permission, or every one for a super user), or every
     * site's handle while the addon works as it does on a single site.
     *
     * @return list<string>
     */
    public static function accessible(): array
    {
        return self::multiple() ? Site::authorized()->map->handle()->values()->all() : self::handles();
    }

    /**
     * Determines whether the signed-in user may work on every site (as a super user, or with
     * `access {site} site` for each), and so on rules for every site.
     */
    public static function accessesAll(): bool
    {
        return array_diff(self::handles(), self::accessible()) === [];
    }

    /**
     * Returns the folder a site lives in on its domain, in the form that the paths Laravel reads
     * from a request start with. It is `/fr` for a site at example.com/fr/, and '' for one
     * at the root of its domain (or of the folder the app is installed in).
     */
    public static function folder(?string $site): string
    {
        $url = $site !== null ? Site::get($site)?->absoluteUrl() : null;
        $path = rtrim((string) parse_url((string) $url, PHP_URL_PATH), '/');
        $base = rtrim(request()->getBasePath(), '/');

        return $base !== '' && str_starts_with($path.'/', $base.'/') ? substr($path, strlen($base)) : $path;
    }

    /**
     * Returns a path as requested (`/fr/a-propos`) as it is within $site (`/a-propos`), which is how
     * Statamic's uri() and the addon's redirects and 404 log write it. It returns null when the path
     * doesn't start with the site's folder.
     */
    public static function within(string $path, ?string $site): ?string
    {
        $folder = self::folder($site);
        $path = '/'.ltrim($path, '/');

        if ($folder === '') {
            return $path;
        }

        return $path === $folder || str_starts_with($path, $folder.'/') ? '/'.ltrim(substr($path, strlen($folder)), '/') : null;
    }

    /**
     * Maps each site handle to its name, for a select.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return Site::all()->mapWithKeys(fn ($site) => [$site->handle() => (string) $site->name()])->all();
    }

    /**
     * Determines whether the addresses built for this request may be cached and served
     * to every request. A site whose URL is relative (`url: '/'`) takes its
     * domain from the request's Host header, which a client can set to anything.
     * If that were cached after a save, one request with `Host: evil.test` would give
     * every visitor a sitemap of evil.test addresses until the next save. So,
     * with such a site, only a request on a host the install names (an
     * absolute site URL's, or else APP_URL's) is cached, and others are built afresh
     * for that request alone. When every site's URL is absolute, nothing depends
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
     * Runs $work with $site as Statamic's current site (a null site leaves it as it is), then
     * puts back what was there, so the site is still worked out from the request
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
