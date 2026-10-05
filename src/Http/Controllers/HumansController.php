<?php

namespace JothamLec\Seo\Http\Controllers;

use Illuminate\Http\Response;
use JothamLec\Seo\SiteSeo;

/**
 * /humans.txt (humanstxt.org): the team and thanks, typed into the SEO
 * global set. Not found until someone fills it in.
 */
class HumansController
{
    public function __invoke(): Response
    {
        $text = app(SiteSeo::class)->humansTxt();

        abort_if($text === null, 404);

        return new Response(rtrim($text)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
