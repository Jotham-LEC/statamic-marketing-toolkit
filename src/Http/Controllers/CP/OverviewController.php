<?php

namespace JothamLec\Seo\Http\Controllers\CP;

use Inertia\Inertia;
use Inertia\Response;
use JothamLec\Seo\SiteSeo;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\User;

/**
 * Tools → SEO: what the addon serves on this site, and where each part is edited.
 */
class OverviewController
{
    public function __invoke(SiteSeo $seo): Response
    {
        abort_unless(User::current()?->can('view seo'), 403);

        $variables = GlobalSet::findByHandle((string) config('seo.global'))?->in(Site::selected()->handle());

        return Inertia::render('seo::Overview', [
            'siteName' => $seo->settings()->siteName(),
            'global' => [
                'exists' => $variables !== null,
                'url' => $variables && User::current()->can('edit', $variables) ? $variables->editUrl() : null,
            ],
            // On the site's own address, which can differ from the control panel's.
            'files' => collect([
                'Sitemap' => config('seo.sitemap.enabled') ? 'sitemap.xml' : null,
                'robots.txt' => config('seo.robots_txt') ? 'robots.txt' : null,
                'humans.txt' => config('seo.humans_txt') ? 'humans.txt' : null,
                'Home share card' => config('seo.og.enabled') ? 'og.png' : null,
            ])->filter()->map(fn ($path, $label) => ['label' => $label, 'url' => $seo->absolute($path)])->values(),
        ]);
    }
}
