<?php

namespace JothamLec\MarketingToolkit\NotFound;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use JothamLec\MarketingToolkit\Support\Sites;

/**
 * A path visitors asked for and got a 404: one row per path, with how often
 * and when, and the last page that linked to it. On a multi-site install,
 * one row per path per site.
 *
 * @property int $id
 * @property ?string $site null on a single site
 * @property string $path
 * @property int $hits
 * @property ?string $referrer
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 */
class MissingPath extends Model
{
    public $timestamps = false;

    protected $table = 'seo_404s';

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

    /**
     * The paths most recently missed that the control panel shows while $site
     * is selected, for the overview and the dashboard widget.
     *
     * @return list<array{path: string, hits: int}>
     */
    public static function recent(string $site, int $limit = 5): array
    {
        return self::query()->shownOn($site)->latest('last_seen_at')->limit($limit)->get()
            ->map(fn (self $row) => ['path' => $row->path, 'hits' => $row->hits])
            ->all();
    }

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
