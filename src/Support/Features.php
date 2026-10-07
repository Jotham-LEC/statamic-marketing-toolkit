<?php

namespace JothamLec\MarketingToolkit\Support;

use Statamic\Facades\Addon;
use Throwable;

/**
 * The modules, each switched on by one config key. Pro: a site can switch
 * them off under Tools → SEO → Features, kept in the addon settings
 * (`features_off`). Free: Pro's modules are off, and the rest run as
 * config/marketing-toolkit.php says. A module that's off is set off in the config at boot,
 * before the routes, listeners and middleware register, so it costs nothing
 * on a request.
 */
final class Features
{
    /** Module => the config key that switches it on. */
    public const array MODULES = [
        'sitemap' => 'marketing-toolkit.sitemap.enabled',
        'robots_txt' => 'marketing-toolkit.robots_txt.enabled',
        'llms_txt' => 'marketing-toolkit.llms_txt.enabled',
        'ads_txt' => 'marketing-toolkit.ads_txt.enabled',
        'hreflang' => 'marketing-toolkit.hreflang.enabled',
        'indexnow' => 'marketing-toolkit.indexnow.enabled',
        'share_cards' => 'marketing-toolkit.og.enabled',
        'redirects' => 'marketing-toolkit.redirects.enabled',
        'automatic_redirects' => 'marketing-toolkit.redirects.automatic',
        'not_found' => 'marketing-toolkit.not_found.enabled',
        'reports' => 'marketing-toolkit.reports.enabled',
        'tracking' => 'marketing-toolkit.tracking.enabled',
        'leads' => 'marketing-toolkit.leads.enabled',
        'favicons' => 'marketing-toolkit.favicons.enabled',
    ];

    /** The modules only Pro has. Their data stays in the database, ready for an upgrade. */
    public const array PRO = ['share_cards', 'automatic_redirects', 'not_found', 'reports', 'leads'];

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
        } catch (Throwable $exception) {
            // Asked while booting: logged, but never in the way of it.
            rescue(fn () => report($exception), report: false);

            return [];
        }

        return array_values(array_intersect(array_keys(self::MODULES), is_array($saved) ? $saved : []));
    }

    /**
     * The modules config/marketing-toolkit.php switches off, rather than this screen: shown
     * off there, and locked, since a switch can't turn them back on.
     *
     * @return list<string>
     */
    public static function offInConfig(): array
    {
        $off = self::off();

        return array_values(array_filter(array_keys(self::MODULES), fn (string $module) => ! in_array($module, $off, true)
            && ! config(self::MODULES[$module], true)));
    }

    /**
     * Sets the modules that are off, off in the config: those switched off in
     * Pro, Pro's own in Free. At every boot rather than in the merged config,
     * which isn't merged once it is cached.
     */
    public static function apply(): void
    {
        foreach (Edition::pro() ? self::off() : self::PRO as $module) {
            config([self::MODULES[$module] => false]);
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
