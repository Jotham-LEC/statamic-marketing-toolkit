<?php

namespace JothamLec\Seo\Http\Controllers\CP;

use Inertia\Inertia;
use Inertia\Response;
use JothamLec\Seo\NotFound\MissingPath;
use JothamLec\Seo\Redirects\Redirect;
use JothamLec\Seo\Reports\Report;
use JothamLec\Seo\SearchConsole\Client;
use JothamLec\Seo\SearchConsole\Connection;
use JothamLec\Seo\SearchConsole\SearchStat;
use JothamLec\Seo\SiteSeo;
use JothamLec\Seo\Support\Sites;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\Addon;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\User;

/**
 * Tools → SEO: where the site stands (the latest report, redirects, recent
 * 404s, the brand defaults, the files it serves), each with a way into the
 * screen that changes it. Links the person may not use are left out. On a
 * multi-site install, all of it for the site selected in the control panel.
 */
class OverviewController
{
    public function __invoke(SiteSeo $seo, Client $searchConsole): Response
    {
        $user = User::current();
        abort_unless($user?->can('view seo'), 403);

        $site = Site::selected()->handle();

        // The selected site's brand values and addresses, not the control panel's domain's.
        return Sites::as($site, fn () => $this->render($seo, $searchConsole, $user, $site));
    }

    private function render(SiteSeo $seo, Client $searchConsole, UserContract $user, string $site): Response
    {
        $variables = GlobalSet::findByHandle((string) config('seo.global'))?->in($site);
        $addon = Addon::get('jotham-lec/statamic-co-seo');
        $latest = Report::query()->shownOn($site)->where('status', Report::DONE)->latest('id')->first();
        $redirects = fn () => Redirect::query()->where('active', true)->when(Sites::multiple(), fn ($query) => $query->appliesOn($site));

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
                'active' => $redirects()->count(),
                'automatic' => $redirects()->where('automatic', true)->count(),
                'url' => cp_route('seo.redirects.index'),
            ] : null,
            'notFound' => [
                'paths' => MissingPath::query()->shownOn($site)->count(),
                'recent' => MissingPath::query()->shownOn($site)->latest('last_seen_at')->limit(5)->get()
                    ->map(fn (MissingPath $row) => ['path' => $row->path, 'hits' => $row->hits])->all(),
                'url' => cp_route('seo.404s.index'),
            ],
            'search' => $this->search($searchConsole),
            'searchSetup' => $this->searchSetup($searchConsole, $user->can('editSettings', $addon)),
            // On the site's own address, which can differ from the control panel's.
            'files' => collect([
                'Sitemap' => config('seo.sitemap.enabled') ? 'sitemap.xml' : null,
                'robots.txt' => config('seo.robots_txt') ? 'robots.txt' : null,
                'Home share card' => config('seo.og.enabled') ? 'og.png' : null,
            ])->filter()->map(fn ($path, $label) => ['label' => $label, 'url' => $seo->absolute($path)])->values(),
        ]);
    }

    /**
     * Where Search Console's setup stands, for the steps on Tools → SEO.
     * Without permission to change it, only whether it is connected.
     *
     * @return array<string, mixed>
     */
    private function searchSetup(Client $client, bool $canSetUp): array
    {
        $connection = new Connection;

        return [
            'configured' => $client->configured(),
            'can_set_up' => $canSetUp,
            'email' => $canSetUp ? $connection->email() : null,
            'key_source' => $connection->keySource(),
            'property' => $canSetUp ? config('seo.search_console.property') : null,
            'property_source' => $connection->propertySource(),
            'suggested_property' => $connection->suggestedProperty(),
            'urls' => $canSetUp ? [
                'key' => cp_route('seo.search-console.key'),
                'forget_key' => cp_route('seo.search-console.key.forget'),
                'property' => cp_route('seo.search-console.property'),
                'check' => cp_route('seo.search-console.check'),
                'import' => cp_route('seo.search-console.import'),
            ] : null,
        ];
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
