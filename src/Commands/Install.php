<?php

namespace JothamLec\MarketingToolkit\Commands;

use Illuminate\Console\Command;
use JothamLec\MarketingToolkit\Conversions\Attribution;
use JothamLec\MarketingToolkit\Tracking\Tracking;
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
     * The blueprint keeps translation keys (lang/en/fields.php), not English,
     * so the control panel shows it in each user's language.
     *
     * @return array<string, mixed>
     */
    public static function tabs(string $container): array
    {
        $field = fn (string $handle, array $config) => ['handle' => $handle, 'field' => $config];
        $consent = fn (string $display) => ['type' => 'button_group', 'display' => $display, 'width' => 50, 'default' => 'denied', 'if' => ['consent_mode' => 'true'], 'options' => [
            'denied' => 'seo::fields.brand.consent_value.options.denied',
            'granted' => 'seo::fields.brand.consent_value.options.granted',
        ]];
        $asset = fn (string $display, string $instructions = '') => ['type' => 'assets', 'display' => $display, 'container' => $container, 'max_files' => 1, 'instructions' => $instructions];

        return [
            'brand' => ['display' => 'seo::fields.brand.tabs.brand', 'sections' => [['fields' => [
                $field('title_site_name', ['type' => 'toggle', 'display' => 'seo::fields.brand.title_site_name.display', 'width' => 50, 'instructions' => 'seo::fields.brand.title_site_name.instructions']),
                $field('title_separator', ['type' => 'text', 'display' => 'seo::fields.brand.title_separator.display', 'width' => 50, 'placeholder' => '·', 'if' => ['title_site_name' => 'true'], 'instructions' => 'seo::fields.brand.title_separator.instructions']),
                $field('default_description', ['type' => 'textarea', 'display' => 'seo::fields.brand.default_description.display', 'character_limit' => 160, 'instructions' => 'seo::fields.brand.default_description.instructions']),
                $field('default_image', $asset('seo::fields.brand.default_image.display', 'seo::fields.brand.default_image.instructions')),
                $field('site_alternate_name', ['type' => 'text', 'display' => 'seo::fields.brand.site_alternate_name.display', 'width' => 50, 'instructions' => 'seo::fields.brand.site_alternate_name.instructions']),
                $field('twitter_handle', ['type' => 'text', 'display' => 'seo::fields.brand.twitter_handle.display', 'width' => 50, 'prepend' => '@']),
            ]], ['display' => 'seo::fields.brand.sections.icon.display', 'instructions' => 'seo::fields.brand.sections.icon.instructions', 'fields' => [
                $field('favicon', $asset('seo::fields.brand.favicon.display', 'seo::fields.brand.favicon.instructions')),
                $field('theme_color', ['type' => 'color', 'display' => 'seo::fields.brand.theme_color.display', 'width' => 50, 'instructions' => 'seo::fields.brand.theme_color.instructions']),
                $field('background_color', ['type' => 'color', 'display' => 'seo::fields.brand.background_color.display', 'width' => 50, 'instructions' => 'seo::fields.brand.background_color.instructions']),
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
            'tracking' => ['display' => 'seo::fields.brand.tabs.tracking', 'sections' => [
                ['instructions' => 'seo::fields.brand.sections.tracking.instructions', 'fields' => [
                    $field('tracking_overlap', ['type' => 'section', 'display' => 'seo::fields.brand.tracking_overlap.display', 'instructions' => 'seo::fields.brand.tracking_overlap.instructions', 'if' => 'seoTrackingOverlap']),
                    $field('gtm_id', ['type' => 'text', 'display' => 'seo::fields.brand.gtm_id.display', 'width' => 50, 'placeholder' => 'GTM-XXXXXXX', 'instructions' => 'seo::fields.brand.gtm_id.instructions', 'validate' => ['nullable', 'regex:/^GTM-[A-Z0-9]{4,12}$/i']]),
                    $field('ga4_id', ['type' => 'text', 'display' => 'seo::fields.brand.ga4_id.display', 'width' => 50, 'placeholder' => 'G-XXXXXXXXXX', 'validate' => ['nullable', 'regex:/^G-[A-Z0-9]{4,16}$/i']]),
                    $field('posthog_key', ['type' => 'text', 'display' => 'seo::fields.brand.posthog_key.display', 'width' => 50, 'placeholder' => 'phc_…', 'validate' => ['nullable', 'regex:/^phc_[A-Za-z0-9]{16,64}$/']]),
                    $field('posthog_host', ['type' => 'text', 'input_type' => 'url', 'display' => 'seo::fields.brand.posthog_host.display', 'width' => 50, 'placeholder' => 'https://us.i.posthog.com', 'instructions' => 'seo::fields.brand.posthog_host.instructions']),
                    $field('meta_pixel_id', ['type' => 'text', 'display' => 'seo::fields.brand.meta_pixel_id.display', 'width' => 50, 'validate' => ['nullable', 'regex:/^\d{6,20}$/']]),
                    $field('linkedin_partner_id', ['type' => 'text', 'display' => 'seo::fields.brand.linkedin_partner_id.display', 'width' => 50, 'validate' => ['nullable', 'regex:/^\d{3,12}$/']]),
                ]],
                ['display' => 'seo::fields.brand.sections.consent.display', 'instructions' => 'seo::fields.brand.sections.consent.instructions', 'fields' => [
                    $field('consent_mode', ['type' => 'toggle', 'display' => 'seo::fields.brand.consent_mode.display', 'instructions' => 'seo::fields.brand.consent_mode.instructions']),
                    $field('consent_ad_storage', $consent('seo::fields.brand.consent_ad_storage.display')),
                    $field('consent_analytics_storage', $consent('seo::fields.brand.consent_analytics_storage.display')),
                    $field('consent_ad_user_data', $consent('seo::fields.brand.consent_ad_user_data.display')),
                    $field('consent_ad_personalization', $consent('seo::fields.brand.consent_ad_personalization.display')),
                    $field('consent_wait_for_update', ['type' => 'integer', 'display' => 'seo::fields.brand.consent_wait_for_update.display', 'width' => 50, 'default' => 500, 'append' => 'ms', 'if' => ['consent_mode' => 'true'], 'instructions' => 'seo::fields.brand.consent_wait_for_update.instructions']),
                    $field('consent_regions', ['type' => 'select', 'display' => 'seo::fields.brand.consent_regions.display', 'width' => 50, 'multiple' => true, 'taggable' => true, 'options' => [Tracking::EEA => 'seo::fields.brand.consent_regions.options.eea'], 'if' => ['consent_mode' => 'true'], 'instructions' => 'seo::fields.brand.consent_regions.instructions']),
                ]],
                ['display' => 'seo::fields.brand.sections.conversions.display', 'instructions' => 'seo::fields.brand.sections.conversions.instructions', 'fields' => [
                    $field('conversions', ['type' => 'toggle', 'display' => 'seo::fields.brand.conversions.display', 'default' => true, 'width' => 50, 'instructions' => 'seo::fields.brand.conversions.instructions']),
                    $field('linkedin_conversion_id', ['type' => 'text', 'display' => 'seo::fields.brand.linkedin_conversion_id.display', 'width' => 50, 'if' => ['conversions' => 'true'], 'validate' => ['nullable', 'regex:/^\d{3,12}$/']]),
                    $field('attribution', ['type' => 'toggle', 'display' => 'seo::fields.brand.attribution.display', 'width' => 50, 'instructions' => 'seo::fields.brand.attribution.instructions']),
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
                $field('ads_txt', ['type' => 'textarea', 'display' => 'seo::fields.brand.ads_txt.display', 'instructions' => 'seo::fields.brand.ads_txt.instructions']),
            ]]]],
        ];
    }
}
