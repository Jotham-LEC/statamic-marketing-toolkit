<?php

namespace JothamLec\MarketingToolkit\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JothamLec\MarketingToolkit\NotFound\Recorder;
use JothamLec\MarketingToolkit\Redirects\Matcher;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Sites;
use JothamLec\MarketingToolkit\Support\StatamicRoutes;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Symfony\Component\HttpFoundation\Response;

/**
 * For a request the site answers with a 404: send it on if a redirect rule
 * matches, else count it in the 404 log. A page that exists always wins over
 * a rule, so a page that comes back takes its address back. The bookkeeping
 * (hit counts, the log) runs in terminate(), after the response has gone out.
 * Not Laravel's defer(): it runs only where the app has Laravel 11's
 * InvokeDeferredCallbacks middleware, which a site keeping the older
 * app/Http/Kernel.php may lack, and the log would then stay empty.
 */
final class HandleMissing
{
    public function __construct(private Matcher $matcher, private Recorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Features::on('redirects') && ! Features::on('not_found')) {
            return $next($request);
        }

        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! in_array($request->getMethod(), ['GET', 'HEAD'], true) || StatamicRoutes::owns($request->getPathInfo())) {
            return $response;
        }

        // A stored copy would be served without coming through here: a redirect
        // added later would never apply, and the log would count one visit.
        $response->headers->set('X-Statamic-Uncacheable', 'true');

        $site = Site::current();
        // As requested, from the domain's root: the matcher takes the site's folder off itself.
        $path = '/'.trim($request->decodedPath(), '/');
        // The lookup reads the rules table. Missing (the addon installed, `migrate` not yet run) or
        // failing, it is reported and the address answers its 404 as before, not a 500.
        $rule = Features::on('redirects') ? rescue(fn () => $this->matcher->match($path, (string) $request->getQueryString(), $site->handle()), null) : null;

        // A rule back to the address asked for would loop; the address is simply missing.
        if ($rule && $rule['target'] !== null && str_starts_with($rule['target'], '/') && Redirect::normalize(self::fromRoot($rule['target'], $site)) === $path) {
            $rule = null;
        }

        if ($rule === null) {
            $request->attributes->set('mt.record_missing', $this->recorder->shouldRecord($request));

            return $response;
        }

        $request->attributes->set('mt.redirect_hit', $rule['id']);

        if ($rule['status'] === 410 || $rule['target'] === null) {
            // The site's own error page, saying the page is gone for good.
            return $response->setStatusCode(410);
        }

        // Built by hand: Laravel's redirect() would trim a trailing slash the site wants.
        return new RedirectResponse(self::location($rule['target'], $site), $rule['status']);
    }

    /**
     * A target path as requested, from the domain's root. Targets are paths
     * within the site, as sources are (see Matcher): `/qui-sommes-nous` on a
     * site at example.com/fr/ is `/fr/qui-sommes-nous`. One that already starts
     * with the site's folder was typed from the domain's root, as one had to
     * before, and is kept as it is.
     */
    private static function fromRoot(string $target, SiteObject $site): string
    {
        $folder = Sites::folder($site->handle());

        return $folder !== '' && preg_match('#^'.preg_quote($folder, '#').'([/?\#]|$)#', $target) ? $target : $folder.$target;
    }

    /**
     * Where a rule sends the visitor: another site's address as typed; a path
     * on the site's own address, never the request's Host header, which a
     * client can set to anything. A site whose URL is relative (`url: '/'`)
     * has no address but the request's, so it gets the path alone, which the
     * browser takes on the host it asked.
     */
    private static function location(string $target, SiteObject $site): string
    {
        if (! str_starts_with($target, '/')) {
            return $target;
        }

        // The site's address without its folder: the domain, and the folder the app is installed in.
        $root = rtrim($site->absoluteUrl(), '/');
        $root = substr($root, 0, strlen($root) - strlen(Sites::folder($site->handle())));
        $location = $root.self::fromRoot($target, $site);

        if (preg_match('#^https?://#i', $site->url())) {
            return $location;
        }

        // Never `//host`: a browser would read a path that starts so as another site.
        return '/'.ltrim((string) preg_replace('#^https?://[^/]*#i', '', $location), '/');
    }

    public function terminate(Request $request, Response $response): void
    {
        // Bookkeeping after the response has gone out: a failure is reported, not thrown.
        if ($id = $request->attributes->get('mt.redirect_hit')) {
            rescue(fn () => Redirect::query()->whereKey($id)->increment('hits', 1, ['last_hit_at' => now()]));
        }

        if ($request->attributes->get('mt.record_missing')) {
            rescue(fn () => $this->recorder->record($request));
        }
    }
}
