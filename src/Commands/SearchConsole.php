<?php

namespace JothamLec\MarketingToolkit\Commands;

use Illuminate\Console\Command;
use JothamLec\MarketingToolkit\SearchConsole\Client;
use JothamLec\MarketingToolkit\SearchConsole\Connection;
use JothamLec\MarketingToolkit\SearchConsole\Importer;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Site;
use Throwable;

/**
 * `php please seo:search-console`: fetches each page's clicks, impressions,
 * click-through rate and position from Google Search Console. The schedule
 * runs it daily once `seo.search_console` is set up. On a multi-site install
 * it imports each site that has a property, or only `--site`.
 */
class SearchConsole extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:seo:search-console {--site= : The handle of one site to import (default: every site with a property)}';

    protected $description = 'Import page numbers from Google Search Console';

    public function handle(Client $client, Importer $importer, Connection $connection): int
    {
        $site = $this->option('site');

        if ($site !== null && ! Site::get($site)) {
            $this->components->error("There is no site [{$site}].");

            return self::FAILURE;
        }

        $sites = match (true) {
            $site !== null => [$site],
            Sites::multiple() => $connection->sitesWithProperty(),
            default => [Site::default()->handle()],
        };

        if ($sites === [] || ! collect($sites)->every(fn (string $site) => $client->configured($site))) {
            $this->components->error('Set SEO_SEARCH_CONSOLE_CREDENTIALS and SEO_SEARCH_CONSOLE_PROPERTY first, or connect it under Tools → SEO (docs/configuration.md).');

            return self::FAILURE;
        }

        $result = self::SUCCESS;

        foreach ($sites as $handle) {
            $on = Sites::multiple() ? ' for '.Site::get($handle)->name() : '';

            try {
                $count = $importer->import($handle);
                $this->components->info("Imported {$count} pages{$on} from Search Console.");
            } catch (Throwable $exception) {
                $this->components->error("Search Console said no{$on}: ".$exception->getMessage());
                $result = self::FAILURE;
            }
        }

        return $result;
    }
}
