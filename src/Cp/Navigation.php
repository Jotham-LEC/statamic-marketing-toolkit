<?php

namespace JothamLec\MarketingToolkit\Cp;

use JothamLec\MarketingToolkit\Support\Permissions;
use Statamic\CP\Navigation\Nav;
use Statamic\Facades\CP\Nav as NavFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

/**
 * The Marketing section of the control panel's nav, between Fields and
 * Tools: the overview, reports, redirects, 404s and Search Console, then the
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

            $overview = $nav->create(__('marketing-toolkit::cp.nav.overview'))->section($section)->route('mt.index')->icon('megaphone')->can(Permissions::VIEW);
            // Its address starts every other screen's, which would light it up on all of them: only its own.
            (fn () => $this->active = 'marketing-toolkit$')->call($overview);
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

            self::placeAfterFields($nav, $section);
        });
    }

    /**
     * The section's place in the sidebar: after Fields, before Tools. A
     * section shows where its first item was added, so the section's items
     * move to just after the last item of Fields. Someone who reorders the
     * sidebar in their preferences keeps their order.
     */
    private static function placeAfterFields(Nav $nav, string $section): void
    {
        (function () use ($section) {
            $ours = array_filter($this->items, fn ($item) => $item->section() === $section);
            $rest = array_values(array_filter($this->items, fn ($item) => $item->section() !== $section));
            $fields = array_keys(array_filter($rest, fn ($item) => $item->section() === 'Fields'));

            if ($fields !== []) {
                array_splice($rest, end($fields) + 1, 0, array_values($ours));
                $this->items = $rest;
            }
        })->call($nav);
    }
}
