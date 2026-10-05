<?php

namespace JothamLec\Seo\Cp;

use Statamic\CP\Navigation\Nav;
use Statamic\CP\Navigation\NavItem;
use Statamic\Facades\CP\Nav as NavFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

/**
 * Tools → SEO in the control panel. Its children are the screens that exist:
 * the brand global here; redirects, 404s and reports join as they land.
 */
class Navigation
{
    public static function register(): void
    {
        NavFacade::extend(fn (Nav $nav) => $nav->tools('SEO')
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

        return array_values(array_filter([
            $variables ? $nav->item('Brand & defaults')->url($variables->editUrl())->can('edit', $variables) : null,
        ]));
    }
}
