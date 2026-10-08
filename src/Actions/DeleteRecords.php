<?php

namespace JothamLec\MarketingToolkit\Actions;

use Illuminate\Support\Collection;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use JothamLec\MarketingToolkit\Support\Permissions;
use Statamic\Actions\Action;
use Statamic\Contracts\Auth\User;

/**
 * Deletes redirects, or rows of the 404 log, from their listings.
 */
final class DeleteRecords extends Action
{
    /** @var bool */
    protected $dangerous = true;

    /** @return string */
    public static function title()
    {
        return __('Delete');
    }

    /**
     * @param  mixed  $item
     * @return bool
     */
    public function visibleTo($item)
    {
        return $item instanceof Redirect || $item instanceof MissingPath;
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

    /** @return string */
    public function buttonText()
    {
        /** @translation */
        return 'Delete|Delete :count items?';
    }

    /** @return string */
    public function confirmationText()
    {
        // The CP picks the singular or plural part.
        return __('marketing-toolkit::cp.actions.delete_confirm');
    }

    /**
     * @param  Collection<int, Redirect|MissingPath>  $items
     * @param  array<string, mixed>  $values
     * @return void
     */
    public function run($items, $values)
    {
        // One by one, so each redirect's model events clear the cached rules.
        $items->each->delete();
    }
}
