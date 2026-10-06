<?php

namespace JothamLec\Seo\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JothamLec\Seo\Reports\Rules\BrokenLinks;
use JothamLec\Seo\Reports\Rules\Canonical;
use JothamLec\Seo\Reports\Rules\DescriptionLength;
use JothamLec\Seo\Reports\Rules\DescriptionUnique;
use JothamLec\Seo\Reports\Rules\ExternalLinks;
use JothamLec\Seo\Reports\Rules\ImageAlt;
use JothamLec\Seo\Reports\Rules\JsonLd;
use JothamLec\Seo\Reports\Rules\NoindexInSitemap;
use JothamLec\Seo\Reports\Rules\OgImage;
use JothamLec\Seo\Reports\Rules\OrphanPages;
use JothamLec\Seo\Reports\Rules\Rule;
use JothamLec\Seo\Reports\Rules\SingleH1;
use JothamLec\Seo\Reports\Rules\TitleLength;
use JothamLec\Seo\Reports\Rules\TitleUnique;
use JothamLec\Seo\SiteSeo;
use JothamLec\Seo\Support\Sites;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Term;

/**
 * Runs reports. start() lists the pages; step() renders the next chunk of
 * them and reads what each says; the last step runs the checks, which need
 * every page (to find repeated titles), scores the pages and the site, and
 * drops reports beyond the number to keep.
 *
 * A report is of one site: on a multi-site install each runs with its site
 * as Statamic's current one, so the pages, the sitemap, the brand global and
 * the links it follows are that site's.
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

        $running?->update(['status' => Report::FAILED, 'error' => 'Stopped making progress.', 'finished_at' => now()]);

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
                $facts = new PageFacts(status: 404, error: 'The page was deleted while the report ran.');
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
        $site = new SiteFacts($settings);

        $report->pages()->select(['id', 'url', 'facts'])->lazyById(200)->each(function (ReportPage $page) use ($site) {
            $facts = $page->facts();

            if ($facts->rendered() && ! $facts->noindex()) {
                $site->add($page->url, $facts);
            }
        });

        $summary = collect($rules)->mapWithKeys(fn (Rule $rule) => [$rule::handle() => [
            'label' => $rule->label(), 'weight' => $rule->weight(), 'fail' => 0, 'warn' => 0,
        ]])->all();
        $scores = [];
        $counts = ['scored' => 0, 'noindex' => 0, 'errors' => 0];

        $report->pages()->lazyById(200)->each(function (ReportPage $page) use ($rules, $site, &$summary, &$scores, &$counts) {
            $facts = $page->facts();

            if (! $facts->rendered()) {
                $counts['errors']++;
                $message = $facts->error ?? "The page answered with status {$facts->status}.";
                $page->update(['results' => ['render' => Result::fail($message)->toArray()], 'score' => 0, 'failing' => ',render:fail,']);
                $scores[] = 0;

                return;
            }

            $applicable = array_filter($rules, fn (Rule $rule) => ! $facts->noindex() || $rule->appliesToNoindex());
            $results = [];
            $earned = $possible = 0;

            foreach ($applicable as $rule) {
                $result = $rule->check($page->url, $facts, $site);
                $results[$rule::handle()] = $result->toArray();
                $earned += $rule->weight() * $result->value();
                $possible += $rule->weight();

                if ($result->status !== Result::PASS) {
                    $summary[$rule::handle()][$result->status]++;
                }
            }

            // A page search engines are told to skip is listed, not scored.
            $score = $facts->noindex() || $possible === 0 ? null : (int) round(100 * $earned / $possible);
            $facts->noindex() ? $counts['noindex']++ : $counts['scored']++;

            if ($score !== null) {
                $scores[] = $score;
            }

            $flagged = collect($results)->reject(fn ($result) => $result['status'] === Result::PASS)
                ->map(fn ($result, $handle) => $handle.':'.$result['status'])->implode(',');

            $page->update(['results' => $results, 'score' => $score, 'failing' => $flagged === '' ? null : ','.$flagged.',']);
        });

        $report->update([
            'status' => Report::DONE,
            'score' => $scores === [] ? null : (int) round(array_sum($scores) / count($scores)),
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
