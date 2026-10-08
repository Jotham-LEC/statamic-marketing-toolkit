<?php

namespace JothamLec\MarketingToolkit\Listeners;

use JothamLec\MarketingToolkit\IndexNow\IndexNow;
use JothamLec\MarketingToolkit\SiteSeo;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Events\EntryScheduleReached;
use Statamic\Events\TermDeleted;
use Statamic\Events\TermSaved;

/**
 * This listener queues the address of published content for IndexNow when it is saved, goes live on
 * schedule, is deleted, or is unpublished (so search engines see that it is gone). Drafts and protected
 * pages (SiteSeo::isProtected()) are never sent. Once a page moves, RedirectChangedUris sends its old
 * address along with its redirect.
 */
final class SubmitToIndexNow
{
    public function __construct(private IndexNow $indexNow) {}

    public function handle(EntrySaving|EntrySaved|EntryDeleted|EntryScheduleReached|TermSaved|TermDeleted $event): void
    {
        if (! $this->indexNow->enabled()) {
            return;
        }

        // An unpublished entry is sent while saving, when Statamic still knows that it was live.
        if ($event instanceof EntrySaving) {
            $entry = $event->entry;

            if ($entry->id() && ($entry->getOriginal('published') ?? false) && ! $entry->published() && ! app(SiteSeo::class)->isProtected($entry)) {
                $this->indexNow->queue($entry->absoluteUrl());
            }

            return;
        }

        $content = $event instanceof TermSaved || $event instanceof TermDeleted ? $event->term : $event->entry;

        // Deleted content is also sent only if it was live, because a draft's address was never public,
        // and neither was a protected page's.
        if ((! method_exists($content, 'status') || $content->status() === 'published') && ! app(SiteSeo::class)->isProtected($content)) {
            $this->indexNow->queue($content->absoluteUrl());
        }
    }
}
