<?php

namespace JothamLec\MarketingToolkit\Redirects;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\User;

/**
 * The 301s Statamic content earns when its address changes, kept free of
 * chains and loops: a rule into the old address is repointed at the new one,
 * and a rule out of the new address is dropped, because that page is live.
 * On a multi-site install all of it happens among the rules of the content's
 * own site; on a single site, among the rules for every site.
 */
class AutoRedirects
{
    /**
     * @param  ?string  $site  the handle of the site the content is on
     */
    public function create(string $from, string $to, ?string $site = null): void
    {
        $from = Redirect::normalize($from);
        $to = Redirect::normalize($to);

        if ($from === $to || ! config('seo.redirects.automatic')) {
            return;
        }

        $this->write($from, $to, $from, $to, live: $to, site: Sites::scope($site));
    }

    /**
     * Everything under an address moved with it: a mount page took its
     * collection's entries along. One wildcard rule covers them all.
     */
    public function createForPrefix(string $from, string $to, ?string $site = null): void
    {
        $from = Redirect::normalize($from);
        $to = Redirect::normalize($to);

        if ($from === $to || $from === '/' || ! config('seo.redirects.automatic')) {
            return;
        }

        $this->write($from.'/*', $to.'/$1', $from, $to, live: $to.'/*', site: Sites::scope($site));
    }

    /**
     * @param  string  $live  the source of a rule the move makes wrong: the new address, or everything under it
     * @param  ?string  $site  the site whose rules change; null: those for every site
     */
    private function write(string $source, string $target, string $from, string $to, string $live, ?string $site): void
    {
        DB::transaction(function () use ($source, $target, $from, $to, $live, $site) {
            // The new address is live again: nothing should send it away.
            Redirect::query()->onSite($site)->where('source', $live)->delete();

            // Rules into the old address, or under it, follow it to the new one,
            // unless that brings one back to its own address (`/x/*` to `/x/$1`).
            // (LIKE only narrows the rows: `_` in a slug is a LIKE wildcard.)
            Redirect::query()->onSite($site)->where(fn ($query) => $query->where('target', $from)->orWhere('target', 'like', $from.'/%'))->get()
                ->filter(fn (Redirect $redirect) => $redirect->target === $from || str_starts_with((string) $redirect->target, $from.'/'))
                ->each(function (Redirect $redirect) use ($from, $to) {
                    $redirect->target = $to.substr($redirect->target, strlen($from));
                    Redirect::pointsBack($redirect->source, $redirect->target) ? $redirect->delete() : $redirect->save();
                });

            // The site's rule from the old address, in any letter case when matching ignores it.
            (Redirect::forSource($source, site: $site) ?? new Redirect)
                ->fill(['site' => $site, 'source' => $source, 'target' => $target, 'status' => 301, 'active' => true, 'automatic' => true])
                ->save();
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
