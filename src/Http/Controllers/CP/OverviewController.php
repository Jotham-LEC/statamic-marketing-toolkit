<?php

namespace JothamLec\Seo\Http\Controllers\CP;

use Inertia\Inertia;
use Inertia\Response;
use JothamLec\Seo\NotFound\MissingPath;
use JothamLec\Seo\Redirects\Redirect;
use JothamLec\Seo\Reports\Report;
use JothamLec\Seo\SearchConsole\Client;
use JothamLec\Seo\SearchConsole\SearchStat;
use JothamLec\Seo\SiteSeo;
use Statamic\Facades\Addon;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\User;

/**
 * Tools → SEO: where the site stands (the latest report, redirects, recent
 * 404s, the brand defaults, the files it serves), each with a way into the
 * screen that changes it. Links the person may not use are left out.
 */
class OverviewController
{
    public function __invoke(SiteSeo $seo, Client $searchConsole): Response
    {
        $user = User::current();
        abort_unless($user?->can('view seo'), 403);

        $variables = GlobalSet::findByHandle((string) config('seo.global'))?->in(Site::selected()->handle());
        $addon = Addon::get('jotham-lec/statamic-co-seo');
        $latest = Report::query()->where('status', Report::DONE)->latest('id')->first();

        return Inertia::render('seo::Overview', [
            'siteName' => $seo->settings()->siteName(),
            'global' => [
                'exists' => $variables !== null,
                'url' => $variables && $user->can('edit', $variables) ? $variables->editUrl() : null,
                'separator' => $seo->settings()->separator(),
                'description' => $seo->settings()->string('default_description'),
            ],
            'report' => [
                'latest' => $latest === null ? null : [
                    'score' => (int) $latest->score,
                    'pages' => (int) ($latest->summary['scored'] ?? $latest->pages_total),
                    'finished_at' => $latest->finished_at?->toIso8601String(),
                    'url' => cp_route('seo.reports.show', $latest),
                ],
                'url' => cp_route('seo.reports.index'),
                'settings_url' => $addon?->hasSettingsBlueprint() && $user->can('editSettings', $addon) ? $addon->settingsUrl() : null,
            ],
            'redirects' => $user->can('manage seo redirects') ? [
                'active' => Redirect::query()->where('active', true)->count(),
                'automatic' => Redirect::query()->where('active', true)->where('automatic', true)->count(),
                'url' => cp_route('seo.redirects.index'),
            ] : null,
            'notFound' => [
                'paths' => MissingPath::query()->count(),
                'recent' => MissingPath::query()->latest('last_seen_at')->limit(5)->get()
                    ->map(fn (MissingPath $row) => ['path' => $row->path, 'hits' => $row->hits])->all(),
                'url' => cp_route('seo.404s.index'),
            ],
            'search' => $this->search($searchConsole),
            // On the site's own address, which can differ from the control panel's.
            'files' => collect([
                'Sitemap' => config('seo.sitemap.enabled') ? 'sitemap.xml' : null,
                'robots.txt' => config('seo.robots_txt') ? 'robots.txt' : null,
                'Home share card' => config('seo.og.enabled') ? 'og.png' : null,
            ])->filter()->map(fn ($path, $label) => ['label' => $label, 'url' => $seo->absolute($path)])->values(),
        ]);
    }

    /**
     * Search Console's numbers, once it is set up: the totals for the
     * period and the pages with the most clicks.
     *
     * @return array<string, mixed>|null
     */
    private function search(Client $client): ?array
    {
        if (! $client->configured()) {
            return null;
        }

        $first = SearchStat::query()->first();

        return [
            'fetched_at' => $first?->fetched_at?->toIso8601String(),
            'from' => $first?->from?->toDateString(),
            'to' => $first?->to?->toDateString(),
            'clicks' => (int) SearchStat::query()->sum('clicks'),
            'impressions' => (int) SearchStat::query()->sum('impressions'),
            'top' => SearchStat::query()->orderByDesc('clicks')->orderByDesc('impressions')->limit(5)->get()
                ->map(fn (SearchStat $row) => [
                    'path' => parse_url($row->url, PHP_URL_PATH) ?: '/',
                    'clicks' => $row->clicks,
                    'impressions' => $row->impressions,
                    'position' => round($row->position, 1),
                ])->all(),
        ];
    }
}
