<?php

namespace JothamLec\MarketingToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JothamLec\MarketingToolkit\Conversions\Attribution;
use JothamLec\MarketingToolkit\Favicons\Favicons;
use JothamLec\MarketingToolkit\Listeners\SaveFeatures;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Console\RunsInPlease;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;
use Statamic\Fields\Blueprint as BlueprintContents;
use Statamic\Structures\Page;

/**
 * The `php please mt:install` command creates the "Brand" and "Marketing
 * settings" global sets and their blueprints through Statamic's API, so editors
 * can fill in the title separator, defaults, publisher and share-card colours
 * (Brand), and the tracking tags, Consent Mode, leads, verification codes and
 * robots.txt (Marketing settings) in the control panel. It is safe to rerun,
 * because it adds what is missing (including the fields a newer version brings)
 * and changes nothing else.
 */
final class Install extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:mt:install
        {--container= : Asset container for the logo, share image and icon (else the first one)}
        {--tab=* : Add these tabs the blueprint doesn\'t have, e.g. shop}
        {--forms : Add the lead source fields to every form}
        {--no-blueprints : Don\'t give collections without a blueprint one with an SEO tab}';

    protected $description = 'Create the Brand and Marketing settings global sets, or add what a newer version brings';

    /** Maps each set's config key to its blueprint in resources/install and its title. */
    public const array SETS = [
        'global' => ['file' => 'seo', 'title' => 'Brand'],
        'settings_global' => ['file' => 'marketing', 'title' => 'Marketing settings'],
    ];

    /** Lists the files the addon serves, each with its module. A file of the same name in public/ wins. */
    private const array SERVED = [
        'robots.txt' => 'robots_txt',
        'llms.txt' => 'llms_txt',
        'ads.txt' => 'ads_txt',
    ];

    public function handle(): int
    {
        $containers = AssetContainer::all()->map->handle()->values()->all();
        $container = $this->option('container') ?? ($containers[0] ?? null);

        if (! $container) {
            $this->components->error('Create an asset container first, or pass --container.');

            return self::FAILURE;
        }

        if (! in_array($container, $containers, true)) {
            $this->components->error("There is no asset container [{$container}]. The containers: ".implode(', ', $containers).'.');

            return self::FAILURE;
        }

        $tabs = (array) $this->option('tab');
        $known = collect(self::SETS)->flatMap(fn (array $set) => array_keys(self::tabs($container, $set['file'])))->all();
        $unknown = array_diff($tabs, $known);

        if ($unknown !== []) {
            $this->components->error('No tab ['.implode(', ', $unknown).']. The tabs: '.implode(', ', $known).'.');

            return self::FAILURE;
        }

        $changed = false;

        foreach (self::SETS as $key => $definition) {
            $changed = $this->installSet((string) config('marketing-toolkit.'.$key), $definition['file'], $definition['title'], $container, $tabs) || $changed;
        }

        if ($this->option('forms')) {
            $forms = Attribution::addToForms();
            $this->components->info($forms === [] ? 'Every form has the lead source fields.' : 'Lead source fields added to: '.implode(', ', $forms).'.');
            $changed = $changed || $forms !== [];
        }

        if (! $this->option('no-blueprints')) {
            $changed = $this->addSeoTabs() || $changed;
        }

        $changed = $this->checkPublicFiles() || $changed;

        if (! $changed) {
            $this->components->info('Already installed: nothing to add.');
        }

        return self::SUCCESS;
    }

    /**
     * Adds the entries' SEO tab, which is the marketing-toolkit::seo fieldset. A
     * collection with a route but no blueprint file yet, such as a new site's
     * Pages, gets a blueprint with the tab (unless --no-blueprints is passed),
     * which is a new file, like the global sets this command creates. A blueprint
     * that exists belongs to the site, so one without the tab is named but never
     * changed, and a rerun can't bring back a tab the site took out.
     */
    private function addSeoTabs(): bool
    {
        $routed = Collection::all()->filter(fn ($collection) => $collection->routes()->filter()->isNotEmpty());
        $new = $routed->filter(fn ($collection) => Blueprint::in('collections/'.$collection->handle())->isEmpty());

        $without = $routed->diffKeys($new)->flatMap(fn ($collection): \Illuminate\Support\Collection => $collection->entryBlueprints()
            ->reject(fn (BlueprintContents $blueprint) => $blueprint->hasField('seo'))
            ->map(fn (BlueprintContents $blueprint) => $collection->title().' ('.$blueprint->title().')'))
            ->values();

        if ($without->isNotEmpty()) {
            $this->components->warn('These blueprints have no SEO tab: '.$without->implode(', ').'. Add one with Link Fieldset → SEO (see docs/getting-started.md).');
        }

        if ($new->isEmpty()) {
            return false;
        }

        foreach ($new as $collection) {
            $blueprint = $collection->fallbackEntryBlueprint();
            $contents = $blueprint->contents();
            $contents['tabs']['seo'] = ['display' => 'SEO', 'sections' => [['fields' => [['import' => 'marketing-toolkit::seo']]]]];
            $blueprint->setContents($contents)->save();
        }

        $this->components->info('SEO tab added to the blueprints of: '.$new->map->title()->implode(', ').'. Commit them.');

        return true;
    }

    /**
     * Installs one global set, which means its blueprint (or the fields it
     * lacks), the set on every site, and the defaults its empty fields take.
     *
     * @param  list<string>  $tabs  tabs to add that the blueprint doesn't have
     */
    private function installSet(string $handle, string $file, string $title, string $container, array $tabs): bool
    {
        $changed = false;
        $blueprint = Blueprint::find("globals.{$handle}");

        if (! $blueprint) {
            Blueprint::make($handle)
                ->setNamespace('globals')
                ->setContents(['tabs' => self::tabs($container, $file)])
                ->save();

            $this->components->info("Blueprint globals.{$handle} created.");
            $changed = true;
        } else {
            $added = [...$this->addTabs($blueprint, $tabs, $container, $file), ...self::addMissingFields($blueprint, $container, $file)];

            if ($added !== []) {
                $this->components->info("Added to {$title}: ".implode(', ', $added).'.');
                $changed = true;
            }
        }

        if (! GlobalSet::findByHandle($handle)) {
            $set = GlobalSet::make($handle)->title($title);

            // The set goes on every site, and the others take what they leave empty from the default site's.
            if (Site::multiEnabled()) {
                $set->sites(Site::all()->mapWithKeys(fn ($site) => [$site->handle() => $site->handle() === Site::default()->handle() ? null : Site::default()->handle()])->all());
            }

            $set->save();

            // The Features tab starts with the switches set as the addon's settings have them.
            if ($handle === config('marketing-toolkit.settings_global')) {
                SaveFeatures::seed($set);
            }

            $this->components->info("Global set [{$handle}] created.");
            $changed = true;
        }

        $set = GlobalSet::findByHandle($handle);
        $changed = $this->enableOnEverySite($set) || $changed;

        $filled = $this->fillDefaults($set);

        if ($filled !== []) {
            $this->components->info('Filled in: '.implode(', ', $filled).". Change them under Globals → {$title}.");
            $changed = true;
        }

        return $changed;
    }

    /**
     * Handles a set that exists but isn't on every site. When asked, it enables
     * the set there (each site taking what it leaves empty from the default
     * site); otherwise, it names those sites.
     */
    private function enableOnEverySite(GlobalSetContract $set): bool
    {
        $missing = Site::all()->map->handle()->diff($set->sites())->values();

        if (! Site::multiEnabled() || $missing->isEmpty()) {
            return false;
        }

        if ($this->input->isInteractive() && $this->confirm("{$set->title()} isn't enabled on: {$missing->implode(', ')}. Enable it there, taking what each leaves empty from the default site?", true)) {
            $set->sites([...$set->origins()->all(), ...$missing->mapWithKeys(fn (string $site) => [$site => Site::default()->handle()])->all()])->save();
            $this->components->info("Enabled on: {$missing->implode(', ')}.");

            return true;
        }

        $this->components->warn("Global set [{$set->handle()}] isn't enabled on: {$missing->implode(', ')}. Those sites use the addon's defaults until it is (Globals → {$set->title()} → Sites).");

        return false;
    }

    /**
     * Names the files in public/ that the web server answers with instead of
     * the addon's own, and deletes them when asked. A new Statamic site has a
     * robots.txt and an empty favicon.ico, for example.
     */
    private function checkPublicFiles(): bool
    {
        $files = [
            ...array_keys(array_filter(self::SERVED, fn (string $module) => Features::on($module))),
            ...(Features::on('favicons') ? array_keys(Favicons::FILES) : []),
        ];
        $found = array_values(array_filter($files, fn (string $file) => is_file(public_path($file))));

        if ($found === []) {
            return false;
        }

        $list = implode(', ', array_map(fn (string $file) => "public/{$file}", $found));
        $this->components->warn("The web server serves {$list} instead of the addon's own. Delete them to use the addon's (its icons are made from the icon in Brand).");

        if (! $this->input->isInteractive() || ! $this->confirm('Delete them, so the addon serves its own?', false)) {
            return false;
        }

        foreach ($found as $file) {
            File::delete(public_path($file));
        }

        $this->components->info("Deleted {$list}.");

        return true;
    }

    /**
     * Adds whole tabs a site asks for by handle and doesn't have.
     *
     * @param  list<string>  $handles
     * @return list<string> the tabs added
     */
    private function addTabs(BlueprintContents $blueprint, array $handles, string $container, string $file): array
    {
        $contents = $blueprint->contents();
        $tabs = self::tabs($container, $file);
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
     * removed, because only tabs the blueprint still has receive fields. The
     * AddNewBrandFields update script passes the fields the new version
     * brings, as `$only`, so a field the site removed stays removed.
     *
     * @param  list<string>|null  $only  the fields that may be added (all of them when null)
     * @return list<string> the fields added
     */
    public static function addMissingFields(BlueprintContents $blueprint, string $container, string $file = 'seo', ?array $only = null): array
    {
        $contents = $blueprint->contents();
        $existing = $blueprint->fields()->all()->keys()->all();
        $added = [];

        foreach (self::tabs($container, $file) as $tab => $config) {
            if (! isset($contents['tabs'][$tab])) {
                continue;
            }

            foreach ($config['sections'] as $index => $section) {
                foreach ($section['fields'] as $field) {
                    if (in_array($field['handle'], $existing, true) || ($only !== null && ! in_array($field['handle'], $only, true))) {
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
     * site uses when they are empty. It does so on each site that the set is
     * enabled on and that has no origin. A site with an origin takes the
     * origin's values, so filling it would cut it off from them.
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
            $homeDescription = data_get($home?->value('seo'), 'description') ?: $home?->value('description');

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
            $filled = [...$filled, ...array_map(fn (string $field) => self::label($field), array_keys($defaults))];
        }

        return array_values(array_unique($filled));
    }

    private static function label(string $handle): string
    {
        foreach (collect(self::SETS)->flatMap(fn (array $set) => self::tabs('', $set['file'])) as $tab) {
            foreach ($tab['sections'] as $section) {
                foreach ($section['fields'] as $field) {
                    if ($field['handle'] === $handle) {
                        return (string) __($field['field']['display'] ?? $handle);
                    }
                }
            }
        }

        return $handle;
    }

    /**
     * Gets the asset container that the blueprint's first assets field uses,
     * which is where fields added later point too.
     */
    public static function containerOf(BlueprintContents $blueprint): ?string
    {
        return $blueprint->fields()->all()->first(fn ($field) => $field->type() === 'assets' && $field->get('container'))?->get('container');
    }

    /**
     * Gets a set's tabs from resources/install/{$file}.yaml (seo for Brand and
     * marketing for Marketing settings), with the asset container set on each
     * assets field.
     *
     * @return array<string, array{display: string, sections: list<array{display?: string, fields: list<array{handle: string, field: array<string, mixed>}>}>}>
     */
    public static function tabs(string $container, string $file = 'seo'): array
    {
        $tabs = YAML::file(__DIR__.'/../../resources/install/'.$file.'.yaml')->parse()['tabs'];

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
