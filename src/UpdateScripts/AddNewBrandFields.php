<?php

namespace JothamLec\MarketingToolkit\UpdateScripts;

use JothamLec\MarketingToolkit\Commands\Install;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\UpdateScripts\UpdateScript;

/**
 * After each update, the Brand and Marketing settings blueprints get the
 * fields the new version brings, in the tabs the site kept, as `mt:install`
 * would add them. Statamic runs it on `composer update` (or `php please
 * updates:run`); commit the blueprints it changes.
 */
class AddNewBrandFields extends UpdateScript
{
    public function shouldUpdate($newVersion, $oldVersion)
    {
        return Blueprint::find('globals.'.config('marketing-toolkit.global')) !== null;
    }

    public function update()
    {
        $container = Install::containerOf(Blueprint::find('globals.'.config('marketing-toolkit.global'))) ?? AssetContainer::all()->first()?->handle();

        if ($container === null) {
            return;
        }

        foreach (Install::SETS as $key => $set) {
            $blueprint = Blueprint::find('globals.'.config('marketing-toolkit.'.$key));
            $added = $blueprint ? Install::addMissingFields($blueprint, $container, $set['file']) : [];

            if ($added !== []) {
                $this->console()->info("Marketing Toolkit added to {$set['title']}: ".implode(', ', $added).'.');
            }
        }
    }
}
