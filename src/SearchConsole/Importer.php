<?php

namespace JothamLec\MarketingToolkit\SearchConsole;

use Illuminate\Support\Facades\DB;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\Site;

/**
 * Replaces the stored numbers with Search Console's for the last
 * `seo.search_console.days` days, one row per page. On a multi-site install,
 * a site's own rows from its own property, keeping only its own domain's
 * pages (a property may be shared by every site).
 */
class Importer
{
    public function __construct(private Client $client) {}

    /**
     * @return int the pages imported
     */
    /**
     * @param  ?string  $site  a site handle; null: the current site
     */
    public function import(?string $site = null): int
    {
        $site ??= Site::current()->handle();
        $stored = Sites::scope($site);
        $to = now('America/Los_Angeles')->toDateString(); // Search Console's dates are Pacific time.
        $from = now('America/Los_Angeles')->subDays(max(1, (int) config('seo.search_console.days', 28)) - 1)->toDateString();
        $rows = $this->client->pages($from, $to, $site);
        $now = now();

        if ($stored !== null) {
            $host = preg_replace('/^www\./', '', (string) parse_url((string) Site::get($site)?->absoluteUrl(), PHP_URL_HOST));
            $rows = array_values(array_filter($rows, fn (array $row) => preg_replace('/^www\./', '', (string) parse_url((string) ($row['keys'][0] ?? ''), PHP_URL_HOST)) === $host));
        }

        DB::transaction(function () use ($rows, $from, $to, $now, $stored) {
            // A site's import also replaces the rows from before there were several sites.
            SearchStat::query()->where(fn ($query) => $query->whereNull('site')->when($stored, fn ($query, string $stored) => $query->orWhere('site', $stored)))->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                SearchStat::query()->insert(array_map(fn (array $row) => [
                    'site' => $stored,
                    'url' => (string) ($row['keys'][0] ?? ''),
                    'clicks' => (int) $row['clicks'],
                    'impressions' => (int) $row['impressions'],
                    'ctr' => (float) $row['ctr'],
                    'position' => (float) $row['position'],
                    'from' => $from,
                    'to' => $to,
                    'fetched_at' => $now,
                ], $chunk));
            }
        });

        return count($rows);
    }
}
