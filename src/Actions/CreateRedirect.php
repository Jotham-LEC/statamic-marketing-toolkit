<?php

namespace JothamLec\MarketingToolkit\Actions;

use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Support\Permissions;
use Statamic\Actions\Action;

/**
 * From a row of the 404 log: open a new redirect with the missing path as its source.
 */
class CreateRedirect extends Action
{
    protected $confirm = false;

    public static function title()
    {
        return __('marketing-toolkit::cp.redirects.create');
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
        return $user->can(Permissions::REDIRECTS);
    }

    public function redirect($items, $values)
    {
        // On the site the visitor missed it on.
        return cp_route('mt.redirects.create', array_filter(['source' => $items->first()->path, 'site' => $items->first()->site]));
    }

    public function run($items, $values)
    {
        //
    }
}
