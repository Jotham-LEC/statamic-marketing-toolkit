<?php

namespace JothamLec\MarketingToolkit\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JothamLec\MarketingToolkit\NotFound\Recorder;
use JothamLec\MarketingToolkit\Redirects\Matcher;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use JothamLec\MarketingToolkit\Support\StatamicRoutes;
use Statamic\Facades\Site;
use Symfony\Component\HttpFoundation\Response;

/**
 * For a request the site answers with a 404: send it on if a redirect rule
 * matches, else count it in the 404 log. A page that exists always wins over
 * a rule, so a page that comes back takes its address back. The bookkeeping
 * (hit counts, the log) runs in terminate(), after the response has gone out.
 */
class HandleMissing
{
    public function __construct(private Matcher $matcher, private Recorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! in_array($request->getMethod(), ['GET', 'HEAD'], true) || StatamicRoutes::owns($request->getPathInfo())) {
            return $response;
        }

        // A stored copy would be served without coming through here: a redirect
        // added later would never apply, and the log would count one visit.
        if (config('seo.redirects.enabled') || config('seo.not_found.enabled')) {
            $response->headers->set('X-Statamic-Uncacheable', 'true');
        }

        $path = $this->recorder->path($request);
        $rule = config('seo.redirects.enabled') ? $this->matcher->match($path, (string) $request->getQueryString(), Site::current()->handle()) : null;

        // A rule back to the address asked for would loop; the address is simply missing.
        if ($rule && $rule['target'] !== null && str_starts_with($rule['target'], '/') && Redirect::normalize($rule['target']) === $path) {
            $rule = null;
        }

        if ($rule === null) {
            $request->attributes->set('seo.record_missing', $this->recorder->shouldRecord($request));

            return $response;
        }

        $request->attributes->set('seo.redirect_hit', $rule['id']);

        if ($rule['status'] === 410 || $rule['target'] === null) {
            // The site's own error page, saying the page is gone for good.
            return $response->setStatusCode(410);
        }

        // Built by hand: Laravel's redirect() would trim a trailing slash the site wants. On the
        // site's own address, not the request's Host header, which a client can set to anything.
        $target = str_starts_with($rule['target'], '/') ? rtrim(Site::current()->absoluteUrl(), '/').$rule['target'] : $rule['target'];

        return new RedirectResponse($target, $rule['status']);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($id = $request->attributes->get('seo.redirect_hit')) {
            Redirect::query()->whereKey($id)->increment('hits', 1, ['last_hit_at' => now()]);
        }

        if ($request->attributes->get('seo.record_missing')) {
            $this->recorder->record($request);
        }
    }
}
