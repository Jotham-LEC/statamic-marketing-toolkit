<?php

namespace JothamLec\Seo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JothamLec\Seo\Support\Edition;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control panel routes of Pro features: not found in the free edition.
 */
class RequirePro
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Edition::pro(), 404);

        return $next($request);
    }
}
