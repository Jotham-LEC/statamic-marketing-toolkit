<?php

namespace JothamLec\MarketingToolkit\Cp;

use JothamLec\MarketingToolkit\Support\Permissions;
use Statamic\CP\Navigation\Nav;
use Statamic\Facades\CP\Nav as NavFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

/**
 * Registers the Marketing section of the control panel's nav, which comes after
 * Statamic's own sections (each user can move it under Preferences → Nav). It
 * lists the overview, reports, redirects, 404s and Search Console, and then the
 * Brand and Marketing settings global sets (Features is a tab of Settings).
 */
class Navigation
{
    public static function register(): void
    {
        NavFacade::extend(function (Nav $nav) {
            $section = __('marketing-toolkit::cp.seo');
            [$brand, $settings] = array_map(
                fn (string $handle) => GlobalSet::findByHandle($handle)?->in(Site::selected()->handle()),
                [(string) config('marketing-toolkit.global'), (string) config('marketing-toolkit.settings_global')],
            );

            // The overview is at /marketing-toolkit/overview, so it isn't highlighted on the section's other screens.
            $nav->create(__('marketing-toolkit::cp.nav.overview'))->section($section)->route('mt.index')->icon('megaphone')->can(Permissions::VIEW);
            $nav->create(__('marketing-toolkit::cp.nav.reports'))->section($section)->route('mt.reports.index')->icon('charts-donut-graph')->can(Permissions::VIEW);
            $nav->create(__('marketing-toolkit::cp.nav.redirects'))->section($section)->route('mt.redirects.index')->icon('moved')->can(Permissions::REDIRECTS);
            $nav->create(__('marketing-toolkit::cp.nav.not_found'))->section($section)->route('mt.404s.index')->icon('warning-diamond')->can(Permissions::VIEW);
            $nav->create(__('marketing-toolkit::cp.nav.search_console'))->section($section)->route('mt.search-console.index')->icon('search-magnifying-glass')->can(Permissions::VIEW);

            if ($brand) {
                $nav->create(__('marketing-toolkit::cp.nav.brand'))->section($section)->url($brand->editUrl())->icon('palette')->can('edit', $brand);
            }

            if ($settings) {
                $nav->create(__('marketing-toolkit::cp.nav.settings'))->section($section)->url($settings->editUrl())->icon('cog')->can('edit', $settings);
            }
        });
    }
}
