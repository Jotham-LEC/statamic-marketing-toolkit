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
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Facades\User;

/**
 * Tools → SEO → Link check: the list, "Check now", one report's checks and
 * the pages with issues, and the progress endpoint a running report's screen polls. Without
 * a queue worker that endpoint also does the work, one step per request, so
 * a report finishes on the sync queue without any request timing out. On a
 * multi-site install the list and "Run report" are of the selected site.
 *
 * Reports keep their checks' names and messages as translation keys (or
 * plain text, from a site's own checks and older reports); they're
 * translated here, on their way to the screen.
 */
class ReportsController
{
    public function index(): Response
    {
        $this->authorize('view seo');

        return Inertia::render('seo::Reports', [
            'reports' => Report::query()->shownOn(Site::selected()->handle())->latest('id')->limit(50)->get()->map(fn (Report $report) => $this->summary($report))->all(),
            'canRun' => (bool) User::current()?->can('run seo reports'),
            'runUrl' => cp_route('seo.reports.run'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function run(Runner $runner): array
    {
        $this->authorize('run seo reports');

        $report = $runner->start(site: Site::selected()->handle());

        if ($report->isRunning() && $report->pages_done === 0 && RunReportStep::usesWorker()) {
            RunReportStep::dispatch($report->id);
        }

        return $this->summary($report);
    }

    /**
     * @return array<string, mixed>
     */
    public function progress(Report $report, Runner $runner): array
    {
        $this->authorize('view seo');

        if ($report->isRunning() && ! RunReportStep::usesWorker()) {
            $report = $runner->step($report);
        }

        return $this->summary($report);
    }

    public function show(Report $report, Runner $runner): Response
    {
        $this->authorize('view seo');

        $rules = collect($report->summary['rules'] ?? [])
            ->map(fn (array $rule, string $handle) => [...$rule, 'label' => __($rule['label']), 'handle' => $handle])
            ->sortByDesc(fn (array $rule) => [$rule['fail'], $rule['warn']])
            ->values()
            ->all();

        return Inertia::render('seo::Report', [
            'report' => $this->summary($report),
            'counts' => [
                'checked' => (int) ($report->summary['checked'] ?? 0),
                'noindex' => (int) ($report->summary['noindex'] ?? 0),
                'errors' => (int) ($report->summary['errors'] ?? 0),
                'with_issues' => (int) ($report->summary['with_issues'] ?? 0),
            ],
            'rules' => $rules,
            'listingUrl' => cp_route('seo.reports.pages', $report),
            'listUrl' => cp_route('seo.reports.index'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function pages(Report $report, Request $request): array
    {
        $this->authorize('view seo');

        $labels = collect($report->summary['rules'] ?? [])->map(fn (array $rule) => __($rule['label']))->put('render', __('seo::reports.rules.render'))->all();
        /** @var array<int, string> $editUrls report page id => edit URL, filled by preload */
        $editUrls = [];
        // Only the pages with something to fix.
        $query = $report->pages()->getQuery()->whereNotNull('failing');

        // Only a known check's name gets into the LIKE pattern. Its `_` is a LIKE
        // wildcard, but no two checks' names differ only there.
        if (array_key_exists($rule = $request->string('rule')->toString(), $labels)) {
            $query->where('failing', 'like', '%,'.$rule.':%');
        }

        return Listing::respond(
            $query,
            $request,
            ['title' => __('seo::reports.cp.page')],
            ['title', 'url'],
            // Not an arrow function: it must see $editUrls once preload has filled it.
            function (ReportPage $page) use ($labels, &$editUrls) {
                return [
                    'id' => $page->id,
                    'title' => $page->title ?: $page->url,
                    'url' => $page->url,
                    'path' => parse_url($page->url, PHP_URL_PATH) ?: '/',
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
            'with_issues' => $report->summary['with_issues'] ?? null,
            'pages_total' => $report->pages_total,
            'pages_done' => $report->pages_done,
            'error' => $report->error === null ? null : __($report->error),
            'created_at' => $report->created_at->toIso8601String(),
            'finished_at' => $report->finished_at?->toIso8601String(),
            'url' => cp_route('seo.reports.show', $report),
            'progress_url' => cp_route('seo.reports.progress', $report),
        ];
    }

    /**
     * Where to fix each page, for those the user may edit: one query for the entries.
     *
     * @param  Collection<int, ReportPage>  $pages
     * @return array<int, string> report page id => edit URL
     */
    private function editUrls($pages): array
    {
        $entries = Entry::query()->whereIn('id', $pages->where('content_type', 'entry')->pluck('content_id')->all())->get()->keyBy->id();

        return $pages
            ->mapWithKeys(fn (ReportPage $page) => [$page->id => $page->content_type === 'entry' ? $entries->get($page->content_id) : Term::find($page->content_id)])
            ->filter(fn ($content) => $content && User::current()?->can('edit', $content))
            ->map->editUrl()
            ->all();
    }

    private function authorize(string $permission): void
    {
        abort_unless(User::current()?->can($permission), 403);
    }
}
