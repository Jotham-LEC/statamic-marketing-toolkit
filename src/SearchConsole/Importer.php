<?php

namespace JothamLec\Seo\SearchConsole;

use Illuminate\Support\Facades\DB;

/**
 * Replaces the stored numbers with Search Console's for the last
 * `seo.search_console.days` days, one row per page.
 */
class Importer
{
    public function __construct(private Client $client) {}

    /**
     * @return int the pages imported
     */
    public function import(): int
    {
        $to = now('America/Los_Angeles')->toDateString(); // Search Console's dates are Pacific time.
        $from = now('America/Los_Angeles')->subDays(max(1, (int) config('seo.search_console.days', 28)) - 1)->toDateString();
        $rows = $this->client->pages($from, $to);
        $now = now();

        DB::transaction(function () use ($rows, $from, $to, $now) {
            SearchStat::query()->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                SearchStat::query()->insert(array_map(fn (array $row) => [
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
