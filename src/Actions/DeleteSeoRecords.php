<?php

namespace JothamLec\MarketingToolkit\Actions;

use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use JothamLec\MarketingToolkit\Support\Permissions;
use Statamic\Actions\Action;

/**
 * Deletes redirects, or rows of the 404 log, from their listings.
 */
final class DeleteSeoRecords extends Action
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
        return $user->can(Permissions::REDIRECTS);
    }

    public function buttonText()
    {
        /** @translation */
        return 'Delete|Delete :count items?';
    }

    public function confirmationText()
    {
        // The CP picks the singular or plural part.
        return __('marketing-toolkit::cp.actions.delete_confirm');
    }

    public function run($items, $values)
    {
        // One by one, so each redirect's model events clear the cached rules.
        $items->each->delete();
    }
}
