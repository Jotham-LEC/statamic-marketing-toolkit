<?php

namespace JothamLec\Seo\Commands;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Fields\Blueprint as BlueprintContents;
use Statamic\Structures\Page;

/**
 * `php please seo:install`: creates the "SEO & brand" global set and its
 * blueprint through Statamic's API, so editors can fill in the site name,
 * defaults, publisher, verification codes, robots.txt and the
 * share-card colours in the control panel. Safe to rerun: it adds nothing
 * that already exists.
 */
class Install extends Command
{
    /** Common choices; any other schema.org type can be typed in. */
    private const array PUBLISHER_TYPES = [
        'Organization' => 'Organization',
        'Corporation' => 'Corporation',
        'EducationalOrganization' => 'Educational organization',
        'NGO' => 'Non-profit',
        'LocalBusiness' => 'Local business',
        'Store' => 'Store',
        'ProfessionalService' => 'Professional service',
        'Restaurant' => 'Restaurant',
        'Person' => 'Person',
    ];

    private const array DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    use RunsInPlease;

    protected $signature = 'statamic:seo:install {--container= : Asset container for the logo and default image} {--fields : Add fields a newer version brings to an existing blueprint} {--tab=* : Add these tabs (e.g. shop) to an existing blueprint}';

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

        if (! GlobalSet::findByHandle($handle)) {
            GlobalSet::make($handle)->title('SEO & brand')->save();

            $this->components->info("Global set [{$handle}] created.");
        }

        $filled = $this->fillDefaults(GlobalSet::findByHandle($handle));

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
     * site uses when they are.
     *
     * @return list<string> the fields filled
     */
    private function fillDefaults(GlobalSetContract $set): array
    {
        $site = Site::default();
        $variables = $set->in($site->handle()) ?? $set->makeLocalization($site->handle());
        $fields = Blueprint::find('globals.'.$set->handle())?->fields()->all()->keys()->all() ?? [];

        $home = Entry::findByUri('/', $site->handle());
        $home = $home instanceof Page ? $home->entry() : $home;
        $homeDescription = data_get($home?->get('seo'), 'description') ?: $home?->get('description');

        $defaults = array_filter([
            'title_separator' => '·',
            'default_description' => is_string($homeDescription) && $homeDescription !== '' ? $homeDescription : null,
            'robots_disallow' => ['/'.trim((string) config('statamic.cp.route', 'cp'), '/').'/'],
        ], fn ($value, $field) => $value !== null && in_array($field, $fields, true) && blank($variables->get($field)), ARRAY_FILTER_USE_BOTH);

        if ($defaults === []) {
            return [];
        }

        foreach ($defaults as $field => $value) {
            $variables->set($field, $value);
        }

        $variables->save();

        return array_keys($defaults);
    }

    /**
     * @return array<string, mixed>
     */
    public static function tabs(string $container): array
    {
        $field = fn (string $handle, array $config) => ['handle' => $handle, 'field' => $config];
        $asset = fn (string $display, string $instructions = '') => ['type' => 'assets', 'display' => $display, 'container' => $container, 'max_files' => 1, 'instructions' => $instructions];

        return [
            'brand' => ['display' => 'Brand', 'sections' => [['fields' => [
                $field('title_separator', ['type' => 'text', 'display' => 'Title separator', 'width' => 50, 'placeholder' => '·', 'instructions' => 'Between the page title and the site name, with a space on each side.']),
                $field('default_description', ['type' => 'textarea', 'display' => 'Default description', 'character_limit' => 160, 'instructions' => 'For pages with no description and no first paragraph.']),
                $field('default_image', $asset('Default share image', 'For pages without an image or a generated card. 1200×630.')),
                $field('site_alternate_name', ['type' => 'text', 'display' => 'Other site name', 'width' => 50, 'instructions' => 'A shorter name or acronym search engines may show instead.']),
                $field('twitter_handle', ['type' => 'text', 'display' => 'X handle', 'width' => 50, 'prepend' => '@']),
            ]]]],
            'publisher' => ['display' => 'Publisher', 'sections' => [
                ['instructions' => 'Who is behind the site, for search engines (JSON-LD). Each value is printed only where its type accepts it.', 'fields' => [
                    $field('publisher_type', ['type' => 'select', 'display' => 'Type', 'width' => 50, 'multiple' => true, 'taggable' => true, 'default' => ['Organization'], 'options' => self::PUBLISHER_TYPES, 'instructions' => 'The most specific schema.org type, or two (EducationalOrganization and LocalBusiness). Type any other schema.org type.']),
                    $field('publisher_name', ['type' => 'text', 'display' => 'Name', 'width' => 50]),
                    $field('publisher_alternate_name', ['type' => 'text', 'display' => 'Other name', 'width' => 50, 'instructions' => 'An abbreviation or former name.']),
                    $field('founding_date', ['type' => 'date', 'display' => 'Founded', 'width' => 50]),
                    $field('publisher_description', ['type' => 'textarea', 'display' => 'Description']),
                    $field('publisher_logo', $asset('Logo or portrait')),
                    $field('job_title', ['type' => 'text', 'display' => 'Job title', 'width' => 50, 'if' => ['publisher_type' => 'contains Person']]),
                    $field('telephone', ['type' => 'text', 'display' => 'Telephone', 'width' => 50]),
                    $field('email', ['type' => 'text', 'input_type' => 'email', 'display' => 'Email', 'width' => 50]),
                    $field('area_served', ['type' => 'text', 'display' => 'Area served', 'width' => 50]),
                    $field('same_as', ['type' => 'list', 'display' => 'Profiles elsewhere', 'instructions' => 'Full URLs: LinkedIn, Instagram, Google Business Profile…']),
                    $field('contact_points', ['type' => 'grid', 'display' => 'Contact points', 'mode' => 'table', 'add_row' => 'Add a contact point', 'fields' => [
                        $field('contact_type', ['type' => 'text', 'display' => 'For', 'placeholder' => 'customer service']),
                        $field('telephone', ['type' => 'text', 'display' => 'Telephone']),
                        $field('email', ['type' => 'text', 'display' => 'Email']),
                    ]]),
                ]],
                ['display' => 'Address', 'instructions' => 'Required for a local business with premises; leave empty for one that only serves an area.', 'fields' => [
                    $field('street_address', ['type' => 'text', 'display' => 'Street address']),
                    $field('address_locality', ['type' => 'text', 'display' => 'City', 'width' => 50]),
                    $field('address_region', ['type' => 'text', 'display' => 'State or region', 'width' => 50]),
                    $field('postal_code', ['type' => 'text', 'display' => 'Postcode', 'width' => 50]),
                    $field('address_country', ['type' => 'text', 'display' => 'Country code', 'width' => 50, 'placeholder' => 'MY, AU, US…']),
                ]],
                ['display' => 'Local business', 'instructions' => 'For a Store, a Restaurant or another LocalBusiness type.', 'fields' => [
                    $field('price_range', ['type' => 'text', 'display' => 'Price range', 'width' => 33, 'placeholder' => '$$']),
                    $field('latitude', ['type' => 'text', 'display' => 'Latitude', 'width' => 33]),
                    $field('longitude', ['type' => 'text', 'display' => 'Longitude', 'width' => 33]),
                    $field('opening_hours', ['type' => 'grid', 'display' => 'Opening hours', 'mode' => 'table', 'add_row' => 'Add hours', 'fields' => [
                        $field('days', ['type' => 'checkboxes', 'display' => 'Days', 'inline' => true, 'options' => array_combine(self::DAYS, array_map(fn (string $day) => substr($day, 0, 3), self::DAYS))]),
                        $field('opens', ['type' => 'time', 'display' => 'Opens']),
                        $field('closes', ['type' => 'time', 'display' => 'Closes']),
                    ]]),
                ]],
            ]],
            'shop' => ['display' => 'Shop', 'sections' => [
                ['instructions' => 'For a site that sells: the currency of its prices, and the return and shipping policies for all its products.', 'fields' => [
                    $field('currency', ['type' => 'text', 'display' => 'Currency', 'width' => 33, 'placeholder' => 'MYR, AUD, USD…', 'instructions' => 'Three-letter code.']),
                ]],
                ['display' => 'Returns', 'fields' => [
                    $field('return_category', ['type' => 'select', 'display' => 'Returns', 'width' => 33, 'options' => [
                        'MerchantReturnFiniteReturnWindow' => 'Within a number of days',
                        'MerchantReturnUnlimitedWindow' => 'Any time',
                        'MerchantReturnNotPermitted' => 'Not accepted',
                    ]]),
                    $field('return_days', ['type' => 'integer', 'display' => 'Days to return', 'width' => 33, 'if' => ['return_category' => 'equals MerchantReturnFiniteReturnWindow']]),
                    $field('return_country', ['type' => 'text', 'display' => 'Country code', 'width' => 33, 'placeholder' => 'MY']),
                    $field('return_policy_link', ['type' => 'text', 'input_type' => 'url', 'display' => 'Return policy page', 'instructions' => 'Enough on its own, or alongside the details above.']),
                ]],
                ['display' => 'Shipping', 'fields' => [
                    $field('shipping_rates', ['type' => 'grid', 'display' => 'Shipping rates', 'mode' => 'table', 'add_row' => 'Add a rate', 'instructions' => 'One row per destination and order value. Leave the order values empty for a flat rate.', 'fields' => [
                        $field('country', ['type' => 'text', 'display' => 'Country']),
                        $field('region', ['type' => 'text', 'display' => 'Region']),
                        $field('min_order', ['type' => 'float', 'display' => 'Orders from']),
                        $field('max_order', ['type' => 'float', 'display' => 'Orders up to']),
                        $field('rate', ['type' => 'float', 'display' => 'Rate']),
                        $field('min_days', ['type' => 'integer', 'display' => 'Days, from']),
                        $field('max_days', ['type' => 'integer', 'display' => 'Days, to']),
                    ]]),
                ]],
            ]],
            'share_cards' => ['display' => 'Share cards', 'sections' => [['instructions' => 'Colours and picture for generated share images.', 'fields' => [
                $field('og_background', ['type' => 'color', 'display' => 'Background', 'width' => 33]),
                $field('og_text', ['type' => 'color', 'display' => 'Text', 'width' => 33]),
                $field('og_accent', ['type' => 'color', 'display' => 'Accent', 'width' => 33]),
                $field('og_picture', $asset('Picture', 'A logo or portrait on every card.')),
            ]]]],
            'crawlers' => ['display' => 'Crawlers', 'sections' => [['fields' => [
                $field('google_verification', ['type' => 'text', 'display' => 'Google verification', 'width' => 50]),
                $field('bing_verification', ['type' => 'text', 'display' => 'Bing verification', 'width' => 50]),
                $field('yandex_verification', ['type' => 'text', 'display' => 'Yandex verification', 'width' => 50]),
                $field('pinterest_verification', ['type' => 'text', 'display' => 'Pinterest verification', 'width' => 50]),
                $field('robots_disallow', ['type' => 'list', 'display' => 'robots.txt Disallow', 'instructions' => 'Paths to keep crawlers out of. Empty: the control panel.']),
                $field('allow_ai_training', ['type' => 'toggle', 'display' => 'Allow AI training', 'default' => true, 'width' => 50, 'instructions' => 'Off: GPTBot, ClaudeBot, Google-Extended, Applebot-Extended and CCBot are turned away in robots.txt. Google Search is unaffected.']),
                $field('allow_ai_search', ['type' => 'toggle', 'display' => 'Allow AI search', 'default' => true, 'width' => 50, 'instructions' => 'Off: the crawlers behind ChatGPT search, Claude and Perplexity answers are turned away.']),
                $field('robots_extra', ['type' => 'textarea', 'display' => 'robots.txt extra lines', 'instructions' => 'Added as typed, e.g. rules for AI crawlers.']),
            ]]]],
        ];
    }
}
