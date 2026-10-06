<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JothamLec\MarketingToolkit\Favicons\Favicons;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Exceptions\NotFoundHttpException;

/**
 * /favicon.ico, /favicon.svg, /apple-touch-icon.png, /icon-192.png,
 * /icon-512.png and /site.webmanifest, made from the brand's icon. A file in
 * public/ of the same name wins: the route isn't registered.
 */
class FaviconController
{
    public function __invoke(Request $request, Favicons $favicons): Response
    {
        $name = ltrim($request->getPathInfo(), '/');
        // Off (Tools → SEO → Features) after the routes were cached; Free: the default site's domain only.
        throw_unless(config('seo.favicons.enabled') && Sites::served(), NotFoundHttpException::class);

        $bytes = $favicons->file($name);
        throw_if($bytes === null, NotFoundHttpException::class);

        return new Response($bytes, 200, array_filter([
            'Content-Type' => Favicons::FILES[$name],
            // Browsers ask for /favicon.ico without the version: a day, then they check again.
            'Cache-Control' => 'public, max-age=86400',
            // An SVG opened on its own runs no script.
            'Content-Security-Policy' => $name === 'favicon.svg' ? "default-src 'none'; style-src 'unsafe-inline'" : null,
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }
}
