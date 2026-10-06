<?php

namespace JothamLec\MarketingToolkit\Tracking;

use Illuminate\Support\Facades\Vite;
use JothamLec\MarketingToolkit\Settings;
use JothamLec\MarketingToolkit\Support\Edition;

/**
 * The tracking tags and Consent Mode defaults of the current site: IDs from
 * the "Tracking" tab of the SEO & brand global, with config/seo.php (and so
 * .env) winning over it. Printed by <s:seo:head /> and <s:seo:body />, in
 * production only and never in Live Preview.
 *
 * Override a method in a subclass named in `seo.tracking.class`, as with
 * SiteSeo: e.g. ids() to read them from somewhere else.
 */
class Tracking
{
    /** Tracker => what an ID looks like. Anything else is ignored, never printed. */
    public const array PATTERNS = [
        'gtm' => '/^GTM-[A-Z0-9]{4,12}$/',
        'ga4' => '/^G-[A-Z0-9]{4,16}$/',
        'posthog' => '/^phc_[A-Za-z0-9]{16,64}$/',
        'meta' => '/^\d{6,20}$/',
        'linkedin' => '/^\d{3,12}$/',
    ];

    /** Tracker => its config key and its field in the global. */
    public const array FIELDS = [
        'gtm' => ['gtm', 'gtm_id'],
        'ga4' => ['ga4', 'ga4_id'],
        'posthog' => ['posthog_key', 'posthog_key'],
        'meta' => ['meta_pixel', 'meta_pixel_id'],
        'linkedin' => ['linkedin', 'linkedin_partner_id'],
    ];

    /** The four Consent Mode v2 signals. */
    public const array SIGNALS = ['ad_storage', 'analytics_storage', 'ad_user_data', 'ad_personalization'];

    /** The region choice that stands for the EEA, the UK and Switzerland. */
    public const string EEA = 'EEA';

    /** EU, EEA, UK and Switzerland: where consent comes before tracking. */
    public const array EEA_REGIONS = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'GB', 'CH',
    ];

    public function __construct(protected Settings $settings) {}

    /**
     * Whether the tags print on this request: in the environments
     * `seo.tracking.environments` lists (production), never in Live Preview.
     */
    public function enabled(): bool
    {
        return app()->environment((array) config('seo.tracking.environments', ['production']))
            && ! request()->isLivePreview();
    }

    /**
     * Each tracker's ID, or null where it has none (or one that doesn't look
     * like an ID: it is printed into a script).
     *
     * @return array{gtm: ?string, ga4: ?string, posthog: ?string, meta: ?string, linkedin: ?string}
     */
    public function ids(): array
    {
        $ids = [];

        foreach (self::FIELDS as $tracker => [$config, $field]) {
            $id = trim((string) (config('seo.tracking.'.$config) ?: $this->settings->string($field)));
            $id = in_array($tracker, ['gtm', 'ga4'], true) ? strtoupper($id) : $id;
            $ids[$tracker] = preg_match(self::PATTERNS[$tracker], $id) ? $id : null;
        }

        return $ids;
    }

    /**
     * Which trackers come from config/seo.php (or .env), and so can't be
     * changed in the control panel.
     *
     * @return array<string, bool>
     */
    public function fromConfig(): array
    {
        return collect(self::FIELDS)->map(fn (array $keys) => filled(config('seo.tracking.'.$keys[0])))->all();
    }

    /**
     * PostHog's API host: the project's region, `https://us.i.posthog.com` unless set.
     */
    public function posthogHost(): string
    {
        $host = rtrim(trim((string) (config('seo.tracking.posthog_host') ?: $this->settings->string('posthog_host'))), '/');

        return preg_match('#^https://[a-z0-9.-]+(:\d+)?$#i', $host) ? $host : 'https://us.i.posthog.com';
    }

    /**
     * Trackers loaded beside Google Tag Manager, which then counts each visit
     * twice if GTM loads them as well: names, for a warning.
     *
     * @return list<string>
     */
    public function besideGtm(): array
    {
        $ids = $this->ids();

        if ($ids['gtm'] === null) {
            return [];
        }

        return array_map(
            fn (string $tracker) => (string) __('seo::cp.tracking.names.'.$tracker),
            array_keys(array_filter(array_diff_key($ids, ['gtm' => true]))),
        );
    }

    /**
     * Consent Mode v2 defaults, for a cookie banner that updates them; null
     * while Consent Mode is off. Regions (Pro): the defaults apply there, and
     * everything is granted elsewhere.
     *
     * @return array{defaults: array<string, string>, regions: list<string>, wait_for_update: int}|null
     */
    public function consent(): ?array
    {
        if (! $this->settings->bool('consent_mode')) {
            return null;
        }

        $defaults = collect(self::SIGNALS)->mapWithKeys(fn (string $signal) => [
            $signal => $this->settings->string('consent_'.$signal) === 'granted' ? 'granted' : 'denied',
        ])->all();

        return [
            'defaults' => $defaults,
            'regions' => Edition::pro() ? $this->regions() : [],
            'wait_for_update' => max(0, min(10000, (int) ($this->settings->string('consent_wait_for_update') ?? 500))),
        ];
    }

    /**
     * ISO 3166-1 or 3166-2 codes (`FR`, `US-CA`), with EEA standing for the EEA, the UK and Switzerland.
     *
     * @return list<string>
     */
    protected function regions(): array
    {
        return collect($this->settings->list('consent_regions'))
            ->flatMap(fn (string $region) => strtoupper(trim($region)) === self::EEA ? self::EEA_REGIONS : [strtoupper(trim($region))])
            ->filter(fn (string $region) => preg_match('/^[A-Z]{2}(-[A-Z0-9]{1,3})?$/', $region) === 1)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * What goes at the top of the <head>: Consent Mode defaults, then the tags.
     */
    public function head(): string
    {
        return $this->enabled() ? view('seo::tracking-head', $this->viewData())->render() : '';
    }

    /**
     * What goes right after <body>: the tags' <noscript> fallbacks.
     */
    public function body(): string
    {
        return $this->enabled() ? view('seo::tracking-body', $this->viewData())->render() : '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        $ids = $this->ids();
        $consent = $this->consent();

        $attribution = $this->attribution();
        // Trackers that don't read Google's Consent Mode, and the attribution cookie, wait for the banner's answer.
        $bridge = $consent !== null && ($ids['posthog'] || $ids['meta'] || $ids['linkedin'] || $attribution);
        $host = $this->posthogHost();

        return [
            'ids' => $ids,
            'consent' => $consent,
            'consentDefaults' => $this->consentDefaults($consent),
            'bridge' => $bridge,
            'posthogOptions' => array_filter([
                'api_host' => $host,
                // PostHog's own cloud: its app is at the same address without `.i`.
                'ui_host' => str_ends_with($host, '.i.posthog.com') ? str_replace('.i.posthog.com', '.posthog.com', $host) : null,
                'person_profiles' => 'identified_only',
                'opt_out_capturing_by_default' => $bridge ?: null,
                'persistence' => $bridge ? 'memory' : null,
            ]),
            'conversions' => $this->conversions() && array_filter($ids) !== [],
            'linkedinConversion' => $this->linkedinConversion(),
            'attribution' => $attribution,
            'nonce' => Vite::cspNonce(),
        ];
    }

    /**
     * Pro: whether a form submission is sent to the tools as a lead.
     */
    public function conversions(): bool
    {
        return Edition::pro() && $this->settings->bool('conversions', true);
    }

    /**
     * Pro: the LinkedIn conversion a form submission counts as, if any.
     */
    public function linkedinConversion(): ?string
    {
        $id = trim((string) $this->settings->string('linkedin_conversion_id'));

        return $this->conversions() && preg_match('/^\d{3,12}$/', $id) ? $id : null;
    }

    /**
     * Pro: whether to remember where each visitor first came from, for their submissions.
     */
    public function attribution(): bool
    {
        return Edition::pro() && $this->settings->bool('attribution');
    }

    /**
     * The gtag('consent', 'default', …) commands: the defaults everywhere,
     * or, with regions, everything granted and the defaults in those regions.
     *
     * @param  array{defaults: array<string, string>, regions: list<string>, wait_for_update: int}|null  $consent
     * @return list<array<string, mixed>>
     */
    protected function consentDefaults(?array $consent): array
    {
        if ($consent === null) {
            return [];
        }

        $wait = ['wait_for_update' => $consent['wait_for_update']];

        return $consent['regions'] === []
            ? [[...$consent['defaults'], ...$wait]]
            : [array_fill_keys(self::SIGNALS, 'granted'), [...$consent['defaults'], 'region' => $consent['regions'], ...$wait]];
    }
}
