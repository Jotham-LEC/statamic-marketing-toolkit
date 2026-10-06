<?php

namespace JothamLec\Seo\Reports;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use JothamLec\Seo\Redirects\Matcher;
use Statamic\Facades\Asset;
use Statamic\Facades\Data;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Whether a path on this site leads somewhere, without fetching it: a page
 * Statamic knows, a file in public/, an asset, a route the app registers
 * (other than Statamic's catch-all), or a redirect rule.
 */
class LinkChecker
{
    /** @var array<string, string> path => ok, redirect or broken */
    private array $known = [];

    public function __construct(private Router $router, private Matcher $redirects) {}

    /**
     * @return 'ok'|'redirect'|'broken'
     */
    public function check(string $path): string
    {
        $path = '/'.trim(rawurldecode($path), '/');

        return $this->known[$path] ??= $this->resolve($path);
    }

    private function resolve(string $path): string
    {
        if (Data::findByUri($path) || Data::findByUri($path.'/')) {
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

        return $this->redirects->match($path) ? 'redirect' : 'broken';
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
