<?php

namespace JothamLec\Seo\Commands;

use Illuminate\Console\Command;
use JothamLec\Seo\SearchConsole\Client;
use JothamLec\Seo\SearchConsole\Importer;
use Statamic\Console\RunsInPlease;
use Throwable;

/**
 * `php please seo:search-console`: fetches each page's clicks, impressions,
 * click-through rate and position from Google Search Console. The schedule
 * runs it daily once `seo.search_console` is set up.
 */
class SearchConsole extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:seo:search-console';

    protected $description = 'Import page numbers from Google Search Console';

    public function handle(Client $client, Importer $importer): int
    {
        if (! $client->configured()) {
            $this->components->error('Set SEO_SEARCH_CONSOLE_CREDENTIALS and SEO_SEARCH_CONSOLE_PROPERTY first (docs/configuration.md).');

            return self::FAILURE;
        }

        try {
            $count = $importer->import();
        } catch (Throwable $exception) {
            $this->components->error('Search Console said no: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Imported {$count} pages from Search Console.");

        return self::SUCCESS;
    }
}
