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
 * Asks other sites whether the addresses a page links to still exist. It sends
 * a HEAD request (or a GET where HEAD isn't allowed), several at a time, and
 * keeps each answer for a day, so a link that many pages share is asked about
 * once. Only a clear miss counts as broken, which means a 404 or 410, or a host
 * that doesn't resolve. A refusal (401, 403, 429), a server error or a timeout
 * says nothing about the link, so it is left alone.
 *
 * Only the public internet is asked. A link (or a redirect) to this machine
 * or to a private network is not followed, and each request goes to the
 * address that was checked, so a page's links can't reach the server's own
 * network.
 *
 * A page's links share BUDGET seconds between them. Those still waiting after
 * that are left unchecked (not broken, and asked about again next time), so a
 * page of slow sites can't hold a report's step past the queue worker's timeout.
 * Where HEAD is refused, the GET is cut off once its headers are in, because
 * only the status and any redirect matter, not the body.
 */
class ExternalLinkChecker
{
    /** The most links checked per page. */
    private const int LIMIT = 50;

    private const int TIMEOUT = 8;

    /** The seconds allowed for all of one page's links. */
    private const int BUDGET = 30;

    /** The answer followed() gives when the budget ran out first. */
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
            // Looking hosts up takes time too, so the links left when the budget runs out stay unchecked.
            if ($this->secondsLeft() < 1) {
                break;
            }

            if (Cache::get($this->key($url)) !== null) {
                continue;
            }

            $address = $this->address($url);

            if ($address === null) {
                // A host that doesn't resolve is broken, but it is asked about again sooner,
                // because DNS may have failed for a moment.
                $this->remember($url, true, now()->addHour());
            } elseif ($address === false) {
                // A private address is never asked, so it is remembered as fine.
                $this->remember($url, false);
            } else {
                $addresses[$url] = $address;
            }
        }

        if ($addresses !== [] && $this->secondsLeft() >= 1) {
            $timeout = min(self::TIMEOUT, $this->secondsLeft());
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $url) => $this->request($pool->as($url), $url, $addresses[$url])->timeout($timeout)->head($url),
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
     * Gets the IP addresses that a host name resolves to, with IPv4 from the
     * hosts file and DNS, and IPv6 from DNS.
     *
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_AAAA) ?: [];

        return [...(gethostbynamel($host) ?: []), ...array_filter(array_column($records, 'ipv6'))];
    }

    /**
     * Gets the address to ask for $url. It returns null when the host doesn't
     * resolve, and false when it isn't on the public internet (or isn't http or https).
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
     * Determines whether $ip is on the public internet. PHP's global range treats
     * the NAT64 prefix 64:ff9b::/96 as public, but a NAT64 gateway passes such an
     * address on to the IPv4 address in its last 32 bits, which may be this
     * machine's (64:ff9b::7f00:1 is 127.0.0.1), so that IPv4 address is judged
     * instead. The local-use prefix 64:ff9b:1::/48 may embed an IPv4 address
     * anywhere, so it is refused. PHP already refuses 6to4 (2002::/16).
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
     * Gets the last answer for $url. It asks again with GET where HEAD was
     * refused, and follows redirects one by one, each to a public address. It
     * returns UNCHECKED when the page's budget runs out on the way.
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
     * @return Response|Throwable|string|false|null null when the host doesn't resolve, false when
     *                                              it isn't asked, or UNCHECKED when no time is left
     */
    private function send(string $method, string $url): Response|Throwable|string|false|null
    {
        $left = $this->secondsLeft();

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
            // Throwing here stops curl before the body, because the headers are all the check reads.
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
     * Builds a request pinned to the address that was checked, so DNS can't
     * answer differently when the connection is made. The request never goes
     * through a proxy, because on the command line (such as a queue worker)
     * Guzzle takes one from HTTP_PROXY and HTTPS_PROXY, and a proxy looks the
     * host up again itself. An empty proxy also stops curl from reading those
     * variables on its own.
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

    /**
     * Gets the seconds left of the page's budget.
     */
    private function secondsLeft(): int
    {
        return $this->deadline === null ? self::TIMEOUT : (int) now()->diffInSeconds($this->deadline, false);
    }

    private function isBroken(mixed $response): bool
    {
        return $response === null || ($response instanceof Response && in_array($response->status(), [404, 410], true));
    }

    private function remember(string $url, bool $broken, ?CarbonInterface $until = null): void
    {
        Cache::put($this->key($url), $broken ? 'broken' : 'fine', $until ?? now()->addDay());
    }

    private function key(string $url): string
    {
        return 'mt:external-link:'.md5($url);
    }
}
