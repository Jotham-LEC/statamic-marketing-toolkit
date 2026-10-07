<?php

namespace JothamLec\MarketingToolkit\UpdateScripts;

use JothamLec\MarketingToolkit\Commands\Install;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\UpdateScripts\UpdateScript;

/**
 * After each update, the SEO & brand blueprint gets the fields the new
 * version brings, in the tabs the site kept, as `seo:install` would add
 * them. Statamic runs it on `composer update` (or `php please updates:run`);
 * commit the blueprint it changes.
 */
class AddNewBrandFields extends UpdateScript
{
    public function shouldUpdate($newVersion, $oldVersion)
    {
        return Blueprint::find('globals.'.config('seo.global')) !== null;
    }

    public function update()
    {
        $blueprint = Blueprint::find('globals.'.config('seo.global'));
        $container = Install::containerOf($blueprint) ?? AssetContainer::all()->first()?->handle();

        if ($container === null) {
            return;
        }

        $added = Install::addMissingFields($blueprint, $container);

        if ($added !== []) {
            $this->console()->info('Marketing Toolkit added to SEO & brand: '.implode(', ', $added).'.');
        }
    }
}
