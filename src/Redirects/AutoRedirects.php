<?php

namespace JothamLec\Seo\Redirects;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Statamic\Facades\User;

/**
 * The 301s Statamic content earns when its address changes, kept free of
 * chains and loops: a rule into the old address is repointed at the new one,
 * and a rule out of the new address is dropped, because that page is live.
 */
class AutoRedirects
{
    public function create(string $from, string $to): void
    {
        $from = Redirect::normalize($from);
        $to = Redirect::normalize($to);

        if ($from === $to || ! config('seo.redirects.automatic')) {
            return;
        }

        $this->write($from, $to, $from, $to);
    }

    /**
     * Everything under an address moved with it: a mount page took its
     * collection's entries along. One wildcard rule covers them all.
     */
    public function createForPrefix(string $from, string $to): void
    {
        $from = Redirect::normalize($from);
        $to = Redirect::normalize($to);

        if ($from === $to || $from === '/' || ! config('seo.redirects.automatic')) {
            return;
        }

        $this->write($from.'/*', $to.'/$1', $from, $to);
    }

    private function write(string $source, string $target, string $from, string $to): void
    {
        DB::transaction(function () use ($source, $target, $from, $to) {
            // The new address is live again: nothing should send it away.
            Redirect::query()->whereIn('source', [$to, $to.'/*'])->delete();

            // Rules into the old address, or under it, follow it to the new one.
            // (LIKE only narrows the rows: `_` in a slug is a LIKE wildcard.)
            Redirect::query()->where('target', $from)->orWhere('target', 'like', $from.'/%')->get()
                ->filter(fn (Redirect $redirect) => $redirect->target === $from || str_starts_with((string) $redirect->target, $from.'/'))
                ->each(fn (Redirect $redirect) => $redirect->update(['target' => $to.substr($redirect->target, strlen($from))]));

            Redirect::query()->whereColumn('source', 'target')->delete();

            Redirect::query()->updateOrCreate(
                ['source' => $source],
                ['target' => $target, 'status' => 301, 'active' => true, 'automatic' => true],
            );
        });

        // Bulk deletes bypass the model events that clear the cache.
        Matcher::flush();
    }

    /**
     * The editor's answer in the save dialog, for the content saved next.
     * Without an answer (a save from code, an import) the redirect is made.
     */
    public function remember(string $id, bool $create): void
    {
        Cache::put($this->choiceKey($id), $create, now()->addMinutes(10));
    }

    public function wanted(string $id): bool
    {
        return Cache::pull($this->choiceKey($id), true) !== false;
    }

    private function choiceKey(string $id): string
    {
        return 'seo:redirect-choice:'.(User::current()?->id() ?? 'guest').':'.$id;
    }
}
