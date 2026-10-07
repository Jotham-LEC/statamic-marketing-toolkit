<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Statamic\Facades\Site;
use Statamic\Facades\User;

/**
 * Where the front-end toolbar's links into the control panel go first on a
 * multi-site install: the page's site is selected, then the screen opens, so
 * it shows that site's report, redirects and settings. Statamic's own
 * select-site route only returns to where the user came from.
 */
class ToolbarController
{
    public function go(Request $request): RedirectResponse
    {
        $site = Site::get($request->string('site')->toString());
        $to = $request->string('to')->toString();
        $cp = '/'.trim((string) config('statamic.cp.route', 'cp'), '/');

        // A control panel path, and only that: never another host (`//evil.test`, `/\evil.test`).
        abort_unless(($to === $cp || str_starts_with($to, $cp.'/')) && ! preg_match('#[\\\\\x00-\x1F]|//#', $to), 404);
        abort_unless($site && User::current()?->can('view', $site), 403);

        Site::setSelected($site->handle());

        return redirect($to);
    }
}
