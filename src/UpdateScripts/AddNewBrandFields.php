<?php

namespace JothamLec\MarketingToolkit\UpdateScripts;

use JothamLec\MarketingToolkit\Commands\Install;
use JothamLec\MarketingToolkit\Support\Version;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\UpdateScripts\UpdateScript;

/**
 * An update adds to the Brand and Marketing settings blueprints the fields
 * the new version brings, in the tabs the site kept, as `mt:install` would
 * add them. Only those: a field the site removed stays removed, through every
 * later update. Updating from before 0.20 (when this script came, and fields
 * were added by hand), every field the blueprints lack is added. Statamic
 * runs it on `composer update` (or `php please updates:run`); commit the
 * blueprints it changes.
 */
class AddNewBrandFields extends UpdateScript
{
    /**
     * The fields each version from 0.20 brought, by that version. A field
     * added to resources/install must be listed here, or no update adds it.
     */
    public const array FIELDS = [
        '0.21.0' => [
            'feature_sitemap', 'feature_robots_txt', 'feature_llms_txt', 'feature_ads_txt', 'feature_hreflang', 'feature_indexnow',
            'feature_redirects', 'feature_automatic_redirects', 'feature_not_found', 'feature_reports', 'feature_share_cards',
            'feature_favicons', 'feature_tracking', 'feature_leads',
        ],
    ];

    /** @var list<string>|null the fields this update brings, as shouldUpdate() found them */
    private ?array $fields = [];

    public function shouldUpdate($newVersion, $oldVersion)
    {
        $this->fields = self::since((string) $oldVersion, (string) $newVersion);

        return $this->fields !== [] && Blueprint::find('globals.'.config('marketing-toolkit.global')) !== null;
    }

    public function update()
    {
        $container = Install::containerOf(Blueprint::find('globals.'.config('marketing-toolkit.global'))) ?? AssetContainer::all()->first()?->handle();

        if ($container === null || $this->fields === []) {
            return;
        }

        foreach (Install::SETS as $key => $set) {
            $blueprint = Blueprint::find('globals.'.config('marketing-toolkit.'.$key));
            $added = $blueprint ? Install::addMissingFields($blueprint, $container, $set['file'], $this->fields) : [];

            if ($added !== []) {
                $this->console()->info("Marketing Toolkit added to {$set['title']}: ".implode(', ', $added).'.');
            }
        }
    }

    /**
     * The fields brought after the old version, up to the new one: null for
     * all of them, from before 0.20. An old version that isn't a release (a
     * branch) brings none; `mt:install` adds what is missing.
     *
     * @return list<string>|null
     */
    public static function since(string $oldVersion, string $newVersion): ?array
    {
        if (! Version::isRelease($oldVersion)) {
            return [];
        }

        if (Version::before($oldVersion, '0.20.0')) {
            return null;
        }

        return collect(self::FIELDS)
            ->filter(fn (array $fields, string $version) => Version::after($version, $oldVersion) && ! Version::after($version, $newVersion))
            ->flatten()
            ->values()
            ->all();
    }
}
