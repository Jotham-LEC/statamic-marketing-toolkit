<?php

namespace JothamLec\MarketingToolkit\Cp;

use JothamLec\MarketingToolkit\Support\Edition;
use Statamic\CP\Navigation\Nav;
use Statamic\CP\Navigation\NavItem;
use Statamic\Facades\Addon;
use Statamic\Facades\CP\Nav as NavFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

/**
 * Tools → SEO in the control panel, with its screens as children.
 */
class Navigation
{
    public static function register(): void
    {
        NavFacade::extend(fn (Nav $nav) => $nav->tools(__('marketing-toolkit::cp.seo'))
            ->route('mt.index')
            ->icon('search-magnifying-glass')
            ->can('view marketing toolkit')
            ->children(fn () => self::children($nav)));
    }

    /**
     * @return list<NavItem>
     */
    private static function children(Nav $nav): array
    {
        $variables = GlobalSet::findByHandle((string) config('marketing-toolkit.global'))?->in(Site::selected()->handle());

        $addon = Addon::get(Edition::PACKAGE);
        $pro = Edition::pro();

        return array_values(array_filter([
            $pro ? $nav->item(__('marketing-toolkit::cp.nav.reports'))->route('mt.reports.index')->can('view marketing toolkit') : null,
            $nav->item(__('marketing-toolkit::cp.nav.redirects'))->route('mt.redirects.index')->can('manage marketing toolkit redirects'),
            $pro ? $nav->item(__('marketing-toolkit::cp.nav.not_found'))->route('mt.404s.index')->can('view marketing toolkit') : null,
            $pro ? $nav->item(__('marketing-toolkit::cp.nav.search_console'))->route('mt.search-console.index')->can('view marketing toolkit') : null,
            $variables ? $nav->item(__('marketing-toolkit::cp.nav.brand'))->url($variables->editUrl())->can('edit', $variables) : null,
            $pro && $addon?->hasSettingsBlueprint() ? $nav->item(__('marketing-toolkit::cp.nav.report_settings'))->url($addon->settingsUrl())->can('editSettings', $addon) : null,
            $pro ? $nav->item(__('marketing-toolkit::cp.nav.features'))->route('mt.features.index')->can('editSettings', $addon) : null,
        ]));
    }
}
