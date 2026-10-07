<?php

namespace JothamLec\MarketingToolkit\Reports;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use JothamLec\MarketingToolkit\Redirects\Matcher;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\Asset;
use Statamic\Facades\Data;
use Statamic\Facades\Site;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Whether a path on the current site leads somewhere, without fetching it:
 * a page Statamic knows there, a file in public/, an asset, a route the app
 * registers (other than Statamic's catch-all), or a redirect rule that
 * applies there.
 */
class LinkChecker
{
    /** @var array<string, string> site and path => ok, redirect or broken */
    private array $known = [];

    public function __construct(private Router $router, private Matcher $redirects) {}

    /**
     * @return 'ok'|'redirect'|'broken'
     */
    public function check(string $path): string
    {
        $path = '/'.trim(rawurldecode($path), '/');
        $site = Site::current()->handle();

        return $this->known[$site.' '.$path] ??= $this->resolve($path, $site);
    }

    private function resolve(string $path, string $site): string
    {
        // A page's link names its path from the domain's root (/fr/a-propos); Statamic
        // finds the page by its address within the site (/a-propos).
        $within = Sites::within($path, $site) ?? $path;

        if (Data::findByUri($within, $site) || Data::findByUri($within.'/', $site)) {
            return 'ok';
        }

        // Only inside public/: a `..` in a link would ask about files beyond it.
        if ($path !== '/' && ! in_array('..', explode('/', $path), true) && is_file(public_path(ltrim($path, '/')))) {
            return 'ok';
        }

        if (Asset::findByUrl($path)) {
            return 'ok';
        }

        if ($this->isAppRoute($path)) {
            return 'ok';
        }

        return $this->redirects->match($path, '', $site) ? 'redirect' : 'broken';
    }

    private function isAppRoute(string $path): bool
    {
        try {
            $route = $this->router->getRoutes()->match(Request::create($path));
        } catch (HttpException) {
            return false;
        }

        // Statamic's front-end catch-all matches anything; it says nothing about the path.
        return ! str_contains($route->uri(), '{segments');
    }
}
