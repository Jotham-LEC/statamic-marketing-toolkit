<?php

namespace JothamLec\Seo\Listeners;

use JothamLec\Seo\IndexNow\IndexNow;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntryScheduleReached;
use Statamic\Events\TermDeleted;
use Statamic\Events\TermSaved;

/**
 * Queues the address of published content that was saved, went live on
 * schedule, or was deleted (so engines see it gone) for IndexNow.
 */
class SubmitToIndexNow
{
    public function __construct(private IndexNow $indexNow) {}

    public function handle(EntrySaved|EntryDeleted|EntryScheduleReached|TermSaved|TermDeleted $event): void
    {
        $content = $event instanceof TermSaved || $event instanceof TermDeleted ? $event->term : $event->entry;
        $deleted = $event instanceof EntryDeleted || $event instanceof TermDeleted;

        if ($deleted || ! method_exists($content, 'status') || $content->status() === 'published') {
            $this->indexNow->queue($content->absoluteUrl());
        }
    }
}
