<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use JothamLec\MarketingToolkit\Cp\Listing;
use JothamLec\MarketingToolkit\Reports\Report;
use JothamLec\MarketingToolkit\Reports\ReportPage;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\Runner;
use JothamLec\MarketingToolkit\Reports\RunReportStep;
use JothamLec\MarketingToolkit\Support\Package;
use Statamic\Facades\Addon;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Facades\User;

/**
 * Tools → SEO → Reports: the list, "Run report", one report's checks and
 * pages, and the progress endpoint a running report's screen polls. Without
 * a queue worker that endpoint also does the work for whoever may run
 * reports, one step per request, so a report finishes on the sync queue
 * without any request timing out. On a multi-site install the list, "Run
 * report" and a report's screens are of the selected site.
 *
 * Reports keep their checks' names and messages as translation keys (or
 * plain text, from a site's own checks and older reports); they're
 * translated here, on their way to the screen.
 */
class ReportsController
{
    public function index(): Response
    {
        $addon = Addon::get(Package::NAME);

        return Inertia::render('marketing-toolkit::Reports', [
            'reports' => Report::query()->shownOn(Site::selected()->handle())->latest('id')->limit(50)->get()->map(fn (Report $report) => $this->summary($report))->all(),
            'canRun' => (bool) User::current()?->can('run marketing toolkit reports'),
            'runUrl' => cp_route('mt.reports.run'),
            'settingsUrl' => $addon && User::current()?->can('editSettings', $addon) ? $addon->settingsUrl() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function run(Runner $runner): array
    {
        $report = $runner->start(site: Site::selected()->handle());

        // Only a report this click started gets its queued steps: one already
        // running has its own (a second chain would step it twice over).
        if ($report->wasRecentlyCreated && $report->isRunning() && RunReportStep::usesWorker()) {
            RunReportStep::dispatch($report->id);
        }

        return $this->summary($report);
    }

    /**
     * Where the report stands. Only someone who may run reports moves it on:
     * anyone else watching it reads the progress the runner makes.
     *
     * @return array<string, mixed>
     */
    public function progress(Report $report, Runner $runner): array
    {
        $this->authorizeSite($report);

        if ($report->isRunning() && ! RunReportStep::usesWorker() && User::current()?->can('run marketing toolkit reports')) {
            $report = $runner->step($report);
        }

        return $this->summary($report);
    }

    public function show(Report $report): Response
    {
        $this->authorizeSite($report);

        $rules = collect($report->summary['rules'] ?? [])
            ->map(fn (array $rule, string $handle) => [...$rule, 'label' => __($rule['label']), 'handle' => $handle])
            ->sortByDesc(fn (array $rule) => [$rule['fail'] * $rule['weight'], $rule['warn']])
            ->values()
            ->all();

        return Inertia::render('marketing-toolkit::Report', [
            'report' => $this->summary($report),
            'counts' => array_intersect_key($report->summary ?? [], array_flip(['scored', 'noindex', 'errors'])),
            'rules' => $rules,
            'listingUrl' => cp_route('mt.reports.pages', $report),
            'listUrl' => cp_route('mt.reports.index'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function pages(Report $report, Request $request): array
    {
        $this->authorizeSite($report);

        $labels = collect($report->summary['rules'] ?? [])->map(fn (array $rule) => __($rule['label']))->put('render', __('marketing-toolkit::reports.rules.render'))->all();
        /** @var array<int, string> $editUrls report page id => edit URL, filled by preload */
        $editUrls = [];
        $query = $report->pages()->getQuery();

        // Only a known check's name gets into the LIKE pattern. Its `_` is a LIKE
        // wildcard, but no two checks' names differ only there.
        if (array_key_exists($rule = $request->string('rule')->toString(), $labels)) {
            $query->where('failing', 'like', '%,'.$rule.':%');
        }

        return Listing::respond(
            $query,
            $request,
            ['score' => __('marketing-toolkit::reports.cp.score'), 'title' => __('marketing-toolkit::reports.cp.page'), 'in_sitemap' => __('marketing-toolkit::reports.cp.in_sitemap')],
            ['title', 'url'],
            // Not an arrow function: it must see $editUrls once preload has filled it.
            function (ReportPage $page) use ($labels, &$editUrls) {
                return [
                    'id' => $page->id,
                    'title' => $page->title ?: $page->url,
                    'url' => $page->url,
                    'path' => parse_url($page->url, PHP_URL_PATH) ?: '/',
                    'score' => $page->score,
                    'in_sitemap' => $page->in_sitemap,
                    'noindex' => $page->facts()->noindex(),
                    'issues' => collect($page->results ?? [])
                        ->reject(fn ($result) => $result['status'] === 'pass')
                        ->map(fn ($result, $handle) => [
                            'label' => $labels[$handle] ?? $handle,
                            'status' => $result['status'],
                            'message' => Result::translate($result['message'], $result['params'] ?? []),
                        ])
                        ->sortBy(fn ($issue) => $issue['status'] === 'fail' ? 0 : 1)
                        ->values()
                        ->all(),
                    'edit_url' => $editUrls[$page->id] ?? null,
                ];
            },
            preload: function ($pages) use (&$editUrls) {
                $editUrls = $this->editUrls($pages);
            },
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Report $report): array
    {
        return [
            'id' => $report->id,
            'status' => $report->status,
            'score' => $report->score,
            'pages_total' => $report->pages_total,
            'pages_done' => $report->pages_done,
            'error' => $report->error === null ? null : __($report->error),
            'created_at' => $report->created_at->toIso8601String(),
            'finished_at' => $report->finished_at?->toIso8601String(),
            'url' => cp_route('mt.reports.show', $report),
            'progress_url' => cp_route('mt.reports.progress', $report),
        ];
    }

    /**
     * Where to fix each page, for those the user may edit: one query for the
     * entries and one for the terms.
     *
     * @param  Collection<int, ReportPage>  $pages
     * @return array<int, string> report page id => edit URL
     */
    private function editUrls($pages): array
    {
        $ids = fn (string $type) => $pages->where('content_type', $type)->pluck('content_id')->all();
        $entries = Entry::query()->whereIn('id', $ids('entry'))->get()->keyBy->id();
        // A term is listed once for each of its sites; the first, as Term::find() gives it.
        $terms = Term::query()->whereIn('id', $ids('term'))->get()->unique->id()->keyBy->id();

        return $pages
            ->mapWithKeys(fn (ReportPage $page) => [$page->id => ($page->content_type === 'entry' ? $entries : $terms)->get($page->content_id)])
            ->filter(fn ($content) => $content && User::current()?->can('edit', $content))
            ->map->editUrl()
            ->all();
    }

    /**
     * A report is seen only while its site is selected, as the list shows it:
     * Statamic keeps the selected site one the user may see.
     */
    private function authorizeSite(Report $report): void
    {
        abort_unless($report->isShownOn(Site::selected()->handle()), 404);
    }
}
