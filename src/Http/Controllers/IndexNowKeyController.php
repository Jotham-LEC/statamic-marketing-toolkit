<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Response;
use JothamLec\MarketingToolkit\IndexNow\IndexNow;

/**
 * /{key}.txt: the file that proves to IndexNow the site owns its key.
 */
class IndexNowKeyController
{
    public function __invoke(IndexNow $indexNow): Response
    {
        return new Response($indexNow->key(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
