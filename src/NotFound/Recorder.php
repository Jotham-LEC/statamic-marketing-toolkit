<?php

namespace JothamLec\MarketingToolkit\NotFound;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\Site;

/**
 * Counts a 404 against its path (and its site, where there are several). Bots (by user agent) and scanner probes (by
 * path) are left out, and the table keeps about `marketing-toolkit.not_found.max_rows`
 * paths, dropping one-off misses first, then the ones seen least recently.
 */
class Recorder
{
    public function shouldRecord(Request $request): bool
    {
        // HandleMissing only asks about GET and HEAD.
        if (! config('marketing-toolkit.not_found.enabled')) {
            return false;
        }

        $agent = strtolower((string) $request->userAgent());

        foreach ((array) config('marketing-toolkit.not_found.ignore_user_agents') as $bot) {
            if ($bot !== '' && str_contains($agent, strtolower($bot))) {
                return false;
            }
        }

        $path = $this->path($request);

        // Postgres refuses text that isn't UTF-8, and a probe is all such a path can be.
        return self::isText($path) && ! Str::is((array) config('marketing-toolkit.not_found.ignore_paths'), $path);
    }

    public function record(Request $request): void
    {
        $path = $this->path($request);

        if (strlen($path) > Redirect::MAX_SOURCE) {
            return;
        }

        $site = Sites::scope(Site::current()->handle());
        $referrer = self::webAddress($request->headers->get('referer'));
        $now = now();

        $updated = MissingPath::query()->ofSite($site)->where('path', $path)->update(array_filter([
            'hits' => DB::raw('hits + 1'),
            'last_seen_at' => $now,
            'referrer' => $referrer,
        ]));

        if ($updated) {
            return;
        }

        try {
            MissingPath::query()->create(['site' => $site, 'path' => $path, 'hits' => 1, 'referrer' => $referrer, 'first_seen_at' => $now, 'last_seen_at' => $now]);
        } catch (UniqueConstraintViolationException) {
            // Another request recorded the same path a moment ago.
            MissingPath::query()->ofSite($site)->where('path', $path)->increment('hits', 1, ['last_seen_at' => $now]);

            return;
        }

        $this->trim();
    }

    public function path(Request $request): string
    {
        return '/'.trim($request->decodedPath(), '/');
    }

    /**
     * An http(s) address fit to store and to link to, or null: the referrer is
     * whatever the request says, `javascript:` included.
     */
    public static function webAddress(?string $url): ?string
    {
        return $url !== null && strlen($url) <= 2048 && self::isText($url) && preg_match('#^https?://#i', $url) ? $url : null;
    }

    private static function isText(string $value): bool
    {
        return mb_check_encoding($value, 'UTF-8') && ! preg_match('/[\x00-\x1F\x7F]/', $value);
    }

    /**
     * Counting the rows on every new path would cost a full count per 404, so
     * only one new path in a tenth of the cap trims (a lottery, as Laravel
     * sweeps sessions): the log runs over by about a tenth, and a
     * small cap is kept exactly.
     */
    private function trim(): void
    {
        $max = max(1, (int) config('marketing-toolkit.not_found.max_rows'));

        if (random_int(1, max(1, intdiv($max, 10))) !== 1) {
            return;
        }

        $excess = MissingPath::query()->count() - $max;

        if ($excess > 0) {
            // One-off misses go first (one hit, no page of the site's linking there: what
            // a flood of made-up addresses looks like), so they can't push out the broken
            // links. Only the site's own pages count: a Referer header is whatever the
            // request says, so a flood could name any other.
            $internal = Site::all()
                ->map(fn ($site) => strtolower((string) parse_url((string) $site->absoluteUrl(), PHP_URL_HOST)))
                ->filter()->unique()
                ->flatMap(fn (string $host) => ["http://{$host}/%", "https://{$host}/%"])
                ->values()->all();
            $linked = str_repeat(' or referrer like ?', count($internal));

            $stale = MissingPath::query()
                ->orderByRaw("case when hits > 1{$linked} then 1 else 0 end", $internal)
                ->orderBy('last_seen_at')
                ->orderBy('id')
                ->limit($excess)
                ->pluck('id');
            MissingPath::query()->whereIn('id', $stale)->delete();
        }
    }
}
