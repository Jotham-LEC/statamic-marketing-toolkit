<?php

namespace JothamLec\MarketingToolkit\SearchConsole;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use JothamLec\MarketingToolkit\Support\BelongsToSite;

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
    use BelongsToSite;

    protected $table = 'mt_search_stats';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from' => 'date', 'to' => 'date', 'fetched_at' => 'datetime', 'ctr' => 'float', 'position' => 'float'];
    }
}
