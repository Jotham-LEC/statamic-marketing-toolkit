<?php

namespace JothamLec\MarketingToolkit\UpdateScripts;

use JothamLec\MarketingToolkit\Commands\Install;
use JothamLec\MarketingToolkit\Listeners\SaveFeatures;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;

/**
 * Version 0.21 splits "SEO & brand" in two. Brand keeps the brand, publisher, shop
 * and share-card fields, and the new Marketing settings set takes the
 * tracking tags, Consent Mode, leads and crawlers. This creates Marketing
 * settings on the sites Brand is on, moves each localization's values of
 * those fields across, takes the fields out of Brand's blueprint (a field the
 * site added itself stays where it is), and renames "SEO & brand" to "Brand".
 * Statamic runs it on `composer update` from before 0.21, and you should commit the files
 * it changes. It doesn't run when updating from 0.21 or later, so a field the
 * site puts back in Brand stays there. It still runs, with `php please
 * updates:run 0.20.0 --package=jotham-lec/statamic-marketing-toolkit`, while
 * Brand holds values of those fields, as on a server whose globals are in
 * the database and whose blueprints were moved where the update ran first.
 */
final class MoveToMarketingSettings extends UpdateScript
{
    public function shouldUpdate($newVersion, $oldVersion)
    {
        if (! self::before((string) $oldVersion, '0.21.0')) {
            return false;
        }

        $blueprint = Blueprint::find('globals.'.config('marketing-toolkit.global'));
        $moved = self::moved();

        return $blueprint !== null && (array_intersect($moved, $blueprint->fields()->all()->keys()->all()) !== []
            || GlobalSet::findByHandle((string) config('marketing-toolkit.global'))?->localizations()
                ->contains(fn ($variables) => $variables->data()->only($moved)->isNotEmpty()));
    }

    public function update(): void
    {
        $brandHandle = (string) config('marketing-toolkit.global');
        $handle = (string) config('marketing-toolkit.settings_global');
        $brand = Blueprint::find('globals.'.$brandHandle);
        $container = Install::containerOf($brand) ?? AssetContainer::all()->first()?->handle() ?? '';
        $moved = self::moved();

        if (! Blueprint::find('globals.'.$handle)) {
            Blueprint::make($handle)->setNamespace('globals')->setContents(['tabs' => Install::tabs($container, 'marketing')])->save();
        }

        $brandSet = GlobalSet::findByHandle($brandHandle);
        $set = GlobalSet::findByHandle($handle);

        if ($brandSet && ! $set) {
            $set = GlobalSet::make($handle)->title(Install::SETS['settings_global']['title']);
            $set->sites($brandSet->origins()->all());
            $set->save();
            $set = GlobalSet::findByHandle($handle);
            // This fills its Features tab from the switches in the addon's settings.
            SaveFeatures::seed($set);
        }

        // Each localization's own values move across, but not those it takes from its origin.
        foreach ($brandSet?->localizations() ?? [] as $site => $variables) {
            $values = $variables->data()->only($moved)->all();

            if ($values === []) {
                continue;
            }

            $target = $set->in($site) ?? $set->makeLocalization($site);

            // A value already in Marketing settings (saved there since) is newer, so the one left in Brand goes.
            foreach ($values as $key => $value) {
                if (! $target->data()->has($key)) {
                    $target->set($key, $value);
                }

                $variables->remove($key);
            }

            $target->save();
            $variables->save();
        }

        $brand->setContents($this->without($brand->contents(), $moved))->save();

        if ($brandSet && $brandSet->title() === 'SEO & brand') {
            $brandSet->title(Install::SETS['global']['title'])->save();
        }

        $this->console()->info('Marketing Toolkit moved tracking, Consent Mode, leads and crawlers from SEO & brand (now "Brand") to Marketing settings. Commit the files it changed.');
    }

    /**
     * Returns the handles of the fields that Marketing settings holds.
     *
     * @return list<string>
     */
    public static function moved(): array
    {
        return collect(Install::tabs('', 'marketing'))
            ->flatMap(fn (array $tab) => $tab['sections'])
            ->flatMap(fn (array $section) => $section['fields'])
            ->pluck('handle')
            ->values()
            ->all();
    }

    /**
     * Returns a blueprint's contents without the moved fields, and without the
     * sections and tabs they leave empty.
     *
     * @param  array<string, mixed>  $contents
     * @param  list<string>  $moved
     * @return array<string, mixed>
     */
    private function without(array $contents, array $moved): array
    {
        foreach ($contents['tabs'] ?? [] as $tab => $config) {
            foreach ($config['sections'] ?? [] as $index => $section) {
                $fields = array_values(array_filter($section['fields'] ?? [], fn ($field) => ! in_array($field['handle'] ?? null, $moved, true)));

                if ($fields === []) {
                    unset($contents['tabs'][$tab]['sections'][$index]);
                } else {
                    $contents['tabs'][$tab]['sections'][$index]['fields'] = $fields;
                }
            }

            $contents['tabs'][$tab]['sections'] = array_values($contents['tabs'][$tab]['sections'] ?? []);

            if ($contents['tabs'][$tab]['sections'] === []) {
                unset($contents['tabs'][$tab]);
            }
        }

        return $contents;
    }
}
