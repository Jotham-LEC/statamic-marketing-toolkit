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
 * The front-end toolbar's endpoint, at /!/marketing-toolkit/toolbar: what
 * the toolbar shows about one page, for the signed-in user. Nothing it
 * answers is cached anywhere. Besides reading, it can clear this one page's
 * static cache, for whoever may use the cache utility.
 */
class ToolbarController
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

        // Only the site's own addresses are asked about.
        if ($site === null || Validator::make($request->query(), ['status' => ['nullable', 'integer', 'between:100,599']])->fails()) {
            return $this->private(response()->json(['message' => 'This address isn’t on this site.'], 422));
        }

        $status = $request->filled('status') ? $request->integer('status') : null;

        // In the user's control panel language, as the control panel would be.
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

        // Without its query string: every copy of the page goes.
        $cacher->invalidateUrls([strtok($url, '?')]);

        return $this->private(response()->noContent());
    }

    /**
     * The page's address, without its fragment: a web address, or null.
     */
    private function pageUrl(Request $request): ?string
    {
        $url = $request->input('url');

        return is_string($url) && strlen($url) <= 2048 && preg_match('#^https?://[^/\\s]+#i', $url) ? (string) strtok($url, '#') : null;
    }

    /**
     * No user who gets the toolbar: the marker cookie goes, so the next page loads nothing.
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
