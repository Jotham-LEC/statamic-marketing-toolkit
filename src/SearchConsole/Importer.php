<?php

namespace JothamLec\MarketingToolkit\SearchConsole;

use Illuminate\Support\Facades\DB;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\Site;

/**
 * Replaces the stored numbers with Search Console's for the last
 * `marketing-toolkit.search_console.days` days, one row per page. On a multi-site install,
 * a site's own rows from its own property, keeping only its own pages (a
 * property may be shared by every site, and sites may share a domain, one
 * under another's path).
 */
class Importer
{
    /** @var ?array<string, string> each site's address as comparable(), longest first */
    private ?array $prefixes = null;

    public function __construct(private Client $client) {}

    /**
     * @param  ?string  $site  a site handle; null: the current site
     * @return int the pages imported
     */
    public function import(?string $site = null): int
    {
        $site ??= Site::current()->handle();
        $stored = Sites::scope($site);
        $to = now('America/Los_Angeles')->toDateString(); // Search Console's dates are Pacific time.
        $from = now('America/Los_Angeles')->subDays(max(1, (int) config('marketing-toolkit.search_console.days')) - 1)->toDateString();
        $rows = $this->client->pages($from, $to, $site);
        $now = now();

        if ($stored !== null) {
            $rows = array_values(array_filter($rows, fn (array $row) => $this->siteOf((string) ($row['keys'][0] ?? '')) === $site));
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

    /**
     * The site a page belongs to: the one whose address is the longest start
     * of it (`example.test/fr/` before `example.test/`), whatever the scheme
     * or a `www.`.
     */
    private function siteOf(string $url): ?string
    {
        $this->prefixes ??= Site::all()
            ->mapWithKeys(fn ($site) => [$site->handle() => self::comparable((string) $site->absoluteUrl())])
            ->sortByDesc(fn (string $prefix) => strlen($prefix))
            ->all();
        $url = self::comparable($url);

        foreach ($this->prefixes as $handle => $prefix) {
            if (str_starts_with($url, $prefix)) {
                return $handle;
            }
        }

        return null;
    }

    /**
     * An address as host and path, ending in a slash: `https://www.Example.test/fr` → `example.test/fr/`.
     */
    private static function comparable(string $url): string
    {
        $host = preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));

        return $host.rtrim((string) parse_url($url, PHP_URL_PATH), '/').'/';
    }
}
