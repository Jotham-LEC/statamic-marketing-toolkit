<?php

namespace JothamLec\Seo\Actions;

use JothamLec\Seo\NotFound\MissingPath;
use Statamic\Actions\Action;

/**
 * From a row of the 404 log: open a new redirect with the missing path as its source.
 */
class CreateRedirect extends Action
{
    protected $confirm = false;

    public static function title()
    {
        return __('Create redirect');
    }

    public function visibleTo($item)
    {
        return $item instanceof MissingPath;
    }

    public function visibleToBulk($items)
    {
        return false;
    }

    public function authorize($user, $item)
    {
        return $user->can('manage seo redirects');
    }

    public function redirect($items, $values)
    {
        return cp_route('seo.redirects.create', ['source' => $items->first()->path]);
    }

    public function run($items, $values)
    {
        //
    }
}
