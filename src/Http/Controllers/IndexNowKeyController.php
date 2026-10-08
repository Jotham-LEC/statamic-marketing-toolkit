<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Response;
use JothamLec\MarketingToolkit\IndexNow\IndexNow;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Exceptions\NotFoundHttpException;

/**
 * Serves /{key}.txt, the file that proves to IndexNow that the site owns its key.
 */
final class IndexNowKeyController
{
    public function __invoke(IndexNow $indexNow): Response
    {
        throw_unless(Features::on('indexnow'), NotFoundHttpException::class);

        return response($indexNow->key())->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
