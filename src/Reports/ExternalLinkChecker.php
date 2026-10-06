<?php

namespace JothamLec\Seo\Reports;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Asks other sites whether the addresses a page links to still exist: a
 * HEAD request (a GET where HEAD isn't allowed), several at a time, each
 * answer kept for a day so a link many pages share is asked once. Only a
 * clear miss counts as broken: a 404 or 410, or a host that doesn't
 * resolve. A refusal (401, 403, 429), a server error or a timeout says
 * nothing about the link and is left alone.
 */
class ExternalLinkChecker
{
    /** Most links checked per page. */
    private const int LIMIT = 50;

    private const int TIMEOUT = 8;

    /**
     * @param  list<string>  $urls
     * @return list<string> the broken ones
     */
    public function broken(array $urls): array
    {
        $urls = array_slice(array_values(array_unique($urls)), 0, self::LIMIT);
        $unknown = array_values(array_filter($urls, fn (string $url) => Cache::get($this->key($url)) === null));

        if ($unknown !== []) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $url) => $pool->as($url)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; statamic-co-seo link check)'])->timeout(self::TIMEOUT)->head($url),
                $unknown,
            ));

            foreach ($unknown as $url) {
                $response = $responses[$url] ?? null;

                // Some servers refuse HEAD; ask them with GET.
                if ($response instanceof Response && in_array($response->status(), [403, 405, 501], true)) {
                    $response = $this->get($url);
                }

                Cache::put($this->key($url), $this->isBroken($response) ? 'broken' : 'fine', now()->addDay());
            }
        }

        return array_values(array_filter($urls, fn (string $url) => Cache::get($this->key($url)) === 'broken'));
    }

    private function get(string $url): Response|Throwable
    {
        try {
            return Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; statamic-co-seo link check)'])->timeout(self::TIMEOUT)->get($url);
        } catch (Throwable $exception) {
            return $exception;
        }
    }

    private function isBroken(mixed $response): bool
    {
        if ($response instanceof Response) {
            return in_array($response->status(), [404, 410], true);
        }

        return $response instanceof ConnectionException && str_contains($response->getMessage(), 'resolve host');
    }

    private function key(string $url): string
    {
        return 'seo:external-link:'.md5($url);
    }
}
