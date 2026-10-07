<?php

namespace JothamLec\MarketingToolkit\SearchConsole;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use JothamLec\MarketingToolkit\Support\Sites;

/**
 * One page's numbers from Google Search Console over the last import's
 * period: clicks, impressions, click-through rate and average position.
 * On a multi-site install, from the property of the site it names.
 *
 * @property ?string $site null on a single site
 * @property string $url
 * @property int $clicks
 * @property int $impressions
 * @property float $ctr
 * @property float $position
 * @property Carbon $from
 * @property Carbon $to
 * @property Carbon $fetched_at
 */
class SearchStat extends Model
{
    protected $table = 'mt_search_stats';

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * Rows of exactly this site; null: those of a single-site install.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOfSite(Builder $query, ?string $site): void
    {
        $site === null ? $query->whereNull('site') : $query->where('site', $site);
    }

    /**
     * The rows the control panel shows while $site is selected: its own, and
     * those from before the install had more than one site.
     *
     * @param  Builder<self>  $query
     */
    public function scopeShownOn(Builder $query, string $site): void
    {
        if (Sites::multiple()) {
            $query->where(fn (Builder $query) => $query->where('site', $site)->orWhereNull('site'));
        }
    }

    protected function casts(): array
    {
        return ['from' => 'date', 'to' => 'date', 'fetched_at' => 'datetime', 'ctr' => 'float', 'position' => 'float'];
    }
}
