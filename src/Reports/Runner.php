<?php

namespace JothamLec\MarketingToolkit\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JothamLec\MarketingToolkit\Reports\Rules\BrokenLinks;
use JothamLec\MarketingToolkit\Reports\Rules\Description;
use JothamLec\MarketingToolkit\Reports\Rules\ExternalLinks;
use JothamLec\MarketingToolkit\Reports\Rules\OgImage;
use JothamLec\MarketingToolkit\Reports\Rules\Rule;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Term;

/**
 * Runs the link check. start() lists the pages; step() renders the next
 * chunk of them and reads what each says (checking its links to other sites
 * as it goes); the last step runs the checks, counts the pages with issues,
 * and drops reports beyond the number to keep.
 *
 * A report is of one site: on a multi-site install each runs with its site
 * as Statamic's current one, so the pages, the sitemap, the brand global and
 * the links it follows are that site's.
 */
class Runner
{
    /** @var list<class-string<Rule>> */
    public const array RULES = [BrokenLinks::class, ExternalLinks::class, Description::class, OgImage::class];

    /** A running report that hasn't moved for this long is taken to have died. */
    private const int STALE_MINUTES = 30;

    public function __construct(private Renderer $renderer, private HtmlInspector $inspector, private SiteSeo $seo, private ExternalLinkChecker $externalLinks) {}

    /**
     * A new report of the site, or the one already running. One start at a
     * time per site: a click and the schedule at the same moment would each
     * find nothing running.
     *
     * @param  ?string  $site  a site handle; null: the current site
     */
    public function start(?ReportSettings $settings = null, ?string $site = null): Report
    {
        $site = Sites::scope($site ?? Site::current()->handle());

        return Cache::lock('seo:reports:start'.($site === null ? '' : ':'.$site), 120)
            ->block(30, fn () => Sites::as($site, fn () => $this->startOrJoin($settings, $site)));
    }

    private function startOrJoin(?ReportSettings $settings, ?string $site): Report
    {
        $running = Report::query()->ofSite($site)->where('status', Report::RUNNING)->latest('id')->first();

        if ($running && $running->updated_at->gt(now()->subMinutes(self::STALE_MINUTES))) {
            return $running;
        }

        $running?->update(['status' => Report::FAILED, 'error' => 'seo::reports.messages.stopped', 'finished_at' => now()]);

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
     * Renders and reads the next chunk of pages; finishes the report after the last.
     */
    public function step(Report $report): Report
    {
        if (! $report->isRunning()) {
            return $report;
        }

        return Sites::as($report->site, fn () => $this->stepInSite($report));
    }

    private function stepInSite(Report $report): Report
    {
        $pages = $report->pages()->where('checked', false)->orderBy('id')->limit(max(1, $report->settings()->int('chunk_size')))->get();

        foreach ($pages as $page) {
            $content = $this->content($page);

            if ($content === null) {
                $facts = new PageFacts(status: 404, error: 'seo::reports.messages.page_deleted');
            } else {
                $rendered = $this->renderer->render($content);
                $facts = $rendered['status'] === 200 && $rendered['error'] === null
                    ? $this->inspector->inspect($rendered['html'])
                    : new PageFacts(status: $rendered['status'], error: $rendered['error']);
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
     * Every remaining step, for the command line and the scheduler.
     *
     * @param  (callable(Report): void)|null  $progress
     */
    public function runToEnd(Report $report, ?callable $progress = null): Report
    {
        while ($report->isRunning()) {
            $report = $this->step($report);

            if ($progress) {
                $progress($report);
            }
        }

        return $report;
    }

    /**
     * The checks a report with these settings runs.
     *
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
        Sites::as($report->site, fn () => $this->finishInSite($report));
    }

    private function finishInSite(Report $report): void
    {
        $settings = $report->settings();
        $rules = $this->rules($settings);

        $summary = collect($rules)->mapWithKeys(fn (Rule $rule) => [$rule::handle() => [
            'label' => $rule->label(), 'fail' => 0, 'warn' => 0,
        ]])->all();
        $counts = ['checked' => 0, 'noindex' => 0, 'errors' => 0, 'with_issues' => 0];

        $report->pages()->lazyById(200)->each(function (ReportPage $page) use ($rules, &$summary, &$counts) {
            $facts = $page->facts();

            if (! $facts->rendered()) {
                $counts['errors']++;
                $counts['with_issues']++;
                $result = $facts->error !== null ? Result::fail($facts->error) : Result::fail('seo::reports.messages.status', ['status' => $facts->status]);
                $page->update(['results' => ['render' => $result->toArray()], 'failing' => ',render:fail,']);

                return;
            }

            $facts->noindex() ? $counts['noindex']++ : $counts['checked']++;
            $results = [];

            foreach (array_filter($rules, fn (Rule $rule) => ! $facts->noindex() || $rule->appliesToNoindex()) as $rule) {
                $result = $rule->check($page->url, $facts);
                $results[$rule::handle()] = $result->toArray();

                if ($result->status !== Result::PASS) {
                    $summary[$rule::handle()][$result->status]++;
                }
            }

            $flagged = collect($results)->reject(fn ($result) => $result['status'] === Result::PASS)
                ->map(fn ($result, $handle) => $handle.':'.$result['status'])->implode(',');

            if ($flagged !== '') {
                $counts['with_issues']++;
            }

            $page->update(['results' => $results, 'failing' => $flagged === '' ? null : ','.$flagged.',']);
        });

        $report->update([
            'status' => Report::DONE,
            'summary' => ['rules' => $summary, ...$counts],
            'finished_at' => now(),
        ]);

        $this->prune($settings->int('keep_reports'), $report->site);
    }

    /**
     * Keeps the latest $keep reports of each site.
     */
    private function prune(int $keep, ?string $site): void
    {
        $stale = Report::query()->ofSite($site)->where('status', '!=', Report::RUNNING)->orderByDesc('id')->skip(max(1, $keep))->take(PHP_INT_MAX)->pluck('id');

        // Pages first: SQLite only cascades with foreign keys switched on.
        ReportPage::query()->whereIn('report_id', $stale)->delete();
        Report::query()->whereIn('id', $stale)->delete();
    }

    /**
     * Published entries and terms with an address, in the order they're
     * checked, as the rows of the report's pages. Entries are read in chunks
     * and kept as rows, so a big site's entries needn't all be in memory.
     *
     * @return Collection<int, array{url: string, content_type: string, content_id: string, title: string}>
     */
    private function targets(ReportSettings $settings): Collection
    {
        $site = Site::current()->handle();
        $excluded = $settings->excludedCollections();

        $entries = Entry::query()->where('site', $site)->whereStatus('published')->orderBy('id')->lazy(500)
            ->filter(fn (EntryContract $entry) => ! in_array($entry->collectionHandle(), $excluded, true) && $entry->url() && ! $entry->isRedirect())
            ->map(fn (EntryContract $entry) => $this->row($entry))
            ->collect()
            ->sortBy('url');

        $terms = collect((array) config('seo.sitemap.taxonomies'))
            ->flatMap(fn (string $taxonomy) => Term::query()->where('taxonomy', $taxonomy)->where('site', $site)->get())
            ->map(fn ($term) => $term->in($site))
            ->filter(fn ($term) => $term?->url() && $this->seo->termHasEntries($term))
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
            'title' => (string) self::title((string) $content->get('title')),
        ];
    }

    /**
     * A title as its column holds it: MySQL (strict) and Postgres refuse more
     * than 255 characters, which would stop the report.
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
