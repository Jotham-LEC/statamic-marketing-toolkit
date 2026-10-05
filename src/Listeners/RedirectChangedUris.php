<?php

namespace JothamLec\Seo\Listeners;

use Carbon\Carbon;
use Illuminate\Events\Dispatcher;
use JothamLec\Seo\Redirects\AutoRedirects;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\CollectionTreeSaving;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Events\TermSaved;
use Statamic\Events\TermSaving;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Structures\CollectionTree;
use Statamic\Support\Arr;
use Throwable;

/**
 * Adds a 301 when published content moves: an entry's or a term's slug or
 * date changes, or a page is moved in a collection's tree (with every page
 * under it). The old address is worked out while saving, from what Statamic
 * remembers of the content as it was loaded; the redirects are written once
 * the save has gone through.
 */
class RedirectChangedUris
{
    /** @var array<string, array<string, list<string>>> */
    private array $pending = [];

    public function __construct(private AutoRedirects $redirects) {}

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

        if (! $this->enabled() || ! $entry->id() || $original === [] || ! $entry->published() || ! $entry->isDirty()) {
            return;
        }

        $before = clone $entry;
        $before->slug($original['slug'] ?? $entry->slug());
        $before->data(Arr::except($original, ['collection', 'locale', 'origin', 'slug', 'date', 'published', 'path']));

        if ($entry->collection()?->dated() && ($date = $original['date'] ?? null)) {
            $before->date(Carbon::createFromFormat('Y-m-d-Hi', $date));
        }

        // Worked out last, so Statamic's caches end up holding the entry being saved.
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
        $this->moved((string) $entry->id(), $from, $to);

        // In a tree, the pages under this one moved with it.
        if ($page = $entry->structure()?->in($entry->locale())->findByEntry($entry->id())) {
            $this->forgetUris();

            foreach ($page->flattenedPages() as $child) {
                $uri = (string) $child->uri();

                if (str_starts_with($uri, $to.'/')) {
                    $this->moved((string) $child->reference(), $from.substr($uri, strlen($to)), $uri);
                }
            }
        }
    }

    public function termSaving(TermSaving $event): void
    {
        $term = $event->term;
        $slug = $term->getOriginal('slug');

        if (! $this->enabled() || $slug === null || $slug === $term->slug()) {
            return;
        }

        $before = clone $term;
        $before->slug($slug);

        $from = $this->termUri($before);
        $to = $this->termUri($term);

        if ($from && $to && $from !== $to) {
            // The editor's dialog answered for the id the form knew: the old slug's.
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
            $this->redirects->create($from, $to);
        }
    }

    public function treeSaving(CollectionTreeSaving $event): void
    {
        $tree = $event->tree;
        $original = $tree->getOriginal('tree');

        if (! $this->enabled() || ! $tree instanceof CollectionTree || $original === null || $original === $tree->tree()) {
            return;
        }

        $before = (clone $tree)->tree($original);
        $from = $this->treeUris($before);
        $to = $this->treeUris($tree);

        foreach ($from as $id => $uri) {
            if (isset($to[$id]) && $uri !== $to[$id]) {
                $this->pending['tree:'.$tree->handle()][$id] = [$uri, $to[$id]];
            }
        }
    }

    public function treeSaved(CollectionTreeSaved $event): void
    {
        foreach ($this->pending['tree:'.$event->tree->handle()] ?? [] as $id => [$from, $to]) {
            $this->moved((string) $id, $from, $to);
        }

        unset($this->pending['tree:'.$event->tree->handle()]);
    }

    /**
     * The page's own 301, and when it is a collection's mount, one wildcard
     * rule for the entries that moved with it.
     */
    private function moved(string $id, string $from, string $to): void
    {
        $this->redirects->create($from, $to);

        if (Collection::all()->contains(fn ($collection) => $collection->mount()?->id() === $id)) {
            $this->redirects->createForPrefix($from, $to);
        }
    }

    private function enabled(): bool
    {
        return (bool) config('seo.redirects.enabled') && (bool) config('seo.redirects.automatic');
    }

    private function entryUri(Entry $entry): ?string
    {
        $this->forgetUris();

        try {
            return $entry->uri();
        } catch (Throwable) {
            // A route that can't be built from the old data has no old address.
            return null;
        }
    }

    private function termUri(Term $term): ?string
    {
        return $term->in(Site::default()->handle())?->uri();
    }

    /**
     * @return array<string, string> entry id => URI
     */
    private function treeUris(CollectionTree $tree): array
    {
        $this->forgetUris();

        // Walked fresh on a copy: a tree caches its pages and doesn't drop them when it changes.
        return (clone $tree)->disableUriCache()->pages()->flattenedPages()
            ->filter(fn ($page) => $page->reference())
            ->mapWithKeys(fn ($page) => [$page->reference() => (string) $page->uri()])
            ->all();
    }

    private function forgetUris(): void
    {
        Blink::store('entry-uris')->flush();
        Blink::store('structure-uris')->flush();
    }

    /**
     * @return array<int, string>|null
     */
    private function pull(string $type, string $id): ?array
    {
        $move = $this->pending[$type][$id] ?? null;
        unset($this->pending[$type][$id]);

        return $move;
    }
}
