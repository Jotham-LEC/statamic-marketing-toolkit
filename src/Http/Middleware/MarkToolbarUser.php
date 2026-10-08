<?php

namespace JothamLec\MarketingToolkit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use JothamLec\MarketingToolkit\Toolbar\Toolbar;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * On every control panel request, this middleware keeps the toolbar's marker cookie in step with the
 * user. The cookie is set while they get the toolbar, and removed once they don't (because the toolbar
 * was switched off, or they lost `access cp`). This covers sessions from before the addon had a toolbar,
 * and "remember me" sign-ins, which skip the sign-in form. The check runs after the request, so saving
 * the preference takes effect at once.
 */
final class MarkToolbarUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $wants = Toolbar::wants(User::current());
        $marked = $request->cookies->get(Toolbar::COOKIE) === '1';

        if ($wants && ! $marked) {
            Cookie::queue(Toolbar::cookie());
        } elseif (! $wants && $request->cookies->has(Toolbar::COOKIE)) {
            Cookie::queue(Toolbar::forget());
        }

        return $response;
    }
}
