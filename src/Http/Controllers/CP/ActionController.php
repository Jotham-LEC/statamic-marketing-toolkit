<?php

namespace JothamLec\Seo\Http\Controllers\CP;

use JothamLec\Seo\NotFound\MissingPath;
use JothamLec\Seo\Redirects\Redirect;
use Statamic\Http\Controllers\CP\ActionController as StatamicActionController;

/**
 * Runs Statamic actions on the redirects and 404 listings. The listing says
 * which it is in the action context.
 */
class ActionController extends StatamicActionController
{
    protected function getSelectedItems($items, $context)
    {
        $model = ($context['type'] ?? null) === '404s' ? MissingPath::class : Redirect::class;

        return $model::query()->whereIn('id', $items->all())->get();
    }
}
