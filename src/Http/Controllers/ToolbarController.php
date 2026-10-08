<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use JothamLec\MarketingToolkit\Support\Sites;
use JothamLec\MarketingToolkit\Toolbar\PageData;
use JothamLec\MarketingToolkit\Toolbar\Toolbar;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\StaticCaching\Cacher;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Serves the front-end toolbar's endpoint at /!/marketing-toolkit/toolbar, which
 * returns what the toolbar shows about one page for the signed-in user. Nothing it
 * answers is cached anywhere. Besides reading, it can clear this one page's
 * static cache for whoever may use the cache utility.
 */
final class ToolbarController
{
    public function show(Request $request): JsonResponse
    {
        if (! Toolbar::enabled()) {
            return $this->private(response()->json(['message' => 'Not found.'], 404))->withCookie(Toolbar::forget());
        }

        $user = User::current();

        if (! Toolbar::wants($user)) {
            return $this->signedOut();
        }

        $url = $this->pageUrl($request);
        $site = $url === null ? null : Site::findByUrl($url);

        // The endpoint only answers for addresses on the site's own domains.
        if ($site === null || Validator::make($request->query(), ['status' => ['nullable', 'integer', 'between:100,599']])->fails()) {
            return $this->private(response()->json(['message' => 'This address isn’t on this site.'], 422));
        }

        $status = $request->filled('status') ? $request->integer('status') : null;

        // The answer uses the user's control panel language, as the control panel would.
        app()->setLocale($user->preferredLocale());

        $data = Sites::as($site->handle(), fn () => (new PageData($user, $url, $status))->toArray());

        return $this->private(response()->json($data));
    }

    /**
     * Clears this page's copy from the static cache, so the next visit stores
     * a fresh one. The route asks for the cache utility's permission.
     */
    public function refreshCache(Request $request, Cacher $cacher): Response
    {
        abort_unless(Toolbar::enabled() && config('statamic.static_caching.strategy'), 404);

        $url = $this->pageUrl($request);
        abort_if($url === null || Site::findByUrl($url) === null, 422);

        // The query string is dropped, so that every cached copy of the page is cleared.
        $cacher->invalidateUrls([strtok($url, '?')]);

        return $this->private(response()->noContent());
    }

    /**
     * Returns the page's address without its fragment, or null if it isn't a web address.
     */
    private function pageUrl(Request $request): ?string
    {
        $url = $request->input('url');

        return is_string($url) && strlen($url) <= 2048 && preg_match('#^https?://[^/\\s]+#i', $url) ? (string) strtok($url, '#') : null;
    }

    /**
     * Answers when no user gets the toolbar, and forgets the marker cookie so the next page loads nothing.
     */
    private function signedOut(): JsonResponse
    {
        return $this->private(response()->json(['message' => __('marketing-toolkit::toolbar.ui.signed_out')], 401))->withCookie(Toolbar::forget());
    }

    /**
     * @template T of BaseResponse
     *
     * @param  T  $response
     * @return T
     */
    private function private(BaseResponse $response): BaseResponse
    {
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
