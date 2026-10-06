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
 * blueprint through Statamic's API, so editors can fill in the title
 * separator, defaults, publisher, verification codes, robots.txt and the
 * share-card colours in the control panel. Safe to rerun: it adds nothing
 * that already exists.
 */
class Install extends Command
{
    /** Common choices; any other schema.org type can be typed in. */
    private const array PUBLISHER_TYPES = [
        'Organization' => 'seo::fields.brand.publisher_type.options.organization',
        'Corporation' => 'seo::fields.brand.publisher_type.options.corporation',
        'EducationalOrganization' => 'seo::fields.brand.publisher_type.options.educational_organization',
        'NGO' => 'seo::fields.brand.publisher_type.options.ngo',
        'LocalBusiness' => 'seo::fields.brand.publisher_type.options.local_business',
        'Store' => 'seo::fields.brand.publisher_type.options.store',
        'ProfessionalService' => 'seo::fields.brand.publisher_type.options.professional_service',
        'Restaurant' => 'seo::fields.brand.publisher_type.options.restaurant',
        'Person' => 'seo::fields.brand.publisher_type.options.person',
    ];

    private const array DAYS = [
        'Monday' => 'seo::fields.brand.days.options.monday',
        'Tuesday' => 'seo::fields.brand.days.options.tuesday',
        'Wednesday' => 'seo::fields.brand.days.options.wednesday',
        'Thursday' => 'seo::fields.brand.days.options.thursday',
        'Friday' => 'seo::fields.brand.days.options.friday',
        'Saturday' => 'seo::fields.brand.days.options.saturday',
        'Sunday' => 'seo::fields.brand.days.options.sunday',
    ];

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
                'title_separator' => '·',
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
     * The blueprint keeps translation keys (lang/en/fields.php), not English,
     * so the control panel shows it in each user's language.
     *
     * @return array<string, mixed>
     */
    public static function tabs(string $container): array
    {
        $field = fn (string $handle, array $config) => ['handle' => $handle, 'field' => $config];
        $asset = fn (string $display, string $instructions = '') => ['type' => 'assets', 'display' => $display, 'container' => $container, 'max_files' => 1, 'instructions' => $instructions];

        return [
            'brand' => ['display' => 'seo::fields.brand.tabs.brand', 'sections' => [['fields' => [
                $field('title_separator', ['type' => 'text', 'display' => 'seo::fields.brand.title_separator.display', 'width' => 50, 'placeholder' => '·', 'instructions' => 'seo::fields.brand.title_separator.instructions']),
                $field('default_description', ['type' => 'textarea', 'display' => 'seo::fields.brand.default_description.display', 'character_limit' => 160, 'instructions' => 'seo::fields.brand.default_description.instructions']),
                $field('default_image', $asset('seo::fields.brand.default_image.display', 'seo::fields.brand.default_image.instructions')),
                $field('site_alternate_name', ['type' => 'text', 'display' => 'seo::fields.brand.site_alternate_name.display', 'width' => 50, 'instructions' => 'seo::fields.brand.site_alternate_name.instructions']),
                $field('twitter_handle', ['type' => 'text', 'display' => 'seo::fields.brand.twitter_handle.display', 'width' => 50, 'prepend' => '@']),
            ]]]],
            'publisher' => ['display' => 'seo::fields.brand.tabs.publisher', 'sections' => [
                ['instructions' => 'seo::fields.brand.sections.publisher.instructions', 'fields' => [
                    $field('publisher_type', ['type' => 'select', 'display' => 'seo::fields.brand.publisher_type.display', 'width' => 50, 'multiple' => true, 'taggable' => true, 'default' => ['Organization'], 'options' => self::PUBLISHER_TYPES, 'instructions' => 'seo::fields.brand.publisher_type.instructions']),
                    $field('publisher_name', ['type' => 'text', 'display' => 'seo::fields.brand.publisher_name.display', 'width' => 50]),
                    $field('publisher_alternate_name', ['type' => 'text', 'display' => 'seo::fields.brand.publisher_alternate_name.display', 'width' => 50, 'instructions' => 'seo::fields.brand.publisher_alternate_name.instructions']),
                    $field('founding_date', ['type' => 'date', 'display' => 'seo::fields.brand.founding_date.display', 'width' => 50]),
                    $field('publisher_description', ['type' => 'textarea', 'display' => 'seo::fields.brand.publisher_description.display']),
                    $field('publisher_logo', $asset('seo::fields.brand.publisher_logo.display')),
                    $field('job_title', ['type' => 'text', 'display' => 'seo::fields.brand.job_title.display', 'width' => 50, 'if' => ['publisher_type' => 'contains Person']]),
                    $field('telephone', ['type' => 'text', 'display' => 'seo::fields.brand.telephone.display', 'width' => 50]),
                    $field('email', ['type' => 'text', 'input_type' => 'email', 'display' => 'seo::fields.brand.email.display', 'width' => 50]),
                    $field('area_served', ['type' => 'text', 'display' => 'seo::fields.brand.area_served.display', 'width' => 50]),
                    $field('same_as', ['type' => 'list', 'display' => 'seo::fields.brand.same_as.display', 'instructions' => 'seo::fields.brand.same_as.instructions']),
                    $field('contact_points', ['type' => 'grid', 'display' => 'seo::fields.brand.contact_points.display', 'mode' => 'table', 'add_row' => 'seo::fields.brand.contact_points.add_row', 'fields' => [
                        $field('contact_type', ['type' => 'text', 'display' => 'seo::fields.brand.contact_type.display', 'placeholder' => 'seo::fields.brand.contact_type.placeholder']),
                        $field('telephone', ['type' => 'text', 'display' => 'seo::fields.brand.telephone.display']),
                        $field('email', ['type' => 'text', 'display' => 'seo::fields.brand.email.display']),
                    ]]),
                ]],
                ['display' => 'seo::fields.brand.sections.address.display', 'instructions' => 'seo::fields.brand.sections.address.instructions', 'fields' => [
                    $field('street_address', ['type' => 'text', 'display' => 'seo::fields.brand.street_address.display']),
                    $field('address_locality', ['type' => 'text', 'display' => 'seo::fields.brand.address_locality.display', 'width' => 50]),
                    $field('address_region', ['type' => 'text', 'display' => 'seo::fields.brand.address_region.display', 'width' => 50]),
                    $field('postal_code', ['type' => 'text', 'display' => 'seo::fields.brand.postal_code.display', 'width' => 50]),
                    $field('address_country', ['type' => 'text', 'display' => 'seo::fields.brand.address_country.display', 'width' => 50, 'placeholder' => 'MY, AU, US…']),
                ]],
                ['display' => 'seo::fields.brand.sections.local_business.display', 'instructions' => 'seo::fields.brand.sections.local_business.instructions', 'fields' => [
                    $field('price_range', ['type' => 'text', 'display' => 'seo::fields.brand.price_range.display', 'width' => 33, 'placeholder' => '$$']),
                    $field('latitude', ['type' => 'text', 'display' => 'seo::fields.brand.latitude.display', 'width' => 33]),
                    $field('longitude', ['type' => 'text', 'display' => 'seo::fields.brand.longitude.display', 'width' => 33]),
                    $field('opening_hours', ['type' => 'grid', 'display' => 'seo::fields.brand.opening_hours.display', 'mode' => 'table', 'add_row' => 'seo::fields.brand.opening_hours.add_row', 'fields' => [
                        $field('days', ['type' => 'checkboxes', 'display' => 'seo::fields.brand.days.display', 'inline' => true, 'options' => self::DAYS]),
                        $field('opens', ['type' => 'time', 'display' => 'seo::fields.brand.opens.display']),
                        $field('closes', ['type' => 'time', 'display' => 'seo::fields.brand.closes.display']),
                    ]]),
                ]],
            ]],
            'shop' => ['display' => 'seo::fields.brand.tabs.shop', 'sections' => [
                ['instructions' => 'seo::fields.brand.sections.shop.instructions', 'fields' => [
                    $field('currency', ['type' => 'text', 'display' => 'seo::fields.brand.currency.display', 'width' => 33, 'placeholder' => 'MYR, AUD, USD…', 'instructions' => 'seo::fields.brand.currency.instructions']),
                ]],
                ['display' => 'seo::fields.brand.sections.returns.display', 'fields' => [
                    $field('return_category', ['type' => 'select', 'display' => 'seo::fields.brand.return_category.display', 'width' => 33, 'options' => [
                        'MerchantReturnFiniteReturnWindow' => 'seo::fields.brand.return_category.options.finite_window',
                        'MerchantReturnUnlimitedWindow' => 'seo::fields.brand.return_category.options.unlimited_window',
                        'MerchantReturnNotPermitted' => 'seo::fields.brand.return_category.options.not_permitted',
                    ]]),
                    $field('return_days', ['type' => 'integer', 'display' => 'seo::fields.brand.return_days.display', 'width' => 33, 'if' => ['return_category' => 'equals MerchantReturnFiniteReturnWindow']]),
                    $field('return_country', ['type' => 'text', 'display' => 'seo::fields.brand.return_country.display', 'width' => 33, 'placeholder' => 'MY']),
                    $field('return_policy_link', ['type' => 'text', 'input_type' => 'url', 'display' => 'seo::fields.brand.return_policy_link.display', 'instructions' => 'seo::fields.brand.return_policy_link.instructions']),
                ]],
                ['display' => 'seo::fields.brand.sections.shipping.display', 'fields' => [
                    $field('shipping_rates', ['type' => 'grid', 'display' => 'seo::fields.brand.shipping_rates.display', 'mode' => 'table', 'add_row' => 'seo::fields.brand.shipping_rates.add_row', 'instructions' => 'seo::fields.brand.shipping_rates.instructions', 'fields' => [
                        $field('country', ['type' => 'text', 'display' => 'seo::fields.brand.country.display']),
                        $field('region', ['type' => 'text', 'display' => 'seo::fields.brand.region.display']),
                        $field('min_order', ['type' => 'float', 'display' => 'seo::fields.brand.min_order.display']),
                        $field('max_order', ['type' => 'float', 'display' => 'seo::fields.brand.max_order.display']),
                        $field('rate', ['type' => 'float', 'display' => 'seo::fields.brand.rate.display']),
                        $field('min_days', ['type' => 'integer', 'display' => 'seo::fields.brand.min_days.display']),
                        $field('max_days', ['type' => 'integer', 'display' => 'seo::fields.brand.max_days.display']),
                    ]]),
                ]],
            ]],
            'share_cards' => ['display' => 'seo::fields.brand.tabs.share_cards', 'sections' => [['instructions' => 'seo::fields.brand.sections.share_cards.instructions', 'fields' => [
                $field('og_background', ['type' => 'color', 'display' => 'seo::fields.brand.og_background.display', 'width' => 33]),
                $field('og_text', ['type' => 'color', 'display' => 'seo::fields.brand.og_text.display', 'width' => 33]),
                $field('og_accent', ['type' => 'color', 'display' => 'seo::fields.brand.og_accent.display', 'width' => 33]),
                $field('og_picture', $asset('seo::fields.brand.og_picture.display', 'seo::fields.brand.og_picture.instructions')),
            ]]]],
            'crawlers' => ['display' => 'seo::fields.brand.tabs.crawlers', 'sections' => [['fields' => [
                $field('google_verification', ['type' => 'text', 'display' => 'seo::fields.brand.google_verification.display', 'width' => 50]),
                $field('bing_verification', ['type' => 'text', 'display' => 'seo::fields.brand.bing_verification.display', 'width' => 50]),
                $field('yandex_verification', ['type' => 'text', 'display' => 'seo::fields.brand.yandex_verification.display', 'width' => 50]),
                $field('pinterest_verification', ['type' => 'text', 'display' => 'seo::fields.brand.pinterest_verification.display', 'width' => 50]),
                $field('robots_disallow', ['type' => 'list', 'display' => 'seo::fields.brand.robots_disallow.display', 'instructions' => 'seo::fields.brand.robots_disallow.instructions']),
                $field('allow_ai_training', ['type' => 'toggle', 'display' => 'seo::fields.brand.allow_ai_training.display', 'default' => true, 'width' => 50, 'instructions' => 'seo::fields.brand.allow_ai_training.instructions']),
                $field('allow_ai_search', ['type' => 'toggle', 'display' => 'seo::fields.brand.allow_ai_search.display', 'default' => true, 'width' => 50, 'instructions' => 'seo::fields.brand.allow_ai_search.instructions']),
                $field('robots_extra', ['type' => 'textarea', 'display' => 'seo::fields.brand.robots_extra.display', 'instructions' => 'seo::fields.brand.robots_extra.instructions']),
            ]]]],
        ];
    }
}
