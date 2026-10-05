<?php

namespace JothamLec\Seo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One address per page: with `seo.trailing_slash` set to 'add' or 'remove',
 * a GET or HEAD for the other form is sent there with a 301, query string
 * kept. Files (anything with an extension in its last segment), the control
 * panel and Statamic's action and Glide routes are left alone.
 */
class TrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = config('seo.trailing_slash');
        $path = '/'.ltrim($request->getPathInfo(), '/');

        if (! in_array($mode, ['add', 'remove'], true) || ! $request->isMethodSafe() || $path === '/' || $this->skipped($path)) {
            return $next($request);
        }

        $hasSlash = str_ends_with($path, '/');

        if ($mode === 'remove' && $hasSlash) {
            return $this->redirect($request, rtrim($path, '/'));
        }

        if ($mode === 'add' && ! $hasSlash) {
            return $this->redirect($request, $path.'/');
        }

        return $next($request);
    }

    private function skipped(string $path): bool
    {
        $prefixes = array_map(fn ($prefix) => '/'.trim((string) $prefix, '/'), [
            config('statamic.cp.route', 'cp'),
            config('statamic.routes.action', '!'),
            config('statamic.assets.image_manipulation.route', 'img'),
        ]);

        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return str_contains((string) basename(rtrim($path, '/')), '.');
    }

    private function redirect(Request $request, string $path): Response
    {
        $query = $request->getQueryString();

        return redirect()->to($request->getSchemeAndHttpHost().$path.($query ? '?'.$query : ''), 301);
    }
}
