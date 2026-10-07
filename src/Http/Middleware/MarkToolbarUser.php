<?php

namespace JothamLec\MarketingToolkit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use JothamLec\MarketingToolkit\Toolbar\Toolbar;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * On every control panel request, the toolbar's marker cookie follows the
 * user: set while they get the toolbar, removed once they don't (they hid it
 * under Preferences, or lost `access cp`). This covers sessions from before
 * the addon had a toolbar, and "remember me" sign-ins, which skip the
 * sign-in form. Checked after the request, so saving the preference counts at once.
 */
class MarkToolbarUser
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
