<?php

namespace JothamLec\MarketingToolkit\NotFound;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use JothamLec\MarketingToolkit\Support\BelongsToSite;

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
    use BelongsToSite;

    public $timestamps = false;

    protected $table = 'mt_404s';

    protected $guarded = ['id'];

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
