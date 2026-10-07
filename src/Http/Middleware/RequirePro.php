<?php

namespace JothamLec\MarketingToolkit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use JothamLec\MarketingToolkit\Support\Edition;
use JothamLec\MarketingToolkit\Support\Sites;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control panel routes of Pro features. In the free edition a screen shows
 * what Pro adds (someone followed a bookmark or the docs there); anything
 * else is not found.
 */
class RequirePro
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Edition::pro()) {
            return $next($request);
        }

        abort_unless($request->isMethod('GET') && ! $request->expectsJson(), 404);

        return Inertia::render('seo::ProOnly', [
            'upgradeUrl' => Edition::marketplaceUrl(),
            'severalSites' => Sites::installed(),
            'overviewUrl' => cp_route('seo.index'),
        ])->toResponse($request);
    }
}
