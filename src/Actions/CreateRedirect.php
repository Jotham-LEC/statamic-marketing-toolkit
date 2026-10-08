<?php

namespace JothamLec\MarketingToolkit\Actions;

use Illuminate\Support\Collection;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Support\Permissions;
use Statamic\Actions\Action;
use Statamic\Contracts\Auth\User;

/**
 * Opens a new redirect from a row of the 404 log, with the missing path as its source.
 */
final class CreateRedirect extends Action
{
    /** @var bool */
    protected $confirm = false;

    /** @return string */
    public static function title()
    {
        return __('marketing-toolkit::cp.redirects.create');
    }

    /**
     * @param  mixed  $item
     * @return bool
     */
    public function visibleTo($item)
    {
        return $item instanceof MissingPath;
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return bool
     */
    public function visibleToBulk($items)
    {
        return false;
    }

    /**
     * @param  User  $user
     * @param  mixed  $item
     * @return bool
     */
    public function authorize($user, $item)
    {
        return $user->can(Permissions::REDIRECTS);
    }

    /**
     * @param  Collection<int, MissingPath>  $items
     * @param  array<string, mixed>  $values
     * @return string
     */
    public function redirect($items, $values)
    {
        // The new redirect opens on the site where the visitor hit the missing path.
        return cp_route('mt.redirects.create', array_filter(['source' => $items->first()->path, 'site' => $items->first()->site]));
    }

    /**
     * @param  Collection<int, MissingPath>  $items
     * @param  array<string, mixed>  $values
     * @return void
     */
    public function run($items, $values)
    {
        //
    }
}
