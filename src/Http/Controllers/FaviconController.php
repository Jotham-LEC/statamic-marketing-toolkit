<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JothamLec\MarketingToolkit\Favicons\Favicons;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Exceptions\NotFoundHttpException;

/**
 * Serves /favicon.ico, /favicon.svg, /apple-touch-icon.png, /icon-192.png,
 * /icon-512.png and /site.webmanifest, made from the brand's icon. A file of the
 * same name in public/ wins, because the web server serves it first.
 */
final class FaviconController
{
    public function __invoke(Request $request, Favicons $favicons): Response
    {
        $name = ltrim($request->getPathInfo(), '/');
        throw_unless(Features::on('favicons'), NotFoundHttpException::class);

        $bytes = $favicons->file($name);
        throw_if($bytes === null, NotFoundHttpException::class);

        return response($bytes)->withHeaders(array_filter([
            'Content-Type' => Favicons::FILES[$name],
            // Browsers ask for /favicon.ico without the version, so they keep it for a day and then check again.
            'Cache-Control' => 'public, max-age=86400',
            // This policy stops an SVG that is opened on its own from running any script.
            'Content-Security-Policy' => $name === 'favicon.svg' ? "default-src 'none'; style-src 'unsafe-inline'" : null,
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }
}
