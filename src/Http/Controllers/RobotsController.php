<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Response;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Exceptions\NotFoundHttpException;

/**
 * /robots.txt from the Crawlers tab of Marketing settings. Outside production it shuts every
 * crawler out. A real public/robots.txt wins: the web server serves it first.
 */
final class RobotsController
{
    public function __invoke(SiteSeo $seo): Response
    {
        throw_unless(Features::on('robots_txt'), NotFoundHttpException::class);

        return new Response($seo->robotsTxt(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
