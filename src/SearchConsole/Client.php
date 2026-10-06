<?php

namespace JothamLec\Seo\SearchConsole;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Search Console's Search Analytics API, signed in as a service
 * account: a JSON key from Google Cloud whose email is a user of the
 * property. No client library: a JWT signed with the key buys an access
 * token, kept until shortly before it expires.
 */
class Client
{
    private const string SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    private const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** The most rows the Search Analytics API returns at once. */
    private const int ROW_LIMIT = 25000;

    public function configured(): bool
    {
        return filled(config('seo.search_console.credentials')) && filled(config('seo.search_console.property'));
    }

    /**
     * Clicks, impressions, CTR and position per page between two dates.
     *
     * @return list<array{keys: list<string>, clicks: int|float, impressions: int|float, ctr: float, position: float}>
     */
    public function pages(string $from, string $to): array
    {
        $property = rawurlencode((string) config('seo.search_console.property'));
        $rows = [];

        // At most ROW_LIMIT rows an answer: a site with more pages is read in turns.
        do {
            $batch = Http::withToken($this->token())
                ->timeout(30)
                ->post("https://www.googleapis.com/webmasters/v3/sites/{$property}/searchAnalytics/query", [
                    'startDate' => $from,
                    'endDate' => $to,
                    'dimensions' => ['page'],
                    'rowLimit' => self::ROW_LIMIT,
                    'startRow' => count($rows),
                    'dataState' => 'all',
                ])
                ->throw()
                ->json('rows', []);

            $rows = [...$rows, ...$batch];
        } while (count($batch) === self::ROW_LIMIT);

        return $rows;
    }

    private function token(): string
    {
        $credentials = $this->credentials();

        return Cache::remember('seo:search-console-token:'.md5($credentials['client_email']), now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $segments = [
                $this->base64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64(json_encode(['iss' => $credentials['client_email'], 'scope' => self::SCOPE, 'aud' => self::TOKEN_URL, 'iat' => $now, 'exp' => $now + 3600])),
            ];

            if (! openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('The Search Console key could not sign a request.');
            }

            return (string) Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', [...$segments, $this->base64($signature)]),
            ])->throw()->json('access_token');
        });
    }

    /**
     * The service account key: a path to its JSON file, or the JSON itself.
     *
     * @return array{client_email: string, private_key: string}
     */
    private function credentials(): array
    {
        $value = (string) config('seo.search_console.credentials');
        $json = is_file($value) ? (string) file_get_contents($value) : $value;
        $credentials = json_decode($json, true);

        if (! is_array($credentials) || ! isset($credentials['client_email'], $credentials['private_key'])) {
            throw new RuntimeException('seo.search_console.credentials is neither a service account key nor the path to one.');
        }

        return $credentials;
    }

    private function base64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
