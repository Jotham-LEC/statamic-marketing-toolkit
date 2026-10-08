<?php

namespace JothamLec\MarketingToolkit\Listeners;

use Carbon\Carbon;
use Illuminate\Events\Dispatcher;
use JothamLec\MarketingToolkit\IndexNow\IndexNow;
use JothamLec\MarketingToolkit\Redirects\AutoRedirects;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Uris;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\CollectionTreeSaving;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Events\TermSaved;
use Statamic\Events\TermSaving;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Structures\CollectionTree;
use Statamic\Structures\Tree;
use Statamic\Support\Arr;
use Throwable;

/**
 * This listener adds a 301 when published content moves, such as when an entry's or a term's slug or date
 * changes, or when a page is moved in a collection's tree (with every page under it). The old address is
 * worked out while saving, from what Statamic remembers of the content as it was loaded, and the
 * redirects are written once the save has gone through.
 */
final class RedirectChangedUris
{
    /** @var array<string, array<string, array<int, mixed>>> */
    private array $pending = [];

    public function __construct(private AutoRedirects $redirects) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            EntrySaving::class => 'entrySaving',
            EntrySaved::class => 'entrySaved',
            TermSaving::class => 'termSaving',
            TermSaved::class => 'termSaved',
            CollectionTreeSaving::class => 'treeSaving',
            CollectionTreeSaved::class => 'treeSaved',
        ];
    }

    public function entrySaving(EntrySaving $event): void
    {
        $entry = $event->entry;
        $original = $entry->getOriginal();
        $this->forget('entry', (string) $entry->id());

        if (! $this->enabled() || ! $entry->id() || $original === [] || ! $entry->published() || ! $entry->isDirty()) {
            return;
        }

        $before = clone $entry;
        $before->slug($original['slug'] ?? $entry->slug());
        $before->data(Arr::except($original, ['collection', 'locale', 'origin', 'slug', 'date', 'published', 'path']));

        // Statamic keeps the date in UTC, so reading it in the app's timezone would shift it by the offset.
        if ($entry->collection()?->dated() && ($date = $original['date'] ?? null)) {
            $before->date(Carbon::createFromFormat('Y-m-d-Hi', $date, 'UTC'));
        }

        // We work out the new address last, so Statamic's caches end up holding the entry being saved.
        $from = $this->entryUri($before);
        $to = $this->entryUri($entry);

        if ($from && $to && $from !== $to) {
            $this->pending['entry'][$entry->id()] = [$from, $to];
        }
    }

    public function entrySaved(EntrySaved $event): void
    {
        $entry = $event->entry;

        if (! $move = $this->pull('entry', (string) $entry->id())) {
            return;
        }

        if (! $this->redirects->wanted((string) $entry->id())) {
            return;
        }

        [$from, $to] = $move;
        $this->moved($entry, $from, $to);

        // In a tree, the pages under this one moved with it, so they get redirects too.
        if ($page = $entry->structure()?->in($entry->locale())->findByEntry($entry->id())) {
            Uris::forget();

            foreach ($page->flattenedPages() as $child) {
                $uri = (string) $child->uri();

                if (str_starts_with($uri, $to.'/')) {
                    $this->moved($child->entry(), $from.substr($uri, strlen($to)), $uri);
                }
            }
        }
    }

    public function termSaving(TermSaving $event): void
    {
        $term = $event->term;
        $slug = $term->getOriginal('slug');
        $this->forget('term', (string) $term->id());

        if (! $this->enabled() || $slug === null || $slug === $term->slug() || ! Uris::termHasPage($term)) {
            return;
        }

        $before = clone $term;
        $before->slug($slug);

        $from = $this->termUris($before);
        $to = $this->termUris($term);

        if ($from !== $to) {
            // The editor's dialog answered for the ID that the form knew, which is the old slug's ID.
            $this->pending['term'][$term->id()] = [$from, $to, $term->taxonomyHandle().'::'.$slug];
        }
    }

    public function termSaved(TermSaved $event): void
    {
        if (! $move = $this->pull('term', (string) $event->term->id())) {
            return;
        }

        [$from, $to, $formId] = $move;

        if ($this->redirects->wanted($formId)) {
            foreach ($from as $site => $uri) {
                if (isset($to[$site]) && $uri !== $to[$site]) {
                    $this->redirects->create($uri, $to[$site], $site);
                    $this->tellIndexNow($event->term, $uri, $site);
                }
            }
        }
    }

    public function treeSaving(CollectionTreeSaving $event): void
    {
        $tree = $event->tree;
        $original = $tree->getOriginal('tree');
        unset($this->pending[$this->treeKey($tree)]);

        if (! $this->enabled() || ! $tree instanceof CollectionTree || $original === null || $original === $tree->tree()) {
            return;
        }

        $before = (clone $tree)->tree($original);
        $from = $this->treeUris($before);
        $to = $this->treeUris($tree);

        foreach ($from as $id => $uri) {
            if (isset($to[$id]) && $uri !== $to[$id]) {
                $this->pending[$this->treeKey($tree)][$id] = [$uri, $to[$id]];
            }
        }
    }

    public function treeSaved(CollectionTreeSaved $event): void
    {
        $key = $this->treeKey($event->tree);

        foreach ($this->pending[$key] ?? [] as $id => [$from, $to]) {
            if ($entry = Entry::find($id)) {
                $this->moved($entry, $from, $to);
            }
        }

        unset($this->pending[$key]);
    }

    /**
     * Builds the key for a tree, which includes the locale because each site has its own tree of a collection.
     */
    private function treeKey(Tree $tree): string
    {
        return 'tree:'.$tree->handle().':'.$tree->locale();
    }

    /**
     * Creates the page's own 301 and, when the page is a collection's mount, one wildcard rule for the
     * entries that moved with it. Both are added among the rules of the entry's site.
     */
    private function moved(EntryContract $entry, string $from, string $to): void
    {
        $this->redirects->create($from, $to, $entry->locale());
        $this->tellIndexNow($entry, $from, $entry->locale());

        if (Collection::findByMount($entry)) {
            $this->redirects->createForPrefix($from, $to, $entry->locale());
        }
    }

    /**
     * Tells IndexNow that the old address now redirects, so search engines recrawl it and follow the 301
     * sooner. A protected page's address is never sent.
     */
    private function tellIndexNow(EntryContract|Term $content, string $uri, string $site): void
    {
        $home = Site::get($site)?->absoluteUrl();

        if ($home !== null && ! app(SiteSeo::class)->isProtected($content)) {
            app(IndexNow::class)->queue(rtrim($home, '/').'/'.ltrim($uri, '/'));
        }
    }

    private function enabled(): bool
    {
        return Features::on('redirects') && Features::on('automatic_redirects');
    }

    private function entryUri(EntryContract $entry): ?string
    {
        Uris::forget();

        try {
            return $entry->uri();
        } catch (Throwable) {
            // When the route can't be built from the old data, there is no old address.
            return null;
        }
    }

    /**
     * Returns the term's address on each site that its taxonomy is on. The sites that don't set their own
     * slug share one, so a new slug moves the term on each of those sites.
     *
     * @return array<string, string> site handle => URI
     */
    private function termUris(Term $term): array
    {
        /** @var \Illuminate\Support\Collection<int, string> $sites */
        $sites = $term->taxonomy()?->sites() ?? collect([Site::default()->handle()]);

        return collect($sites)
            ->mapWithKeys(fn (string $site) => [$site => $term->in($site)?->uri()])
            ->filter()
            ->all();
    }

    /**
     * @return array<string, string> entry id => URI
     */
    private function treeUris(CollectionTree $tree): array
    {
        Uris::forget();

        // We walk a fresh copy, because a tree caches its pages and doesn't drop them when it changes.
        return (clone $tree)->disableUriCache()->pages()->flattenedPages()
            ->filter(fn ($page) => $page->reference())
            ->mapWithKeys(fn ($page) => [$page->reference() => (string) $page->uri()])
            ->all();
    }

    /**
     * @return array<int, mixed>|null
     */
    private function pull(string $type, string $id): ?array
    {
        $move = $this->pending[$type][$id] ?? null;
        $this->forget($type, $id);

        return $move;
    }

    /**
     * Drops anything that an earlier save of this content left behind. A save that was cancelled or failed
     * never reached its Saved event, and this instance outlives it in a queue worker or a long import.
     */
    private function forget(string $type, string $id): void
    {
        unset($this->pending[$type][$id]);
    }
}
