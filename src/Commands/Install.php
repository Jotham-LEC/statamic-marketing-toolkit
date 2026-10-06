<?php

namespace JothamLec\MarketingToolkit\Commands;

use Illuminate\Console\Command;
use JothamLec\MarketingToolkit\Conversions\Attribution;
use Statamic\Console\RunsInPlease;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;
use Statamic\Fields\Blueprint as BlueprintContents;
use Statamic\Structures\Page;

/**
 * `php please seo:install`: creates the "SEO & brand" global set and its
 * blueprint through Statamic's API, so editors can fill in the title
 * separator, defaults, publisher, verification codes, robots.txt and the
 * share-card colours in the control panel. Safe to rerun: it adds nothing
 * that already exists.
 */
class Install extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:seo:install {--container= : Asset container for the logo and default image} {--fields : Add fields a newer version brings to an existing blueprint} {--tab=* : Add these tabs (e.g. shop) to an existing blueprint} {--forms : Add the lead source fields (Pro) to every form}';

    protected $description = 'Create the SEO & brand global set';

    public function handle(): int
    {
        $handle = (string) config('seo.global');
        $container = $this->option('container') ?? AssetContainer::all()->first()?->handle();

        if (! $container) {
            $this->components->error('Create an asset container first, or pass --container.');

            return self::FAILURE;
        }

        if (! Blueprint::find("globals.{$handle}")) {
            Blueprint::make($handle)
                ->setNamespace('globals')
                ->setContents(['tabs' => self::tabs($container)])
                ->save();

            $this->components->info("Blueprint globals.{$handle} created.");
        } else {
            $blueprint = Blueprint::find("globals.{$handle}");
            $added = [
                ...$this->addTabs($blueprint, (array) $this->option('tab'), $container),
                ...($this->option('fields') ? $this->addMissingFields($blueprint, $container) : []),
            ];

            if ($added !== []) {
                $this->components->info('Added: '.implode(', ', $added).'.');
            }
        }

        if ($this->option('forms')) {
            $forms = Attribution::addToForms();
            $this->components->info($forms === [] ? 'Every form has the lead source fields.' : 'Lead source fields added to: '.implode(', ', $forms).'.');
        }

        if (! GlobalSet::findByHandle($handle)) {
            $set = GlobalSet::make($handle)->title('SEO & brand');

            // On every site; the others take what they leave empty from the default site's.
            if (Site::multiEnabled()) {
                $set->sites(Site::all()->mapWithKeys(fn ($site) => [$site->handle() => $site->handle() === Site::default()->handle() ? null : Site::default()->handle()])->all());
            }

            $set->save();

            $this->components->info("Global set [{$handle}] created.");
        }

        $set = GlobalSet::findByHandle($handle);
        $missing = Site::all()->map->handle()->diff($set->sites())->values();

        if (Site::multiEnabled() && $missing->isNotEmpty()) {
            $this->components->warn("Global set [{$handle}] isn't enabled on: {$missing->implode(', ')}. Those sites use the addon's defaults until it is (Globals → SEO & brand → Sites).");
        }

        $filled = $this->fillDefaults($set);

        if ($filled !== []) {
            $this->components->info('Defaults filled in: '.implode(', ', $filled).'. Change them under Globals → SEO & brand.');
        }

        return self::SUCCESS;
    }

    /**
     * Adds whole tabs a site asks for by handle and doesn't have.
     *
     * @param  list<string>  $handles
     * @return list<string> the tabs added
     */
    private function addTabs(BlueprintContents $blueprint, array $handles, string $container): array
    {
        $contents = $blueprint->contents();
        $tabs = self::tabs($container);
        $added = array_values(array_filter($handles, fn (string $tab) => isset($tabs[$tab]) && ! isset($contents['tabs'][$tab])));

        foreach ($added as $tab) {
            $contents['tabs'][$tab] = $tabs[$tab];
        }

        if ($added !== []) {
            $blueprint->setContents($contents)->save();
        }

        return array_map(fn (string $tab) => "{$tab} tab", $added);
    }

    /**
     * Adds to an existing blueprint the fields it lacks, each in its tab and
     * section as a fresh install has them. A tab the site removed stays
     * removed: only tabs the blueprint still has receive fields.
     *
     * @return list<string> the fields added
     */
    private function addMissingFields(BlueprintContents $blueprint, string $container): array
    {
        $contents = $blueprint->contents();
        $existing = $blueprint->fields()->all()->keys()->all();
        $added = [];

        foreach (self::tabs($container) as $tab => $config) {
            if (! isset($contents['tabs'][$tab])) {
                continue;
            }

            foreach ($config['sections'] as $index => $section) {
                foreach ($section['fields'] as $field) {
                    if (in_array($field['handle'], $existing, true)) {
                        continue;
                    }

                    $contents['tabs'][$tab]['sections'][$index] ??= array_diff_key($section, ['fields' => true]) + ['fields' => []];
                    $contents['tabs'][$tab]['sections'][$index]['fields'][] = $field;
                    $added[] = $field['handle'];
                }
            }
        }

        if ($added !== []) {
            $blueprint->setContents($contents)->save();
        }

        return $added;
    }

    /**
     * Fills the brand fields that are empty, and only those, with what the
     * site uses when they are: on each site the set is enabled on that has
     * no origin. A site with an origin takes the origin's values, so filling
     * it would cut it off from them.
     *
     * @return list<string> the fields filled
     */
    private function fillDefaults(GlobalSetContract $set): array
    {
        $fields = Blueprint::find('globals.'.$set->handle())?->fields()->all()->keys()->all() ?? [];
        $filled = [];

        foreach ($set->origins()->filter(fn ($origin) => $origin === null)->keys() as $site) {
            if (! Site::get($site)) {
                continue;
            }

            $variables = $set->in($site) ?? $set->makeLocalization($site);

            $home = Entry::findByUri('/', $site);
            $home = $home instanceof Page ? $home->entry() : $home;
            $homeDescription = data_get($home?->get('seo'), 'description') ?: $home?->get('description');

            $defaults = array_filter([
                'default_description' => is_string($homeDescription) && $homeDescription !== '' ? $homeDescription : null,
                'robots_disallow' => ['/'.trim((string) config('statamic.cp.route', 'cp'), '/').'/'],
            ], fn ($value, $field) => $value !== null && in_array($field, $fields, true) && blank($variables->get($field)), ARRAY_FILTER_USE_BOTH);

            if ($defaults === []) {
                continue;
            }

            foreach ($defaults as $field => $value) {
                $variables->set($field, $value);
            }

            $variables->save();
            $filled = [...$filled, ...array_keys($defaults)];
        }

        return array_values(array_unique($filled));
    }

    /**
     * The blueprint's tabs, from resources/install/seo.yaml, with the asset
     * container on each assets field.
     *
     * @return array<string, mixed>
     */
    public static function tabs(string $container): array
    {
        $tabs = YAML::file(__DIR__.'/../../resources/install/seo.yaml')->parse()['tabs'];

        foreach ($tabs as $tab => $config) {
            foreach ($config['sections'] as $section => $fields) {
                foreach ($fields['fields'] as $index => $field) {
                    if ($field['field']['type'] === 'assets') {
                        $tabs[$tab]['sections'][$section]['fields'][$index]['field']['container'] = $container;
                    }
                }
            }
        }

        return $tabs;
    }
}
