<?php

namespace JothamLec\MarketingToolkit\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use JothamLec\MarketingToolkit\Support\Sites;

/**
 * Belongs on a model whose rows have a `site` column, which holds a site's handle, or null on a
 * single site (and for rows from before the install had more than one).
 *
 * @property ?string $site
 */
trait BelongsToSite
{
    /**
     * Scopes the query to rows of exactly this site, and a null site means those of a single-site install.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOfSite(Builder $query, ?string $site): void
    {
        $site === null ? $query->whereNull('site') : $query->where('site', $site);
    }

    /**
     * Scopes the query to the rows the control panel shows while $site is selected, which are its own
     * and those from before the install had more than one site.
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
