<?php

namespace JothamLec\Seo\SearchConsole;

use Illuminate\Database\Eloquent\Model;

/**
 * One page's numbers from Google Search Console over the last import's
 * period: clicks, impressions, click-through rate and average position.
 *
 * @property string $url
 * @property int $clicks
 * @property int $impressions
 * @property float $ctr
 * @property float $position
 */
class SearchStat extends Model
{
    protected $table = 'seo_search_stats';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['from' => 'date', 'to' => 'date', 'fetched_at' => 'datetime', 'ctr' => 'float', 'position' => 'float'];
    }
}
