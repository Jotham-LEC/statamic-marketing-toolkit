<?php

namespace JothamLec\MarketingToolkit\Cp;

use JothamLec\MarketingToolkit\Support\Edition;
use Statamic\CP\Navigation\Nav;
use Statamic\CP\Navigation\NavItem;
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
        NavFacade::extend(fn (Nav $nav) => $nav->tools(__('seo::cp.seo'))
            ->route('seo.index')
            ->icon('search-magnifying-glass')
            ->can('view seo')
            ->children(fn () => self::children($nav)));
    }

    /**
     * @return list<NavItem>
     */
    private static function children(Nav $nav): array
    {
        $variables = GlobalSet::findByHandle((string) config('seo.global'))?->in(Site::selected()->handle());

        $pro = Edition::pro();

        return array_values(array_filter([
            $pro ? $nav->item(__('seo::cp.nav.reports'))->route('seo.reports.index')->can('view seo') : null,
            $nav->item(__('seo::cp.nav.redirects'))->route('seo.redirects.index')->can('manage seo redirects'),
            $pro ? $nav->item(__('seo::cp.nav.not_found'))->route('seo.404s.index')->can('view seo') : null,
            $pro ? $nav->item(__('seo::cp.nav.search_console'))->route('seo.search-console.index')->can('view seo') : null,
            $variables ? $nav->item(__('seo::cp.nav.brand'))->url($variables->editUrl())->can('edit', $variables) : null,
        ]));
    }
}
