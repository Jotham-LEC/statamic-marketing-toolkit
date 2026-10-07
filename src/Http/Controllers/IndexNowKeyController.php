<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Response;
use JothamLec\MarketingToolkit\IndexNow\IndexNow;
use Statamic\Exceptions\NotFoundHttpException;

/**
 * /{key}.txt: the file that proves to IndexNow the site owns its key.
 */
class IndexNowKeyController
{
    public function __invoke(IndexNow $indexNow): Response
    {
        throw_unless(config('marketing-toolkit.indexnow.enabled'), NotFoundHttpException::class);

        return new Response($indexNow->key(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
