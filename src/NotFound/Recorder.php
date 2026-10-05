<?php

namespace JothamLec\Seo\NotFound;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Counts a 404 against its path. Bots (by user agent) and scanner probes (by
 * path) are left out, and the table keeps at most `seo.not_found.max_rows`
 * paths, dropping the ones seen least recently.
 */
class Recorder
{
    public function shouldRecord(Request $request): bool
    {
        // HandleMissing only asks about GET and HEAD.
        if (! config('seo.not_found.enabled')) {
            return false;
        }

        $agent = strtolower((string) $request->userAgent());

        foreach ((array) config('seo.not_found.ignore_user_agents', []) as $bot) {
            if ($bot !== '' && str_contains($agent, strtolower($bot))) {
                return false;
            }
        }

        $path = $this->path($request);

        // Postgres refuses text that isn't UTF-8, and a probe is all such a path can be.
        return self::isText($path) && ! Str::is((array) config('seo.not_found.ignore_paths', []), $path);
    }

    public function record(Request $request): void
    {
        $path = $this->path($request);

        if (strlen($path) > 768) {
            return;
        }

        $referrer = self::webAddress($request->headers->get('referer'));
        $now = now();

        $updated = MissingPath::query()->where('path', $path)->update(array_filter([
            'hits' => DB::raw('hits + 1'),
            'last_seen_at' => $now,
            'referrer' => $referrer,
        ]));

        if ($updated) {
            return;
        }

        try {
            MissingPath::query()->create(['path' => $path, 'hits' => 1, 'referrer' => $referrer, 'first_seen_at' => $now, 'last_seen_at' => $now]);
        } catch (UniqueConstraintViolationException) {
            // Another request recorded the same path a moment ago.
            MissingPath::query()->where('path', $path)->increment('hits', 1, ['last_seen_at' => $now]);

            return;
        }

        $this->trim();
    }

    public function path(Request $request): string
    {
        return '/'.trim(rawurldecode($request->getPathInfo()), '/');
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

    private function trim(): void
    {
        $excess = MissingPath::query()->count() - max(1, (int) config('seo.not_found.max_rows', 1000));

        if ($excess > 0) {
            $stale = MissingPath::query()->orderBy('last_seen_at')->orderBy('id')->limit($excess)->pluck('id');
            MissingPath::query()->whereIn('id', $stale)->delete();
        }
    }
}
