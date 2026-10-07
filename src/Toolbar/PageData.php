<?php

namespace JothamLec\MarketingToolkit\Toolbar;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Preview\MetaPayload;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use JothamLec\MarketingToolkit\Reports\Report;
use JothamLec\MarketingToolkit\Reports\ReportPage;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\SearchConsole\Client;
use JothamLec\MarketingToolkit\SearchConsole\SearchStat;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use JothamLec\MarketingToolkit\Support\Uris;
use JothamLec\MarketingToolkit\Tracking\Tracking;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Data;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Statamic\Structures\Page;

/**
 * What the front-end toolbar shows about one page, for one user: one JSON
 * shape, each part null when the user may not see it. Built on the page's
 * own site (ToolbarController runs it under Sites::as()), with that site's
 * report, redirects, 404s and Search Console numbers, as the control panel
 * shows them with the site selected. Sentences come ready, in the user's
 * language; the script only fills in what the browser knows (consent).
 */
class PageData
{
    /** Checks about what the page's HTML holds (links, headings, images, markup): fixed in the content, so they lead to the report. */
    public const array REPORT_RULES = ['broken_links', 'external_links', 'image_alt', 'json_ld', 'orphan_pages', 'single_h1'];

    private Entry|Term|null $content;

    private string $path;

    private SiteObject $site;

    public function __construct(
        private User $user,
        private string $url,
        private ?int $status,
    ) {
        $this->site = Site::findByUrl($url) ?? Site::current();
        $found = Data::findByRequestUrl($url);
        $found = $found instanceof Page ? $found->entry() : $found;
        $this->content = $found instanceof Entry || $found instanceof Term ? $found : null;
        $this->path = Uris::normalizePath($url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $view = $this->user->can('view marketing toolkit');
        $report = $view && $this->content ? $this->report() : null;

        return [
            'user' => [
                ...Toolbar::preferences($this->user),
                'color_mode' => $this->user->preferredColorMode(),
                'csrf' => csrf_token(),
                'labels' => __('marketing-toolkit::toolbar.ui'),
            ],
            'site' => ['handle' => $this->site->handle(), 'name' => (string) $this->site->name()],
            'page' => $this->page(),
            'seo' => $report,
            'preview' => $view && $this->content ? $this->preview($this->content) : null,
            'redirects' => $this->redirects(),
            'tracking' => $view ? $this->tracking() : null,
            'sites' => $this->sites(),
            'more' => $this->more(),
        ];
    }

    /**
     * Whether Statamic answers this address with a 404: as the browser saw
     * it, else when no content has it and the 404 log has counted it. A
     * draft's address is missing too, for everyone but the control panel.
     */
    private function missing(): bool
    {
        if ($this->content && $this->isDraft()) {
            return true;
        }

        if ($this->status !== null) {
            return in_array($this->status, [404, 410], true);
        }

        return $this->content === null && MissingPath::query()->ofSite(Sites::scope($this->site->handle()))->where('path', $this->path)->exists();
    }

    private function isDraft(): bool
    {
        return $this->content instanceof Entry && $this->content->status() !== 'published';
    }

    /**
     * @return array<string, mixed>
     */
    private function page(): array
    {
        $content = $this->content;
        $editable = $content && $this->user->can('edit', $content);

        return [
            'type' => $content instanceof Entry ? 'entry' : ($content instanceof Term ? 'term' : null),
            'title' => $content ? (string) $content->value('title') : null,
            'status' => $content instanceof Entry ? $content->status() : ($content ? 'published' : null),
            'missing' => $this->missing(),
            'edit_url' => $editable ? $this->cp($content->editUrl()) : null,
            'seo_url' => $editable ? $this->seoUrl($content) : null,
        ];
    }

    /**
     * The edit screen open on the tab holding the `seo` field, by its handle
     * in this content's blueprint (whatever the site renamed it to); the
     * screen opens on its first tab when no tab holds it.
     */
    private function seoUrl(Entry|Term $content): string
    {
        $tab = $content->blueprint()?->tabs()->first(fn ($tab) => $tab->fields()->has('seo'))?->handle();

        return $this->cp($content->editUrl().($tab ? '#'.$tab : ''));
    }

    /**
     * The SEO panel: this page's row in the site's latest report, its checks
     * worst first, and its Search Console numbers.
     *
     * @return array<string, mixed>
     */
    private function report(): array
    {
        $report = Report::latestDone($this->site->handle());
        $content = $this->content;
        $row = $report && $content ? $report->pages()
            ->where('content_type', $content instanceof Entry ? 'entry' : 'term')
            ->where('content_id', (string) $content->id())
            ->first() : null;

        return [
            'score' => $row?->score,
            'noindex' => $row?->facts()->noindex() ?? false,
            'messages' => $this->reportMessages($report, $row),
            'issues' => $report && $row ? $this->issues($report, $row) : [],
            'report_url' => $report ? $this->cp(cp_route('mt.reports.show', $report)) : $this->cp(cp_route('mt.reports.index')),
            'run_url' => $this->user->can('run marketing toolkit reports') ? $this->cp(cp_route('mt.reports.index')) : null,
            'search' => $this->search(),
        ];
    }

    /**
     * @return list<string>
     */
    private function reportMessages(?Report $report, ?ReportPage $row): array
    {
        if ($report === null) {
            return [__('marketing-toolkit::toolbar.seo.no_report')];
        }

        if ($row === null) {
            return [__('marketing-toolkit::toolbar.seo.not_in_report')];
        }

        $finished = $report->finished_at ?? $report->updated_at;
        $saved = $this->content?->lastModified();
        // Saved on the day of the report: the times say which came first.
        $sameDay = $saved && $saved->isSameDay($finished);
        $messages = [$saved && $saved->gt($finished)
            ? __('marketing-toolkit::toolbar.seo.changed', ['saved' => $this->date($saved, $sameDay), 'date' => $this->date($finished, $sameDay)])
            : __('marketing-toolkit::toolbar.seo.date', ['date' => $this->date($finished)])];

        if ($row->facts()->noindex()) {
            $messages[] = __('marketing-toolkit::toolbar.seo.noindex');
        } elseif ($this->issues($report, $row) === []) {
            $messages[] = __('marketing-toolkit::toolbar.seo.passing');
        }

        return $messages;
    }

    /**
     * The checks this page fails or warns about, failures first, then by the
     * check's weight, each in the reader's language and with where to fix it.
     *
     * @return list<array{label: string, status: string, message: string, url: ?string}>
     */
    private function issues(Report $report, ReportPage $row): array
    {
        $rules = collect($report->summary['rules'] ?? []);
        $fix = $this->content && $this->user->can('edit', $this->content) ? $this->seoUrl($this->content) : null;
        $detail = $this->cp(cp_route('mt.reports.show', $report));

        return collect($row->results ?? [])
            ->reject(fn (array $result) => $result['status'] === Result::PASS)
            ->map(fn (array $result, string $handle) => [
                'label' => (string) __($rules[$handle]['label'] ?? 'marketing-toolkit::reports.rules.'.$handle),
                'status' => $result['status'],
                'message' => Result::translate($result['message'], $result['params'] ?? []),
                'url' => in_array($handle, self::REPORT_RULES, true) || $handle === 'render' ? $detail : $fix,
                'weight' => (float) ($rules[$handle]['weight'] ?? 1),
            ])
            ->sortBy([fn ($a, $b) => ($a['status'] === Result::FAIL ? 0 : 1) <=> ($b['status'] === Result::FAIL ? 0 : 1), fn ($a, $b) => $b['weight'] <=> $a['weight']])
            ->map(fn (array $issue) => array_diff_key($issue, ['weight' => true]))
            ->values()
            ->all();
    }

    /**
     * One sentence of Search Console's numbers for this page, once it is set up for the site.
     */
    private function search(): ?string
    {
        if (! app(Client::class)->configured($this->site->handle())) {
            return null;
        }

        $url = (string) ($this->content?->absoluteUrl() ?? strtok($this->url, '?#'));
        $stat = SearchStat::query()->shownOn($this->site->handle())->whereIn('url', array_unique([$url, rtrim($url, '/'), rtrim($url, '/').'/']))->first();

        if ($stat === null) {
            return __('marketing-toolkit::toolbar.seo.search_none');
        }

        return __('marketing-toolkit::toolbar.seo.search', [
            'days' => (int) config('marketing-toolkit.search_console.days', 28),
            'clicks' => Number::format($stat->clicks, locale: $this->locale()),
            'impressions' => Number::format($stat->impressions, locale: $this->locale()),
            // Rounded as the overview rounds it (ICU would round half to even).
            'position' => Number::format(round($stat->position, 1), maxPrecision: 1, locale: $this->locale()),
        ]);
    }

    /**
     * The Preview panel: the search result and the share card, as the SEO
     * tab's preview draws them, and the indexing facts as sentences.
     *
     * @return array<string, mixed>
     */
    private function preview(Entry|Term $content): array
    {
        $seo = app(SiteSeo::class);
        $meta = app(MetaPayload::class)->forContent($content);
        $languages = count(array_diff_key($seo->contentAlternates($content), ['x-default' => true]));
        $facts = [];

        if (str_contains($meta['robots'], 'noindex')) {
            $facts[] = __('marketing-toolkit::toolbar.preview.noindex', ['reason' => __('marketing-toolkit::toolbar.preview.reasons.'.$this->noindexReason($content))]);
        } elseif ($meta['canonical'] !== null && rtrim($meta['canonical'], '/') !== rtrim($meta['url'], '/')) {
            $facts[] = __('marketing-toolkit::toolbar.preview.canonical', ['url' => $meta['canonical']]);
        }

        if (config('marketing-toolkit.sitemap.enabled')) {
            $facts[] = $seo->inSitemap($content) ? __('marketing-toolkit::toolbar.preview.in_sitemap') : __('marketing-toolkit::toolbar.preview.not_in_sitemap');
        }

        if ($languages > 1) {
            $facts[] = __('marketing-toolkit::toolbar.preview.languages', ['count' => $languages]);
        }

        return [...$meta, 'facts' => $facts];
    }

    private function noindexReason(Entry|Term $content): string
    {
        $seo = $content->get('seo');

        if (is_array($seo) && ($seo['noindex'] ?? false)) {
            return 'page';
        }

        return config('marketing-toolkit.robots.noindex_outside_production') && ! app()->isProduction() ? 'environment' : 'rule';
    }

    /**
     * The Redirects panel: rules that send visitors here, one from this
     * address that a page here overrides, and on a missing page, its 404s.
     *
     * @return array<string, mixed>|null
     */
    private function redirects(): ?array
    {
        $manage = config('marketing-toolkit.redirects.enabled') && $this->user->can('manage marketing toolkit redirects');
        $view = config('marketing-toolkit.not_found.enabled') && $this->user->can('view marketing toolkit');

        if (! $manage && ! $view) {
            return null;
        }

        $missing = $this->missing();
        $handle = $this->site->handle();
        $messages = [];
        $toHere = collect();

        if ($missing) {
            $messages[] = __('marketing-toolkit::toolbar.redirects.missing');

            if ($this->content && $this->isDraft()) {
                $messages[] = __('marketing-toolkit::toolbar.redirects.draft');
            }

            $logged = $view ? MissingPath::query()->ofSite(Sites::scope($handle))->where('path', $this->path)->first() : null;

            if ($logged) {
                $messages[] = trans_choice('marketing-toolkit::toolbar.redirects.logged', $logged->hits, ['count' => Number::format($logged->hits, locale: $this->locale()), 'date' => $this->date($logged->first_seen_at)]);
            }
        }

        if ($manage && ! $missing) {
            $toHere = $this->redirectsHere();
            $hits = (int) $toHere->sum('hits');
            $messages[] = $toHere->isEmpty()
                ? __('marketing-toolkit::toolbar.redirects.none')
                : trans_choice('marketing-toolkit::toolbar.redirects.to_here', $toHere->count(), ['count' => $toHere->count(), 'hits' => Number::format($hits, locale: $this->locale())]);
        }

        $ignored = $manage && $this->content && ! $missing ? $this->redirectFromHere() : null;

        if ($ignored) {
            $messages[] = __('marketing-toolkit::toolbar.redirects.ignored');
        }

        return [
            'messages' => $messages,
            'to_here' => $toHere->take(5)->map(fn (Redirect $redirect) => [
                'source' => $redirect->source,
                'hits' => $redirect->hits,
                'url' => $this->cp(cp_route('mt.redirects.edit', $redirect)),
            ])->values()->all(),
            'ignored' => $ignored ? ['source' => $ignored->source, 'url' => $this->cp(cp_route('mt.redirects.edit', $ignored))] : null,
            'create_url' => $manage && $missing
                ? $this->cp(cp_route('mt.redirects.create', array_filter(['source' => $this->path, 'site' => Sites::scope($handle)])))
                : null,
        ];
    }

    /**
     * Active rules on this site whose target is this page: its path on the
     * site (as rules store targets) or its full address.
     *
     * @return Collection<int, Redirect>
     */
    private function redirectsHere()
    {
        $url = (string) ($this->content?->absoluteUrl() ?? strtok($this->url, '?#'));
        $relative = Redirect::normalizeTarget('/'.ltrim((string) $this->site->relativePath($url), '/'));

        return Redirect::query()
            ->where('active', true)
            ->when(Sites::multiple(), fn ($query) => $query->appliesOn($this->site->handle()))
            ->whereIn('target', array_values(array_unique(array_filter([$relative, $url, rtrim($url, '/'), rtrim($url, '/').'/']))))
            ->orderByDesc('hits')
            ->get()
            ->filter(fn (Redirect $redirect) => $redirect->isAccessible())
            ->values();
    }

    /**
     * An active rule from this address: it never applies while a page is here.
     */
    private function redirectFromHere(): ?Redirect
    {
        $rule = Redirect::forSource($this->path, site: Sites::scope($this->site->handle()))
            ?? (Sites::multiple() ? Redirect::forSource($this->path) : null);

        return $rule && $rule->active && $rule->isAccessible() ? $rule : null;
    }

    /**
     * The Tracking panel: which tags load on this page, or why none do, and
     * Consent Mode. The browser adds its own consent state.
     *
     * @return array<string, mixed>
     */
    private function tracking(): array
    {
        $tracking = app(Tracking::class);
        $ids = array_filter($tracking->ids());
        $names = fn (array $trackers) => $this->list(array_map(fn (string $tracker) => (string) __('marketing-toolkit::cp.tracking.names.'.$tracker), $trackers));
        $consent = $tracking->consent();
        $variables = GlobalSet::findByHandle((string) config('marketing-toolkit.settings_global'))?->in($this->site->handle());

        $messages = [match (true) {
            ! config('marketing-toolkit.tracking.enabled') => __('marketing-toolkit::toolbar.tracking.off'),
            $ids === [] => __('marketing-toolkit::toolbar.tracking.none_set'),
            ! app()->environment((array) config('marketing-toolkit.tracking.environments')) => __('marketing-toolkit::toolbar.tracking.not_production'),
            default => __('marketing-toolkit::toolbar.tracking.printing', ['tools' => $names(array_keys($ids))]),
        }];

        if ($ids !== []) {
            $messages[] = $consent === null
                ? __('marketing-toolkit::toolbar.tracking.consent_off')
                : __('marketing-toolkit::toolbar.tracking.consent_on', ['where' => $this->consentWhere($consent['regions'])]);
            $messages[] = $tracking->conversions() ? __('marketing-toolkit::toolbar.tracking.leads_on') : __('marketing-toolkit::toolbar.tracking.leads_off');
        }

        if ($overlap = $tracking->besideGtm()) {
            $messages[] = __('marketing-toolkit::toolbar.tracking.overlap', ['tools' => $this->list($overlap)]);
        }

        return [
            'messages' => $messages,
            // The browser reads its consent state only where Consent Mode is on.
            'consent' => $consent !== null,
            'settings_url' => $variables && $this->user->can('edit', $variables) ? $this->cp($variables->editUrl()) : null,
        ];
    }

    /**
     * @param  list<string>  $regions
     */
    private function consentWhere(array $regions): string
    {
        if ($regions === []) {
            return __('marketing-toolkit::toolbar.tracking.everywhere');
        }

        $eea = array_diff(Tracking::EEA_REGIONS, $regions) === [];
        $others = $eea ? array_values(array_diff($regions, Tracking::EEA_REGIONS)) : $regions;

        return __('marketing-toolkit::toolbar.tracking.in_regions', ['regions' => $this->list([...($eea ? [__('marketing-toolkit::toolbar.tracking.eea')] : []), ...$others])]);
    }

    /**
     * The Sites panel: this page on each other site the user may see, with
     * its status, its address and its edit screen, or that it isn't there yet.
     *
     * @return list<array<string, mixed>>|null
     */
    private function sites(): ?array
    {
        $content = $this->content;

        if (! Sites::multiple() || $content === null) {
            return null;
        }

        $versions = $this->versions($content);
        $sites = $content instanceof Entry ? $content->collection()->sites() : collect($content->taxonomy()?->sites() ?? []);

        return Site::authorized()
            ->filter(fn ($site) => $site->handle() !== $content->locale() && collect($sites)->contains($site->handle()))
            ->map(function ($site) use ($versions) {
                $version = $versions[$site->handle()] ?? null;

                if ($version === null) {
                    return ['site' => (string) $site->name(), 'handle' => $site->handle(), 'missing' => __('marketing-toolkit::toolbar.sites.missing', ['site' => $site->name()])];
                }

                $status = $version instanceof Entry ? $version->status() : 'published';

                return [
                    'site' => (string) $site->name(),
                    'handle' => $site->handle(),
                    'status' => __('marketing-toolkit::toolbar.sites.statuses.'.$status),
                    'url' => $status === 'published' ? $version->absoluteUrl() : null,
                    'edit_url' => $this->user->can('edit', $version) ? $this->cp($version->editUrl(), $site->handle()) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, Entry|Term> site handle => this content there
     */
    private function versions(Entry|Term $content): array
    {
        if ($content instanceof Entry) {
            $root = $content->root();

            return collect([$root, ...$root->descendants()->values()->all()])->keyBy(fn (Entry $entry) => $entry->locale())->all();
        }

        return collect($content->taxonomy()?->sites() ?? [])->mapWithKeys(fn (string $site) => [$site => $content->in($site)])->filter()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function more(): array
    {
        return [
            'overview_url' => $this->user->can('view marketing toolkit') ? $this->cp(cp_route('mt.index')) : null,
            'dashboard_url' => $this->cp(cp_route('dashboard')),
            'cache' => (bool) config('statamic.static_caching.strategy') && $this->user->can('access cache utility'),
            'cache_url' => route('statamic.mt.toolbar.cache', [], false),
            'hide_url' => route('statamic.mt.toolbar.hide', [], false),
        ];
    }

    /**
     * A control panel address, opened on this page's site: through
     * `toolbar/go`, which selects the site first, on a multi-site install.
     */
    private function cp(string $url, ?string $site = null): string
    {
        if (! Sites::multiple()) {
            return $url;
        }

        $to = (string) parse_url($url, PHP_URL_PATH).(($query = parse_url($url, PHP_URL_QUERY)) ? '?'.$query : '').(($fragment = parse_url($url, PHP_URL_FRAGMENT)) ? '#'.$fragment : '');

        return cp_route('mt.toolbar.go', ['site' => $site ?? $this->site->handle(), 'to' => $to]);
    }

    private function date(CarbonInterface $date, bool $time = false): string
    {
        return $date->locale($this->locale())->isoFormat($time ? 'LLL' : 'LL');
    }

    private function locale(): string
    {
        return app()->getLocale();
    }

    /**
     * "A, B and C", in the reader's language.
     *
     * @param  list<string>  $items
     */
    private function list(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? (string) $last : implode(', ', $items).' '.__('marketing-toolkit::toolbar.ui.and').' '.$last;
    }
}
