<?php

namespace JothamLec\Seo\Http\Controllers;

use Illuminate\Http\Response;
use JothamLec\Seo\SiteSeo;

/**
 * /robots.txt from the SEO global set. Outside production it shuts every
 * crawler out. A real public/robots.txt always wins: the route is not
 * registered when one exists.
 */
class RobotsController
{
    public function __invoke(): Response
    {
        return new Response(app(SiteSeo::class)->robotsTxt(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
