<?php

namespace JothamLec\Seo\Http\Controllers\CP;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use JothamLec\Seo\Cp\Listing;
use JothamLec\Seo\Reports\Report;
use JothamLec\Seo\Reports\ReportPage;
use JothamLec\Seo\Reports\Runner;
use JothamLec\Seo\Reports\RunReportStep;
use Statamic\Facades\Addon;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Statamic\Facades\User;

/**
 * Tools → SEO → Reports: the list, "Run report", one report's checks and
 * pages, and the progress endpoint a running report's screen polls. Without
 * a queue worker that endpoint also does the work, one step per request, so
 * a report finishes on the sync queue without any request timing out.
 */
class ReportsController
{
    public function index(): Response
    {
        $this->authorize('view seo');

        $addon = Addon::get('jotham-lec/statamic-seo');

        return Inertia::render('seo::Reports', [
            'reports' => Report::query()->latest('id')->limit(50)->get()->map(fn (Report $report) => $this->summary($report))->all(),
            'canRun' => (bool) User::current()?->can('run seo reports'),
            'runUrl' => cp_route('seo.reports.run'),
            'settingsUrl' => $addon && User::current()?->can('editSettings', $addon) ? $addon->settingsUrl() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function run(Runner $runner): array
    {
        $this->authorize('run seo reports');

        $report = $runner->start();

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
            ->map(fn (array $rule, string $handle) => [...$rule, 'handle' => $handle])
            ->sortByDesc(fn (array $rule) => [$rule['fail'] * $rule['weight'], $rule['warn']])
            ->values()
            ->all();

        return Inertia::render('seo::Report', [
            'report' => $this->summary($report),
            'counts' => array_intersect_key($report->summary ?? [], array_flip(['scored', 'noindex', 'errors'])),
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

        $labels = collect($report->summary['rules'] ?? [])->map->label->put('render', 'Page renders')->all();
        $query = $report->pages()->getQuery();

        // Only a known check's name gets into the LIKE pattern, so no escaping is needed.
        if (array_key_exists($rule = $request->string('rule')->toString(), $labels)) {
            $query->where('failing', 'like', '%,'.$rule.':%');
        }

        return Listing::respond(
            $query,
            $request,
            ['score' => 'Score', 'title' => 'Page', 'in_sitemap' => 'In sitemap'],
            ['title', 'url'],
            fn (ReportPage $page) => [
                'id' => $page->id,
                'title' => $page->title ?: $page->url,
                'url' => $page->url,
                'path' => parse_url($page->url, PHP_URL_PATH) ?: '/',
                'score' => $page->score,
                'in_sitemap' => $page->in_sitemap,
                'noindex' => $page->facts()->noindex(),
                'issues' => collect($page->results ?? [])
                    ->reject(fn ($result) => $result['status'] === 'pass')
                    ->map(fn ($result, $handle) => ['label' => $labels[$handle] ?? $handle, ...$result])
                    ->sortBy(fn ($issue) => $issue['status'] === 'fail' ? 0 : 1)
                    ->values()
                    ->all(),
                'edit_url' => $this->editUrl($page),
            ],
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
            'error' => $report->error,
            'created_at' => $report->created_at->toIso8601String(),
            'finished_at' => $report->finished_at?->toIso8601String(),
            'url' => cp_route('seo.reports.show', $report),
            'progress_url' => cp_route('seo.reports.progress', $report),
        ];
    }

    private function editUrl(ReportPage $page): ?string
    {
        $content = $page->content_type === 'entry' ? Entry::find($page->content_id) : Term::find($page->content_id);

        return $content && User::current()?->can('edit', $content) ? $content->editUrl() : null;
    }

    private function authorize(string $permission): void
    {
        abort_unless(User::current()?->can($permission), 403);
    }
}
