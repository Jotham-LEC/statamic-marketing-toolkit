<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Response;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;

/**
 * /robots.txt from the SEO global set. Outside production it shuts every
 * crawler out. A real public/robots.txt always wins: the route is not
 * registered when one exists.
 */
class RobotsController
{
    public function __invoke(): Response
    {
        // Off (Tools → SEO → Features) after the routes were cached; Free: the default site's domain only.
        abort_unless(config('seo.robots_txt') && Sites::served(), 404);

        return new Response(app(SiteSeo::class)->robotsTxt(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
