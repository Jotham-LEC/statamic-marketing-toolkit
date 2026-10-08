<?php

namespace JothamLec\MarketingToolkit\Legacy;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use JothamLec\MarketingToolkit\Support\Package;
use Statamic\Facades\YAML;
use Throwable;

/**
 * Co-SEO saved its addon settings (the Search Console property) under its own name, in
 * `resources/addons/seo.yaml` or, with the Eloquent driver, in an `addon_settings` row for
 * `jotham-lec/statamic-co-seo`. This copies them so Marketing Toolkit reads them under its
 * new name. A migration runs it once, and what the new name already has wins.
 *
 * This was added in 0.18.0 and will be removed in 1.0; see "Removed in 1.0" in docs/upgrading.md.
 */
final class CoSeoSettings
{
    public const string OLD_PACKAGE = 'jotham-lec/statamic-co-seo';

    public static function carryOver(): void
    {
        self::file();
        self::database();
    }

    private static function file(): void
    {
        $old = resource_path('addons/seo.yaml');
        $new = resource_path('addons/marketing-toolkit.yaml');

        if (! File::exists($old)) {
            return;
        }

        $values = [...(array) YAML::file($old)->parse(), ...(File::exists($new) ? (array) YAML::file($new)->parse() : [])];
        File::put($new, YAML::dump($values));
    }

    private static function database(): void
    {
        $model = config('statamic.eloquent-driver.addon_settings.model');

        if (config('statamic.eloquent-driver.addon_settings.driver') !== 'eloquent' || ! is_string($model) || ! class_exists($model)) {
            return;
        }

        try {
            if (! Schema::hasTable((new $model)->getTable())) {
                return;
            }

            $old = $model::query()->where('addon', self::OLD_PACKAGE)->first();

            if ($old === null) {
                return;
            }

            $new = $model::query()->firstOrNew(['addon' => Package::NAME]);
            $new->settings = [...(array) $old->settings, ...(array) $new->settings];
            $new->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
