<?php

namespace JothamLec\MarketingToolkit\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * A row with a `site` column: a site's handle, or null on a single site
 * (and for rows from before the install had more than one).
 *
 * @property ?string $site
 */
trait BelongsToSite
{
    /**
     * Rows of exactly this site; null: those of a single-site install.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOfSite(Builder $query, ?string $site): void
    {
        $site === null ? $query->whereNull('site') : $query->where('site', $site);
    }

    /**
     * The rows the control panel shows while $site is selected: its own, and
     * those from before the install had more than one site.
     *
     * @param  Builder<static>  $query
     */
    public function scopeShownOn(Builder $query, string $site): void
    {
        if (Sites::multiple()) {
            $query->where(fn (Builder $query) => $query->where('site', $site)->orWhereNull('site'));
        }
    }

    public function isShownOn(string $site): bool
    {
        return ! Sites::multiple() || $this->site === null || $this->site === $site;
    }
}
