<?php

namespace JothamLec\MarketingToolkit\Support;

use Statamic\Facades\Addon;
use Throwable;

/**
 * Pro: the modules a site can switch off under Tools → SEO → Features, kept
 * in the addon settings (`features_off`). A module that's off is set off in
 * the config at boot, before the routes, listeners and middleware register,
 * so it costs nothing on a request. In Free every module runs, as
 * config/seo.php says.
 */
final class Features
{
    /** Module => what turning it off sets in the config. */
    public const array MODULES = [
        'sitemap' => ['seo.sitemap.enabled' => false],
        'robots_txt' => ['seo.robots_txt' => false],
        'llms_txt' => ['seo.llms_txt' => false],
        'ads_txt' => ['seo.ads_txt' => false],
        'hreflang' => ['seo.hreflang.enabled' => false],
        'indexnow' => ['seo.indexnow.enabled' => false],
        'share_cards' => ['seo.og.enabled' => false],
        'redirects' => ['seo.redirects.enabled' => false],
        'automatic_redirects' => ['seo.redirects.automatic' => false],
        'not_found' => ['seo.not_found.enabled' => false],
        'reports' => ['seo.reports.enabled' => false],
        'tracking' => ['seo.tracking.enabled' => false],
        'leads' => ['seo.leads.enabled' => false],
        'favicons' => ['seo.favicons.enabled' => false],
    ];

    public const string SETTING = 'features_off';

    /**
     * The modules switched off, as saved.
     *
     * @return list<string>
     */
    public static function off(): array
    {
        try {
            $saved = Addon::get(Edition::PACKAGE)?->settings()->get(self::SETTING);
        } catch (Throwable) {
            return [];
        }

        return array_values(array_intersect(array_keys(self::MODULES), is_array($saved) ? $saved : []));
    }

    /**
     * Sets the modules that are off, off in the config.
     */
    public static function apply(): void
    {
        foreach (self::off() as $module) {
            config(self::MODULES[$module]);
        }
    }

    /**
     * @param  list<string>  $off
     */
    public static function save(array $off): void
    {
        $settings = Addon::get(Edition::PACKAGE)->settings();
        $settings->set(self::SETTING, array_values(array_intersect(array_keys(self::MODULES), $off)) ?: null);
        $settings->save();
    }
}
