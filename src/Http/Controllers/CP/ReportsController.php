<?php

namespace JothamLec\MarketingToolkit\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
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
use JothamLec\MarketingToolkit\Support\Permissions;
use Statamic\Facades\Addon;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Fields\Field;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Marketing → Reports: the list, "Run report", one report's checks and
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
final class ReportsController
{
    public function index(): Response
    {
        $addon = Addon::get(Package::NAME);
        $fields = Package::canEditSettings() ? $addon->settingsBlueprint()->fields()->addValues($addon->settings()->raw())->preProcess() : null;

        return Inertia::render('marketing-toolkit::Reports', [
            'reports' => Report::query()->shownOn(Site::selected()->handle())->latest('id')->limit(50)->get()->map(fn (Report $report) => $this->summary($report))->all(),
            'canRun' => (bool) User::current()?->can(Permissions::REPORTS),
            'runUrl' => cp_route('mt.reports.run'),
            // The Settings tab, for whoever may change the addon's settings.
            'settings' => $fields ? [
                'blueprint' => $addon->settingsBlueprint()->toPublishArray(),
                'values' => $fields->values()->all(),
                'meta' => $fields->meta()->all(),
                'submitUrl' => cp_route('mt.reports.settings'),
            ] : null,
        ]);
    }

    /**
     * Saves the Settings tab. Only its own fields are set: the addon's
     * settings also keep the Features switches and the Search Console setup,
     * hidden fields in the blueprint that their own screens change, so a
     * stale copy of them in this form must not overwrite them.
     */
    public function saveSettings(Request $request): JsonResponse
    {
        abort_unless(Package::canEditSettings(), 403);

        $addon = Addon::get(Package::NAME);
        $fields = $addon->settingsBlueprint()->fields()->addValues($request->all());
        $fields->validate();

        $settings = $addon->settings();

        $own = $fields->all()->reject(fn (Field $field) => $field->visibility() === 'hidden')->keys()->all();

        foreach ($fields->process()->values()->only($own)->all() as $key => $value) {
            $settings->set($key, $value);
        }

        $settings->save();

        return response()->json(['saved' => true]);
    }

    /**
     * A report's pages as CSV: each page's address, title and score, with the
     * checks it failed and those it only warns about.
     */
    public function export(Report $report): StreamedResponse
    {
        $this->authorizeSite($report);

        $labels = $this->labels($report);
        $names = fn (ReportPage $page, string $status) => collect($page->results ?? [])
            ->filter(fn ($result) => $result['status'] === $status)
            ->keys()
            ->map(fn (string $handle) => $labels[$handle] ?? $handle)
            ->implode('; ');

        return response()->streamDownload(function () use ($report, $names) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map(fn (string $column) => __('marketing-toolkit::reports.cp.csv.'.$column), ['url', 'title', 'score', 'failed', 'warnings']), escape: '');

            $report->pages()->orderBy('score')->orderBy('url')->each(function (ReportPage $page) use ($out, $names) {
                fputcsv($out, array_map(self::cell(...), [$page->url, $page->title, $page->score, $names($page, 'fail'), $names($page, 'warn')]), escape: '');
            });

            fclose($out);
        }, 'report-'.$report->id.'-'.$report->created_at->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A cell a spreadsheet won't run as a formula: page titles come from the
     * pages themselves.
     */
    private static function cell(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
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

        // Without a worker, whoever may run reports moves it on; with one, a step
        // whose worker died is queued again once the report has stood still.
        if ($report->isRunning() && User::current()?->can(Permissions::REPORTS)) {
            RunReportStep::usesWorker() ? $runner->resumeIfStalled($report) : $report = $runner->step($report);
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
            // Every count, also for a report that failed before it had any.
            'counts' => [...['scored' => 0, 'noindex' => 0, 'errors' => 0], ...array_intersect_key($report->summary ?? [], array_flip(['scored', 'noindex', 'errors']))],
            'rules' => $rules,
            'listingUrl' => cp_route('mt.reports.pages', $report),
            'listUrl' => cp_route('mt.reports.index'),
            'exportUrl' => cp_route('mt.reports.export', $report),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function pages(Report $report, Request $request): array
    {
        $this->authorizeSite($report);

        $labels = $this->labels($report);
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
            'finished_at' => $report->finished_at?->toIso8601String(),
            'url' => cp_route('mt.reports.show', $report),
            'progress_url' => cp_route('mt.reports.progress', $report),
            // Whether watching it moves it on: false for a running report on the
            // sync queue watched by someone who may not run reports (see progress()).
            'advancing' => $report->isRunning() && (RunReportStep::usesWorker() || (bool) User::current()?->can(Permissions::REPORTS)),
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

    /**
     * Each check's label, by its handle, and the render failure's.
     *
     * @return array<string, string>
     */
    private function labels(Report $report): array
    {
        return collect($report->summary['rules'] ?? [])
            ->map(fn (array $rule) => __($rule['label']))
            ->put('render', __('marketing-toolkit::reports.rules.render'))
            ->all();
    }
}
