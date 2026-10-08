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
 * Queues the address of published content that was saved, went live on
 * schedule, was deleted or was unpublished (so engines see it gone) for
 * IndexNow. Drafts and protected pages (SiteSeo::isProtected()) are never
 * sent. A page's old address, once it moves, is sent by RedirectChangedUris
 * with its redirect.
 */
final class SubmitToIndexNow
{
    public function __construct(private IndexNow $indexNow) {}

    public function handle(EntrySaving|EntrySaved|EntryDeleted|EntryScheduleReached|TermSaved|TermDeleted $event): void
    {
        if (! $this->indexNow->enabled()) {
            return;
        }

        // Unpublished: told while saving, when Statamic still knows it was live.
        if ($event instanceof EntrySaving) {
            $entry = $event->entry;

            if ($entry->id() && ($entry->getOriginal('published') ?? false) && ! $entry->published() && ! app(SiteSeo::class)->isProtected($entry)) {
                $this->indexNow->queue($entry->absoluteUrl());
            }

            return;
        }

        $content = $event instanceof TermSaved || $event instanceof TermDeleted ? $event->term : $event->entry;

        // Deleted too only if it was live: a draft's address was never public, nor a protected page's.
        if ((! method_exists($content, 'status') || $content->status() === 'published') && ! app(SiteSeo::class)->isProtected($content)) {
            $this->indexNow->queue($content->absoluteUrl());
        }
    }
}
