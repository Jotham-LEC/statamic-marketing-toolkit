<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Statamic\Facades\Site;
use Statamic\Facades\User;

/**
 * Handles the front-end toolbar's links into the control panel on a multi-site
 * install. It selects the page's site and then opens the screen, so the screen
 * shows that site's report, redirects and settings. Statamic's own select-site
 * route only returns to where the user came from.
 */
final class ToolbarController
{
    public function go(Request $request): RedirectResponse
    {
        $site = Site::get($request->string('site')->toString());
        $to = $request->string('to')->toString();
        $cp = '/'.trim((string) config('statamic.cp.route', 'cp'), '/');

        // The target must be a control panel path and never another host (`//evil.test`, `/\evil.test`).
        abort_unless(($to === $cp || str_starts_with($to, $cp.'/')) && ! preg_match('#[\\\\\x00-\x1F]|//#', $to), 404);
        abort_unless($site && User::current()?->can('view', $site), 403);

        Site::setSelected($site->handle());

        return redirect($to);
    }
}
