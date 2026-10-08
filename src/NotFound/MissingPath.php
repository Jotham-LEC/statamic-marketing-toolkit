<?php

namespace JothamLec\MarketingToolkit\NotFound;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use JothamLec\MarketingToolkit\Models\Concerns\BelongsToSite;
use Statamic\Facades\Site;

/**
 * This model records a path that visitors asked for and got a 404. There is one row per path, holding how
 * often and when it was missed, and the last page that linked to it. On a multi-site install, there is one
 * row per path per site.
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
    use BelongsToSite, MassPrunable;

    public $timestamps = false;

    protected $table = 'mt_404s';

    protected $guarded = ['id'];

    /**
     * Returns the most recently missed paths that the control panel shows while $site is selected, for the
     * overview and the dashboard widget.
     *
     * @return list<array{path: string, hits: int}>
     */
    public static function recent(string $site, int $limit = 5): array
    {
        return self::query()->shownOn($site)->latest('last_seen_at')->limit($limit)->get()
            ->map(fn (self $row) => ['path' => $row->path, 'hits' => $row->hits])
            ->all();
    }

    /**
     * Returns the largest number of paths the log keeps (`marketing-toolkit.not_found.max_rows`).
     */
    public static function maxRows(): int
    {
        return max(1, (int) config('marketing-toolkit.not_found.max_rows'));
    }

    /**
     * Returns the rows beyond maxRows() for Laravel's `model:prune`, which the addon schedules daily.
     * One-off misses go first (one hit, and no page of the site linking there, which is what a flood of
     * made-up addresses looks like), so they can't push out the broken links. The other rows first seen
     * within the last day go next, so a flood of addresses that are each asked for twice can't push them
     * out either. Within each group, the least recently seen rows go first.
     *
     * Only the site's own pages count as linking, because a Referer header says whatever the request
     * wants, so a flood could name any other page. This is a best effort, because the client controls the
     * hits and the Referer, and a flood that is kept up for days still wins in the end.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        $excess = self::query()->count() - self::maxRows();

        if ($excess <= 0) {
            return self::query()->whereRaw('1 = 0');
        }

        $internal = Site::all()
            ->map(fn ($site) => strtolower((string) parse_url((string) $site->absoluteUrl(), PHP_URL_HOST)))
            ->filter()->unique()
            ->flatMap(fn (string $host) => ["http://{$host}/%", "https://{$host}/%"])
            ->values()->all();
        $recurs = 'hits > 1'.str_repeat(' or referrer like ?', count($internal));

        // We work out the IDs once, because model:prune deletes in chunks until nothing is left to delete.
        $stale = self::query()
            // A null referrer makes the test null rather than false, which is why we use `case when … else 0`.
            ->orderByRaw("case when {$recurs} then (case when first_seen_at > ? then 1 else 2 end) else 0 end", [...$internal, now()->subDay()])
            ->orderBy('last_seen_at')
            ->orderBy('id')
            ->limit($excess)
            ->pluck('id');

        return self::query()->whereIn('id', $stale);
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
