<?php

namespace JothamLec\MarketingToolkit\Tracking;

use Illuminate\Support\Facades\Vite;
use JothamLec\MarketingToolkit\Settings;
use JothamLec\MarketingToolkit\Support\Features;

/**
 * The tracking tags of the current site, and its Consent Mode defaults: IDs from
 * the "Tracking" tab of the Marketing settings global, with config/marketing-toolkit.php (and so
 * .env) winning over it. Printed by <s:mt:head /> and <s:mt:body />, in
 * production only and never in Live Preview.
 *
 * Override a method in a subclass bound in its place in the container:
 * e.g. ids() to read them from somewhere else.
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

    /**
     * Tracker => its key: the field's handle in the global's Tracking tab, the
     * key under `marketing-toolkit.tracking`, and in .env MT_ followed by the key in capitals.
     */
    public const array FIELDS = [
        'gtm' => 'gtm_id',
        'ga4' => 'ga4_id',
        'posthog' => 'posthog_key',
        'meta' => 'meta_pixel_id',
        'linkedin' => 'linkedin_partner_id',
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
     * `marketing-toolkit.tracking.environments` lists (production), never in Live Preview.
     */
    public function enabled(): bool
    {
        return Features::on('tracking')
            && app()->environment((array) config('marketing-toolkit.tracking.environments'))
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
        return collect($this->entered())->map(fn (?string $id, string $tracker) => $id !== null && preg_match(self::PATTERNS[$tracker], $id) ? $id : null)->all();
    }

    /**
     * Trackers with something entered that isn't an ID, and so isn't printed:
     * tracker => what was entered.
     *
     * @return array<string, string>
     */
    public function invalid(): array
    {
        return array_diff_key(array_filter($this->entered(), fn (?string $id) => $id !== null), array_filter($this->ids()));
    }

    /**
     * Which trackers come from config/marketing-toolkit.php (or .env), and so can't be
     * changed in the control panel.
     *
     * @return array<string, bool>
     */
    public function fromConfig(): array
    {
        return collect(self::FIELDS)->map(fn (string $key) => filled(config('marketing-toolkit.tracking.'.$key)))->all();
    }

    /**
     * What each tracker has, from the config or else the global, tidied but not checked.
     *
     * @return array<string, ?string>
     */
    protected function entered(): array
    {
        return collect(self::FIELDS)->map(function (string $key, string $tracker) {
            $id = trim((string) (config('marketing-toolkit.tracking.'.$key) ?: $this->settings->string($key)));

            return $id === '' ? null : (in_array($tracker, ['gtm', 'ga4'], true) ? strtoupper($id) : $id);
        })->all();
    }

    /**
     * PostHog's API host: the project's region, `https://us.i.posthog.com` unless set.
     */
    public function posthogHost(): string
    {
        $host = rtrim(trim((string) (config('marketing-toolkit.tracking.posthog_host') ?: $this->settings->string('posthog_host'))), '/');

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
            fn (string $tracker) => (string) __('marketing-toolkit::cp.tracking.names.'.$tracker),
            array_keys(array_filter(array_diff_key($ids, ['gtm' => true]))),
        );
    }

    /**
     * Consent Mode v2 defaults, for a cookie banner that updates them;
     * null while Consent Mode is off. With regions, the
     * defaults apply there, and everything is granted elsewhere.
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
            'regions' => $this->regions(),
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
     * What goes high in the <head>, after <meta charset>: Consent Mode defaults, then the tags.
     */
    public function head(): string
    {
        return $this->enabled() ? view('marketing-toolkit::tracking-head', $this->viewData())->render() : '';
    }

    /**
     * What goes right after <body>: the tags' <noscript> fallbacks.
     */
    public function body(): string
    {
        return $this->enabled() ? view('marketing-toolkit::tracking-body', $this->viewData())->render() : '';
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
            // Where PostHog's snippet loads its library from, for the bridge to load it once analytics is granted.
            'posthogScript' => str_replace('.i.posthog.com', '-assets.i.posthog.com', $host).'/static/array.js',
            'conversions' => $this->conversions() && array_filter($ids) !== [],
            'linkedinConversion' => $this->linkedinConversion(),
            'attribution' => $attribution,
            'nonce' => Vite::cspNonce(),
        ];
    }

    /**
     * Whether a form submission is sent to the tools as a lead.
     */
    public function conversions(): bool
    {
        return Features::on('leads') && $this->settings->bool('conversions', true);
    }

    /**
     * The LinkedIn conversion a form submission counts as, if any.
     */
    public function linkedinConversion(): ?string
    {
        $id = trim((string) $this->settings->string('linkedin_conversion_id'));

        return $this->conversions() && preg_match(self::PATTERNS['linkedin'], $id) ? $id : null;
    }

    /**
     * Whether to remember where each visitor first came from, for their submissions.
     */
    public function attribution(): bool
    {
        return Features::on('leads') && $this->settings->bool('attribution');
    }

    /**
     * The gtag('consent', 'default', …) commands: the defaults everywhere,
     * or, with regions, everything granted and the defaults in those regions.
     * Both wait for the banner: a visitor outside the regions who declined
     * is denied by its update, which must arrive before the first hit.
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
            : [[...array_fill_keys(self::SIGNALS, 'granted'), ...$wait], [...$consent['defaults'], 'region' => $consent['regions'], ...$wait]];
    }
}
