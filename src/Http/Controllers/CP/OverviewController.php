<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Inertia\Inertia;
use Inertia\Response;
use JothamLec\MarketingToolkit\Favicons\Favicons;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Og\Generator;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use JothamLec\MarketingToolkit\Reports\Report;
use JothamLec\MarketingToolkit\SearchConsole\Client;
use JothamLec\MarketingToolkit\SearchConsole\SearchStat;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Package;
use JothamLec\MarketingToolkit\Support\Sites;
use JothamLec\MarketingToolkit\Tracking\Tracking;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\Addon;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\User;

/**
 * Marketing → Overview: where the site stands (the latest report, redirects, recent
 * 404s, the brand defaults, the files it serves), each with a way into the
 * screen that changes it. Links the person may not use are left out. On a
 * multi-site install, all of it for the site selected in the control panel.
 */
class OverviewController
{
    public function __invoke(SiteSeo $seo, Client $searchConsole): Response
    {
        $user = User::current();

        $site = Sites::multiple() ? Site::selected()->handle() : Site::default()->handle();

        // The selected site's brand values and addresses, not the control panel's domain's.
        return Sites::as($site, fn () => $this->render($seo, $searchConsole, $user, $site));
    }

    private function render(SiteSeo $seo, Client $searchConsole, UserContract $user, string $site): Response
    {
        $variables = GlobalSet::findByHandle((string) config('marketing-toolkit.global'))?->in($site);
        // The set the Tracking tab is in: Marketing settings, or Brand on a site that hasn't moved it yet.
        $tracking = GlobalSet::findByHandle((string) config('marketing-toolkit.settings_global'))?->in($site) ?? $variables;
        $redirects = fn () => Redirect::query()->where('active', true)->when(Sites::multiple(), fn ($query) => $query->appliesOn($site));

        return Inertia::render('marketing-toolkit::Overview', [
            'siteName' => $seo->settings()->siteName(),
            'global' => [
                'exists' => $variables !== null,
                'url' => $variables && $user->can('edit', $variables) ? $variables->editUrl() : null,
                'separator' => $seo->settings()->titleSiteName() ? $seo->settings()->separator() : null,
                'description' => $seo->settings()->string('default_description'),
            ],
            'report' => $this->report($user, $site),
            'redirects' => $user->can('manage marketing toolkit redirects') ? [
                'active' => $redirects()->count(),
                'automatic' => $redirects()->where('automatic', true)->count(),
                'url' => cp_route('mt.redirects.index'),
            ] : null,
            'notFound' => [
                'paths' => MissingPath::query()->shownOn($site)->count(),
                'recent' => MissingPath::recent($site),
                'url' => cp_route('mt.404s.index'),
            ],
            'search' => $this->search($searchConsole, $site),
            'searchConsole' => ['url' => cp_route('mt.search-console.index')],
            // On the site's own address, which can differ from the control panel's.
            'tracking' => $this->tracking($tracking && $user->can('edit', $tracking) ? $tracking->editUrl() : null),
            // From the domain's root, where the web server and the addon's routes serve them, also for a site under a folder.
            'files' => collect([
                __('marketing-toolkit::cp.overview.files.sitemap') => config('marketing-toolkit.sitemap.enabled') ? '/sitemap.xml' : null,
                __('marketing-toolkit::cp.overview.files.robots') => config('marketing-toolkit.robots_txt.enabled') ? '/robots.txt' : null,
                __('marketing-toolkit::cp.overview.files.llms') => config('marketing-toolkit.llms_txt.enabled') ? '/llms.txt' : null,
                __('marketing-toolkit::cp.overview.files.favicon') => config('marketing-toolkit.favicons.enabled') && app(Favicons::class)->version() ? '/site.webmanifest' : null,
                __('marketing-toolkit::cp.overview.files.card') => config('marketing-toolkit.og.enabled') && app(Generator::class)->available() ? '/og.png' : null,
            ])->filter()->map(fn ($path, $label) => [
                'label' => $label,
                'url' => $seo->absolute($path),
                // The web server answers with this file instead of the addon's.
                'public' => file_exists(public_path(ltrim($path, '/'))),
            ])->values(),
        ]);
    }

    /**
     * The trackers set, each with where it comes from, and those loaded beside GTM.
     *
     * @return array<string, mixed>
     */
    private function tracking(?string $url): array
    {
        $tracking = app(Tracking::class);
        // From .env when the config holds it; an ID a Tracking subclass returns comes from code.
        $fromEnv = fn (string $tracker, string $id) => strcasecmp(trim((string) config('marketing-toolkit.tracking.'.Tracking::FIELDS[$tracker])), $id) === 0;

        return [
            'tools' => collect($tracking->ids())->filter()->map(fn (string $id, string $tracker) => [
                'name' => __('marketing-toolkit::cp.tracking.names.'.$tracker),
                'id' => $id,
                'from_env' => $fromEnv($tracker, $id),
            ])->values()->all(),
            // Set, but not an ID, so never printed: where to fix it.
            'invalid' => collect($tracking->invalid())->map(fn (string $value, string $tracker) => __('marketing-toolkit::cp.tracking.invalid', [
                'name' => __('marketing-toolkit::cp.tracking.names.'.$tracker),
                'value' => $value,
                'where' => $fromEnv($tracker, $value) ? 'MT_'.strtoupper(Tracking::FIELDS[$tracker]) : __('marketing-toolkit::cp.tracking.where_global'),
            ]))->values()->all(),
            'consent' => $tracking->consent() !== null,
            'overlap' => $tracking->besideGtm(),
            'url' => $url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function report(UserContract $user, string $site): array
    {
        $latest = Report::latestDone($site);

        return [
            'latest' => $latest === null ? null : [
                'score' => (int) $latest->score,
                'pages' => $latest->scoredPages(),
                'finished_at' => $latest->finished_at?->toIso8601String(),
                'url' => cp_route('mt.reports.show', $latest),
                // The checks most pages fail, beside the gauge.
                'checks' => collect($latest->summary['rules'] ?? [])
                    ->filter(fn (array $rule) => ($rule['fail'] ?? 0) > 0)
                    ->sortByDesc(fn (array $rule) => [$rule['fail'] * ($rule['weight'] ?? 1), $rule['fail']])
                    ->take(5)
                    ->map(fn (array $rule) => ['label' => __($rule['label']), 'fail' => (int) $rule['fail']])
                    ->values()
                    ->all(),
            ],
            'url' => cp_route('mt.reports.index'),
            // The Settings tab of Reports.
            'settings_url' => Package::canEditSettings() ? cp_route('mt.reports.index').'#settings' : null,
        ];
    }

    /**
     * Search Console's numbers, once it is set up: the totals for the
     * period and the pages with the most clicks.
     *
     * @return array<string, mixed>|null
     */
    private function search(Client $client, string $site): ?array
    {
        if (! $client->configured($site)) {
            return null;
        }

        $stats = fn () => SearchStat::query()->shownOn($site);
        $first = $stats()->first();

        return [
            'fetched_at' => $first?->fetched_at?->toIso8601String(),
            'from' => $first?->from?->toDateString(),
            'to' => $first?->to?->toDateString(),
            'clicks' => (int) $stats()->sum('clicks'),
            'impressions' => (int) $stats()->sum('impressions'),
            'top' => $stats()->orderByDesc('clicks')->orderByDesc('impressions')->limit(5)->get()
                ->map(fn (SearchStat $row) => [
                    'path' => parse_url($row->url, PHP_URL_PATH) ?: '/',
                    'clicks' => $row->clicks,
                    'impressions' => $row->impressions,
                    'position' => round($row->position, 1),
                ])->all(),
        ];
    }
}
