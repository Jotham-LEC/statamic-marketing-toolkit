<?php

namespace JothamLec\MarketingToolkit\Reports;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use JothamLec\MarketingToolkit\Reports\Rules\BrokenLinks;
use JothamLec\MarketingToolkit\Reports\Rules\Canonical;
use JothamLec\MarketingToolkit\Reports\Rules\DescriptionLength;
use JothamLec\MarketingToolkit\Reports\Rules\DescriptionUnique;
use JothamLec\MarketingToolkit\Reports\Rules\ExternalLinks;
use JothamLec\MarketingToolkit\Reports\Rules\ImageAlt;
use JothamLec\MarketingToolkit\Reports\Rules\JsonLd;
use JothamLec\MarketingToolkit\Reports\Rules\NoindexInSitemap;
use JothamLec\MarketingToolkit\Reports\Rules\OgImage;
use JothamLec\MarketingToolkit\Reports\Rules\OrphanPages;
use JothamLec\MarketingToolkit\Reports\Rules\Rule;
use JothamLec\MarketingToolkit\Reports\Rules\SingleH1;
use JothamLec\MarketingToolkit\Reports\Rules\TitleLength;
use JothamLec\MarketingToolkit\Reports\Rules\TitleUnique;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Term;

/**
 * Runs reports. start() lists the pages, and step() renders the next chunk of
 * them and reads what each one says. The last step runs the checks, which need
 * every page (to find repeated titles), scores the pages and the site, and
 * drops the reports beyond the number to keep.
 *
 * A report covers one site. On a multi-site install, each report runs with its
 * site as Statamic's current one, so the pages, the sitemap, the brand global
 * and the links it follows are that site's.
 */
class Runner
{
    /** @var list<class-string<Rule>> */
    public const array RULES = [
        TitleLength::class, TitleUnique::class, DescriptionLength::class, DescriptionUnique::class,
        SingleH1::class, Canonical::class, NoindexInSitemap::class, ImageAlt::class,
        BrokenLinks::class, OgImage::class, JsonLd::class, OrphanPages::class, ExternalLinks::class,
    ];

    /** A running report that hasn't moved for this long is taken to have died. */
    private const int STALE_MINUTES = 30;

    /**
     * A step takes no new page after this many seconds and leaves the rest of
     * its chunk to the next step. This way, slow pages, or slow sites they link
     * to, can't run a step past the queue job's timeout (RunReportStep::$timeout),
     * which stays below the 90 seconds Laravel's queues wait by default before
     * they hand a job to another worker (`retry_after`).
     */
    public const int STEP_SECONDS = 45;

    /**
     * The number of seconds that the lock held by a running step lasts. It outlasts
     * the job's timeout, so a second step can't start while the first one still runs.
     */
    private const int STEP_LOCK_SECONDS = 120;

    /**
     * A report on a queue worker that hasn't moved for this long has lost its
     * step (because a worker was killed outright), and resumeIfStalled() queues
     * one. This is longer than the step's timeout, so a step still running has
     * been stopped by then.
     */
    private const int RESUME_MINUTES = 15;

    public function __construct(private Renderer $renderer, private HtmlInspector $inspector, private SiteSeo $seo, private ExternalLinkChecker $externalLinks) {}

    /**
     * Starts a new report of the site, or returns the one already running. Only
     * one start runs at a time per site, because a click and the schedule at the
     * same moment would each find nothing running.
     *
     * @param  ?string  $site  a site handle, or null for the current site
     */
    public function start(?ReportSettings $settings = null, ?string $site = null): Report
    {
        $site = Sites::scope($site ?? Site::current()->handle());

        return Cache::lock('mt:reports:start'.($site === null ? '' : ':'.$site), 120)
            ->block(30, fn () => Sites::as($site, fn () => $this->startOrJoin($settings, $site)));
    }

    private function startOrJoin(?ReportSettings $settings, ?string $site): Report
    {
        $running = Report::query()->ofSite($site)->where('status', Report::RUNNING)->latest('id')->first();

        if ($running && $running->updated_at->gt(now()->subMinutes(self::STALE_MINUTES))) {
            return $running;
        }

        $running?->update(['status' => Report::FAILED, 'error' => 'marketing-toolkit::reports.messages.stopped', 'finished_at' => now()]);

        $settings ??= app(ReportSettings::class);
        $sitemap = $this->seo->sitemapUrls()->pluck('loc')->flip();
        $targets = $this->targets($settings);

        $report = Report::query()->create(['site' => $site, 'settings' => $settings->all(), 'pages_total' => $targets->count()]);

        $targets->chunk(500)->each(fn (Collection $chunk) => ReportPage::query()->insert($chunk->map(fn (array $page) => [
            ...$page,
            'report_id' => $report->id,
            'in_sitemap' => $sitemap->has($page['url']),
        ])->values()->all()));

        if ($targets->isEmpty()) {
            $this->finish($report);
        }

        return $report->refresh();
    }

    /**
     * Renders and reads the next chunk of pages, and finishes the report after
     * the last one. Only one step runs at a time per report, because a progress
     * request, a queued step and a second click may come together and would check
     * the same pages twice. While another step runs, this one leaves the report as it is.
     */
    public function step(Report $report): Report
    {
        if (! $report->isRunning()) {
            return $report;
        }

        $stepped = self::stepLock($report, self::STEP_LOCK_SECONDS)->get(fn () => $this->stepIfRunning($report));

        // False means another process holds the step, so the report is as that process leaves it.
        return $stepped ?: $report->refresh();
    }

    /**
     * Steps the report if it is still running once the lock is held, because a
     * step that just ended may have finished it.
     */
    private function stepIfRunning(Report $report): Report
    {
        if (! $report->refresh()->isRunning()) {
            return $report;
        }

        return $this->onReportSite($report, fn () => $this->checkNextPages($report));
    }

    /**
     * Runs $work with the report's site as the current one, so the pages, the
     * sitemap, the Brand global and the links it follows are that site's.
     *
     * @template T
     *
     * @param  \Closure(): T  $work
     * @return T
     */
    private function onReportSite(Report $report, \Closure $work): mixed
    {
        return Sites::as($report->site, $work);
    }

    /**
     * Renders and reads the next chunk of pages, then finishes the report if
     * none are left.
     */
    private function checkNextPages(Report $report): Report
    {
        $pages = $report->pages()->where('checked', false)->orderBy('id')->limit(max(1, $report->settings()->int('chunk_size')))->get();
        $until = now()->addSeconds(self::STEP_SECONDS);

        foreach ($pages as $index => $page) {
            if ($index > 0 && now()->gte($until)) {
                break;
            }

            $content = $this->content($page);

            if ($content === null) {
                $facts = new PageFacts(status: 404, error: 'marketing-toolkit::reports.messages.page_deleted');
            } else {
                $rendered = $this->renderer->render($content);
                $facts = $rendered->ok()
                    ? $this->inspector->inspect($rendered->html)
                    : new PageFacts(status: $rendered->status, error: $rendered->error, exception: $rendered->exception);
            }

            $broken = $report->settings()->ruleEnabled(ExternalLinks::handle()) && $facts->externalLinks !== []
                ? $this->externalLinks->broken($facts->externalLinks)
                : [];

            $facts = new PageFacts(...[...$facts->toArray(), 'inSitemap' => $page->in_sitemap, 'brokenExternalLinks' => $broken]);

            $page->update(['checked' => true, 'facts' => $facts->toArray(), 'title' => self::title($facts->title) ?? $page->title]);
        }

        $report->update(['pages_done' => $report->pages()->where('checked', true)->count()]);

        if ($pages->isEmpty() || ! $report->pages()->where('checked', false)->exists()) {
            $this->finish($report);
        }

        return $report->refresh();
    }

    /**
     * Marks a running report as failed, which is how a step that failed for good
     * leaves it. The message is generic, because the error itself is in the log.
     */
    public function fail(Report $report): void
    {
        Report::query()->whereKey($report->id)->where('status', Report::RUNNING)
            ->update(['status' => Report::FAILED, 'error' => 'marketing-toolkit::reports.messages.failed', 'finished_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Queues the next step of a report on a queue worker that has stood
     * still for RESUME_MINUTES with no step running, as when a worker was
     * killed mid-step and so queued nothing. The progress request calls this,
     * since the control panel polls it while the report is open. It queues a step
     * at most once per RESUME_MINUTES, however many requests are polling.
     */
    public function resumeIfStalled(Report $report): bool
    {
        if (! $report->isRunning() || ! RunReportStep::usesWorker() || $report->updated_at->gt(now()->subMinutes(self::RESUME_MINUTES))) {
            return false;
        }

        $step = self::stepLock($report, 1);

        if (! $step->get()) {
            return false;
        }

        $step->release();

        if (! Cache::add('mt:reports:resume:'.$report->id, true, now()->addMinutes(self::RESUME_MINUTES))) {
            return false;
        }

        RunReportStep::dispatch($report->id);

        return true;
    }

    /**
     * Gets the lock that is held while a step of the report runs.
     */
    private static function stepLock(Report $report, int $seconds): Lock
    {
        return Cache::lock('mt:reports:step:'.$report->id, $seconds);
    }

    /**
     * Runs every remaining step, for the command line and the scheduler. While
     * another process holds the step, this waits a moment before asking again.
     *
     * @param  (callable(Report): void)|null  $progress
     */
    public function runToEnd(Report $report, ?callable $progress = null): Report
    {
        while ($report->isRunning()) {
            $done = $report->pages_done;
            $report = $this->step($report);

            if ($report->isRunning() && $report->pages_done === $done) {
                Sleep::for(250)->milliseconds();
            }

            if ($progress) {
                $progress($report);
            }
        }

        return $report;
    }

    /**
     * @return list<Rule>
     */
    public function rules(ReportSettings $settings): array
    {
        return array_values(array_filter(
            array_map(fn (string $class) => app($class), self::RULES),
            fn (Rule $rule) => $settings->ruleEnabled($rule::handle()),
        ));
    }

    public function finish(Report $report): void
    {
        $this->onReportSite($report, fn () => $this->finishInSite($report));
    }

    private function finishInSite(Report $report): void
    {
        $settings = $report->settings();
        $rules = $this->rules($settings);
        $site = new SiteFacts($settings);

        $report->pages()->select(['id', 'url', 'facts'])->lazyById(200)->each(function (ReportPage $page) use ($site) {
            $facts = $page->facts();

            if ($facts->rendered() && ! $facts->noindex()) {
                $site->add($page->url, $facts);
            }
        });

        $totals = new SiteTotals($rules);

        $report->pages()->lazyById(200)->each(fn (ReportPage $page) => $this->score($page, $rules, $site, $totals));

        // Only a running report is updated, so one marked failed meanwhile (by a step that timed out) stays failed.
        Report::query()->whereKey($report->id)->where('status', Report::RUNNING)->update([
            'status' => Report::DONE,
            'score' => $totals->score(),
            'summary' => json_encode($totals->summary()),
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        $this->prune($settings->int('keep_reports'), $report->site);
    }

    /**
     * Runs the checks on one page, stores its results and score, and adds
     * them to the site's totals. A page that didn't render fails only the
     * `render` check and scores zero.
     *
     * @param  list<Rule>  $rules
     */
    private function score(ReportPage $page, array $rules, SiteFacts $site, SiteTotals $totals): void
    {
        $facts = $page->facts();

        if (! $facts->rendered()) {
            $result = match (true) {
                $facts->error === null => Result::fail('marketing-toolkit::reports.messages.status', ['status' => $facts->status]),
                $facts->exception !== null => Result::fail($facts->error, ['exception' => $facts->exception]),
                default => Result::fail($facts->error),
            };
            $page->update(['results' => ['render' => $result->toArray()], 'score' => 0, 'failing' => ',render:fail,']);
            $totals->addError();

            return;
        }

        $results = [];
        $earned = $possible = 0;

        foreach ($rules as $rule) {
            if ($facts->noindex() && ! $rule->appliesToNoindex()) {
                continue;
            }

            $result = $rule->check($page->url, $facts, $site);
            $results[$rule::handle()] = $result->toArray();
            $earned += $rule->weight() * $result->value();
            $possible += $rule->weight();
        }

        // A page that search engines are told to skip is listed but not scored.
        $score = $facts->noindex() || $possible === 0 ? null : (int) round(100 * $earned / $possible);
        $totals->addPage($results, $score, $facts->noindex());

        $flagged = collect($results)->reject(fn ($result) => $result['status'] === Result::PASS)
            ->map(fn ($result, $handle) => $handle.':'.$result['status'])->implode(',');

        $page->update(['results' => $results, 'score' => $score, 'failing' => $flagged === '' ? null : ','.$flagged.',']);
    }

    private function prune(int $keep, ?string $site): void
    {
        $stale = Report::query()->ofSite($site)->where('status', '!=', Report::RUNNING)->orderByDesc('id')->skip(max(1, $keep))->take(PHP_INT_MAX)->pluck('id');

        // The pages are deleted first, because SQLite only cascades when foreign keys are switched on.
        ReportPage::query()->whereIn('report_id', $stale)->delete();
        Report::query()->whereIn('id', $stale)->delete();
    }

    /**
     * Lists the published entries and terms that have an address, in the order
     * they're checked, as the rows of the report's pages. Protected pages are left
     * out, as they are from the sitemap, because they aren't public and rendering
     * one only answers with the way to sign in. Entries are read in chunks and
     * kept as rows, so a big site's entries needn't all be in memory.
     *
     * @return Collection<int, array{url: string, content_type: string, content_id: string, title: string}>
     */
    private function targets(ReportSettings $settings): Collection
    {
        $site = Site::current()->handle();
        $excluded = $settings->excludedCollections();

        $entries = Entry::query()->where('site', $site)->whereStatus('published')->orderBy('id')->lazy(500)
            ->filter(fn (EntryContract $entry) => ! in_array($entry->collectionHandle(), $excluded, true) && $entry->url() && ! $entry->isRedirect() && ! $this->seo->isProtected($entry))
            ->map(fn (EntryContract $entry) => $this->row($entry))
            ->collect()
            ->sortBy('url');

        $terms = collect((array) config('marketing-toolkit.sitemap.taxonomies'))
            ->flatMap(fn (string $taxonomy): Collection => Term::query()->where('taxonomy', $taxonomy)->where('site', $site)->get())
            ->map(fn ($term) => $term->in($site))
            ->filter(fn ($term) => $term?->url() && $this->seo->termHasEntries($term) && ! $this->seo->isProtected($term))
            ->map(fn (TermContract $term) => $this->row($term))
            ->sortBy('url');

        $targets = $entries->values()->merge($terms->values());
        $max = $settings->int('max_pages');

        return $max > 0 ? $targets->take($max)->values() : $targets;
    }

    /**
     * @return array{url: string, content_type: string, content_id: string, title: string}
     */
    private function row(EntryContract|TermContract $content): array
    {
        return [
            'url' => (string) $content->absoluteUrl(),
            'content_type' => $content instanceof EntryContract ? 'entry' : 'term',
            'content_id' => (string) $content->id(),
            'title' => (string) self::title((string) $content->value('title')),
        ];
    }

    /**
     * Cuts a title to the length its column holds, because MySQL (in strict mode)
     * and Postgres refuse more than 255 characters, which would stop the report.
     */
    private static function title(?string $title): ?string
    {
        return $title === null ? null : mb_substr($title, 0, 255);
    }

    private function content(ReportPage $page): EntryContract|TermContract|null
    {
        $content = $page->content_type === 'entry'
            ? Entry::find($page->content_id)
            : Term::find($page->content_id)?->in(Site::current()->handle());

        return $content && ($content instanceof TermContract || $content->status() === 'published') ? $content : null;
    }
}
