<?php

namespace JothamLec\MarketingToolkit\Reports;

use Carbon\CarbonInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Asks other sites whether the addresses a page links to still exist: a
 * HEAD request (a GET where HEAD isn't allowed), several at a time, each
 * answer kept for a day so a link many pages share is asked once. Only a
 * clear miss counts as broken: a 404 or 410, or a host that doesn't
 * resolve. A refusal (401, 403, 429), a server error or a timeout says
 * nothing about the link and is left alone.
 *
 * Only the public internet is asked. A link (or a redirect) to this machine
 * or a private network is not followed, and each request goes to the
 * address that was checked, so a page's links can't reach the server's own
 * network.
 *
 * A page's links get BUDGET seconds between them; those still waiting after
 * that are left unchecked (not broken, and asked again next time), so a page
 * of slow sites can't hold a report's step past the queue worker's timeout.
 * Where HEAD is refused, the GET is cut off once its headers are in: only
 * the status and any redirect matter, not the body.
 */
class ExternalLinkChecker
{
    /** Most links checked per page. */
    private const int LIMIT = 50;

    private const int TIMEOUT = 8;

    /** Seconds for all of one page's links. */
    private const int BUDGET = 30;

    /** followed()'s answer when the budget ran out first. */
    private const string UNCHECKED = 'unchecked';

    private const int MAX_REDIRECTS = 5;

    private const string USER_AGENT = 'Mozilla/5.0 (compatible; statamic-marketing-toolkit link check)';

    private ?CarbonInterface $deadline = null;

    /**
     * @param  list<string>  $urls
     * @return list<string> the broken ones
     */
    public function broken(array $urls): array
    {
        $urls = array_slice(array_values(array_unique($urls)), 0, self::LIMIT);
        $addresses = [];
        $this->deadline = now()->addSeconds(self::BUDGET);

        foreach ($urls as $url) {
            if (Cache::get($this->key($url)) !== null) {
                continue;
            }

            $address = $this->address($url);

            // A host that doesn't resolve is decided already; a private one is never asked.
            is_string($address) ? $addresses[$url] = $address : $this->remember($url, $address === null);
        }

        if ($addresses !== []) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $url) => $this->request($pool->as($url), $url, $addresses[$url])->head($url),
                array_keys($addresses),
            ));

            foreach (array_keys($addresses) as $url) {
                $response = $this->followed($url, $responses[$url] ?? null);

                if ($response !== self::UNCHECKED) {
                    $this->remember($url, $this->isBroken($response));
                }
            }
        }

        return array_values(array_filter($urls, fn (string $url) => Cache::get($this->key($url)) === 'broken'));
    }

    /**
     * The IP addresses a host name resolves to (IPv4 from the hosts file and
     * DNS, IPv6 from DNS).
     *
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_AAAA) ?: [];

        return [...(gethostbynamel($host) ?: []), ...array_filter(array_column($records, 'ipv6'))];
    }

    /**
     * The address to ask for $url: null when its host doesn't resolve, false
     * when it isn't on the public internet (or isn't http or https).
     */
    private function address(string $url): string|false|null
    {
        $parts = parse_url($url);
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if ($host === '' || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                return false;
            }
        }

        return $ips[0];
    }

    /**
     * Whether $ip is on the public internet. PHP's global range takes the
     * NAT64 prefix 64:ff9b::/96 as public, but a NAT64 gateway passes it on
     * to the IPv4 address in its last 32 bits, which may be this machine's
     * (64:ff9b::7f00:1 is 127.0.0.1): that address is judged instead. The
     * local-use prefix 64:ff9b:1::/48 may embed one anywhere, so is refused.
     * (6to4, 2002::/16, PHP refuses already.)
     */
    private static function isPublic(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
            return false;
        }

        $packed = (string) inet_pton($ip);

        if (strlen($packed) !== 16) {
            return true;
        }

        if (str_starts_with($packed, "\x00\x64\xff\x9b\x00\x01")) {
            return false;
        }

        if (! str_starts_with($packed, "\x00\x64\xff\x9b".str_repeat("\x00", 8))) {
            return true;
        }

        return self::isPublic((string) inet_ntop(substr($packed, 12)));
    }

    /**
     * The last answer for $url: asked again with GET where HEAD was refused,
     * and redirects followed one by one, each to a public address. UNCHECKED
     * when the page's budget runs out on the way.
     */
    private function followed(string $url, mixed $response): mixed
    {
        for ($hops = 0; ; $hops++) {
            if ($response instanceof Response && in_array($response->status(), [403, 405, 501], true)) {
                $response = $this->send('get', $url);
            }

            if ($response === self::UNCHECKED) {
                return $response;
            }

            $location = $response instanceof Response && $response->redirect() ? $response->header('Location') : '';

            if ($location === '' || $hops === self::MAX_REDIRECTS) {
                return $response;
            }

            $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
            $response = $this->send('head', $url);
        }
    }

    /**
     * @return Response|Throwable|string|false|null null: the host doesn't resolve; false: not asked; UNCHECKED: no time left
     */
    private function send(string $method, string $url): Response|Throwable|string|false|null
    {
        $left = $this->deadline === null ? self::TIMEOUT : (int) now()->diffInSeconds($this->deadline, false);

        if ($left < 1) {
            return self::UNCHECKED;
        }

        $address = $this->address($url);

        if (! is_string($address)) {
            return $address;
        }

        $headers = null;
        $request = $this->request(Http::createPendingRequest(), $url, $address)->timeout(min(self::TIMEOUT, $left));

        if ($method === 'get') {
            // Throwing here stops curl before the body: the headers are all the check reads.
            $request->withOptions(['on_headers' => function (ResponseInterface $response) use (&$headers) {
                $headers = $response;

                throw new RuntimeException('Body not wanted.');
            }]);
        }

        try {
            return $request->send($method, $url);
        } catch (Throwable $exception) {
            return $headers instanceof ResponseInterface ? new Response($headers) : $exception;
        }
    }

    /**
     * A request pinned to the address that was checked, so DNS can't answer
     * differently when the connection is made. Never through a proxy: on the
     * command line (a queue worker) Guzzle takes one from HTTP_PROXY and
     * HTTPS_PROXY, and a proxy looks the host up again itself; an empty
     * proxy also stops curl reading those variables on its own.
     */
    private function request(PendingRequest $request, string $url, string $address): PendingRequest
    {
        $parts = parse_url($url);
        $port = $parts['port'] ?? (strtolower((string) $parts['scheme']) === 'https' ? 443 : 80);
        $ip = str_contains($address, ':') ? "[{$address}]" : $address;

        return $request
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(self::TIMEOUT)
            ->withoutRedirecting()
            ->withOptions(['proxy' => '', 'curl' => [CURLOPT_RESOLVE => [trim((string) $parts['host'], '[]').":{$port}:{$ip}"]]]);
    }

    private function isBroken(mixed $response): bool
    {
        return $response === null || ($response instanceof Response && in_array($response->status(), [404, 410], true));
    }

    private function remember(string $url, bool $broken): void
    {
        Cache::put($this->key($url), $broken ? 'broken' : 'fine', now()->addDay());
    }

    private function key(string $url): string
    {
        return 'mt:external-link:'.md5($url);
    }
}
