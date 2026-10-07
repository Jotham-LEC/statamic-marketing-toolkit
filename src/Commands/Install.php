<?php

namespace JothamLec\MarketingToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JothamLec\MarketingToolkit\Conversions\Attribution;
use JothamLec\MarketingToolkit\Favicons\Favicons;
use JothamLec\MarketingToolkit\Listeners\SaveFeatures;
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
 * `php please mt:install`: creates the "Brand" and "Marketing settings"
 * global sets and their blueprints through Statamic's API, so editors can
 * fill in the title separator, defaults, publisher and share-card colours
 * (Brand), and the tracking tags, Consent Mode, leads, verification codes and
 * robots.txt (Marketing settings) in the control panel. Safe to rerun: it
 * adds what is missing (the fields a newer version brings too) and changes
 * nothing else.
 */
class Install extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:mt:install
        {--container= : Asset container for the logo, share image and icon (else the first one)}
        {--tab=* : Add these tabs the blueprint doesn\'t have, e.g. shop}
        {--forms : Add the lead source fields to every form}';

    protected $description = 'Create the Brand and Marketing settings global sets, or add what a newer version brings';

    /** Each set: its config key => its blueprint in resources/install and its title. */
    public const array SETS = [
        'global' => ['file' => 'seo', 'title' => 'Brand'],
        'settings_global' => ['file' => 'marketing', 'title' => 'Marketing settings'],
    ];

    /** Files the addon serves, by the config switch that turns each on. A file of the same name in public/ wins. */
    private const array SERVED = [
        'robots.txt' => 'marketing-toolkit.robots_txt.enabled',
        'llms.txt' => 'marketing-toolkit.llms_txt.enabled',
        'ads.txt' => 'marketing-toolkit.ads_txt.enabled',
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

        $changed = $this->checkPublicFiles() || $changed;

        if (! $changed) {
            $this->components->info('Already installed: nothing to add.');
        }

        return self::SUCCESS;
    }

    /**
     * One global set: its blueprint (or the fields it lacks), the set on
     * every site, and the defaults its empty fields take.
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

            // On every site; the others take what they leave empty from the default site's.
            if (Site::multiEnabled()) {
                $set->sites(Site::all()->mapWithKeys(fn ($site) => [$site->handle() => $site->handle() === Site::default()->handle() ? null : Site::default()->handle()])->all());
            }

            $set->save();

            // The Features tab starts as the addon's settings have the switches.
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
     * A set that exists but isn't on every site: enabled there when asked
     * (each taking what it leaves empty from the default site), else named.
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
     * Files in public/ that the web server answers with instead of the
     * addon's (a new Statamic site has a robots.txt and an empty
     * favicon.ico): named, and deleted when asked.
     */
    private function checkPublicFiles(): bool
    {
        $files = [
            ...array_keys(array_filter(self::SERVED, fn (string $key) => config($key))),
            ...(config('marketing-toolkit.favicons.enabled') ? array_keys(Favicons::FILES) : []),
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
     * removed: only tabs the blueprint still has receive fields. Also run by
     * the AddNewBrandFields update script after each update.
     *
     * @return list<string> the fields added
     */
    public static function addMissingFields(BlueprintContents $blueprint, string $container, string $file = 'seo'): array
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
            $filled = [...$filled, ...array_map(fn (string $field) => self::label($field), array_keys($defaults))];
        }

        return array_values(array_unique($filled));
    }

    /**
     * A field's label, as the control panel shows it.
     */
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
     * The asset container the blueprint's first assets field uses: where
     * fields added later point too.
     */
    public static function containerOf(BlueprintContents $blueprint): ?string
    {
        return $blueprint->fields()->all()->first(fn ($field) => $field->type() === 'assets' && $field->get('container'))?->get('container');
    }

    /**
     * A set's tabs, from resources/install/{$file}.yaml (seo: Brand,
     * marketing: Marketing settings), with the asset container on each
     * assets field.
     *
     * @return array<string, mixed>
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
