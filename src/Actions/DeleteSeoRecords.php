<?php

namespace JothamLec\Seo\Actions;

use JothamLec\Seo\NotFound\MissingPath;
use JothamLec\Seo\Redirects\Redirect;
use Statamic\Actions\Action;

/**
 * Deletes redirects, or rows of the 404 log, from their listings.
 */
class DeleteSeoRecords extends Action
{
    protected $dangerous = true;

    public static function title()
    {
        return __('Delete');
    }

    public function visibleTo($item)
    {
        return $item instanceof Redirect || $item instanceof MissingPath;
    }

    public function authorize($user, $item)
    {
        return $user->can('manage seo redirects');
    }

    public function buttonText()
    {
        /** @translation */
        return 'Delete|Delete :count items?';
    }

    public function confirmationText()
    {
        /** @translation */
        return 'Delete this?|Delete these :count items?';
    }

    public function run($items, $values)
    {
        // One by one, so each redirect's model events clear the cached rules.
        $items->each->delete();
    }
}
