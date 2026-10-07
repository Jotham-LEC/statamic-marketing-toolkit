<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JothamLec\MarketingToolkit\Toolbar\Toolbar;
use Statamic\Facades\User;

/**
 * The front-end toolbar's endpoint: what the toolbar shows about one page,
 * for the signed-in user. It only reads; the one thing it changes is this
 * page's static cache, for whoever may use the cache utility.
 */
class ToolbarController
{
    public function show(Request $request): JsonResponse
    {
        abort_unless(Toolbar::enabled(), 404);

        if (! Toolbar::wants(User::current())) {
            return $this->signedOut();
        }

        return $this->private(response()->json([]));
    }

    public function refreshCache(Request $request): Response
    {
        abort_unless(Toolbar::enabled(), 404);

        return $this->private(response()->noContent());
    }

    /**
     * No user who gets the toolbar: the marker cookie goes, so the next page loads nothing.
     */
    private function signedOut(): JsonResponse
    {
        return $this->private(response()->json(['message' => __('marketing-toolkit::toolbar.signed_out')], 401))->withCookie(Toolbar::forget());
    }

    /**
     * @template T of \Symfony\Component\HttpFoundation\Response
     *
     * @param  T  $response
     * @return T
     */
    private function private($response)
    {
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
