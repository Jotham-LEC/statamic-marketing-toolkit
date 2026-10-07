<?php

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use JothamLec\MarketingToolkit\SearchConsole\SearchStat;

/**
 * A service account key made up for the test, and its public half.
 *
 * @return array{0: string, 1: string}
 */
function serviceAccountKey(): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $private);

    return [json_encode(['client_email' => 'seo@project.iam.gserviceaccount.com', 'private_key' => $private]), openssl_pkey_get_details($key)['key']];
}

test('imports each page\'s numbers, signed in as the service account', function () {
    [$credentials, $public] = serviceAccountKey();
    config(['marketing-toolkit.search_console' => ['credentials' => $credentials, 'property' => 'sc-domain:example.test', 'days' => 28]]);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599]),
        'www.googleapis.com/webmasters/*' => Http::response(['rows' => [
            ['keys' => ['https://example.test/about'], 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.035, 'position' => 7.4],
            ['keys' => ['https://example.test/'], 'clicks' => 30, 'impressions' => 500, 'ctr' => 0.06, 'position' => 3.1],
        ]]),
    ]);
    SearchStat::query()->insert(['url' => 'https://example.test/stale', 'clicks' => 1, 'impressions' => 1, 'ctr' => 1, 'position' => 1, 'from' => '2026-01-01', 'to' => '2026-01-28', 'fetched_at' => now()]);

    $this->artisan('statamic:mt:search-console')->assertSuccessful();

    Http::assertSent(function (HttpRequest $request) use ($public) {
        if ($request->url() !== 'https://oauth2.googleapis.com/token') {
            return false;
        }

        [$header, $claims, $signature] = explode('.', $request['assertion']);
        $decode = fn (string $part) => base64_decode(strtr($part, '-_', '+/'));
        $claims = json_decode($decode($claims), true);

        return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && $claims['iss'] === 'seo@project.iam.gserviceaccount.com'
            && $claims['scope'] === 'https://www.googleapis.com/auth/webmasters.readonly'
            && $claims['aud'] === 'https://oauth2.googleapis.com/token'
            && openssl_verify("{$header}.".explode('.', $request['assertion'])[1], $decode($signature), $public, OPENSSL_ALGO_SHA256) === 1;
    });
    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Aexample.test/searchAnalytics/query'
        && $request->hasHeader('Authorization', 'Bearer token-1')
        && $request['dimensions'] === ['page']
        && $request['dataState'] === 'all');

    expect(SearchStat::query()->orderByDesc('clicks')->pluck('clicks', 'url')->all())->toBe(['https://example.test/' => 30, 'https://example.test/about' => 12]);
});

test('without credentials the command says what to set, and the overview shows nothing', function () {
    $this->artisan('statamic:mt:search-console')->assertFailed();

    $this->actingAs(cpUser(super: true))->get(cp_route('mt.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('search', null));
});

test('the overview shows the totals and the pages with the most clicks', function () {
    config(['marketing-toolkit.search_console' => ['credentials' => '{}', 'property' => 'sc-domain:example.test']]);
    SearchStat::query()->insert([
        ['url' => 'https://example.test/about', 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.03, 'position' => 7.44, 'from' => '2026-09-08', 'to' => '2026-10-05', 'fetched_at' => now()],
        ['url' => 'https://example.test/', 'clicks' => 30, 'impressions' => 500, 'ctr' => 0.06, 'position' => 3.1, 'from' => '2026-09-08', 'to' => '2026-10-05', 'fetched_at' => now()],
    ]);

    $this->actingAs(cpUser(super: true))->get(cp_route('mt.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('search.clicks', 42)
        ->where('search.impressions', 840)
        ->where('search.from', '2026-09-08')
        ->where('search.top.0', ['path' => '/', 'clicks' => 30, 'impressions' => 500, 'position' => 3.1])
        ->where('search.top.1.position', 7.4));
});

test('a site with more pages than one answer holds is read in turns', function () {
    [$credentials] = serviceAccountKey();
    config(['marketing-toolkit.search_console' => ['credentials' => $credentials, 'property' => 'https://example.test/', 'days' => 28]]);
    $row = fn (int $i) => ['keys' => ["https://example.test/p{$i}"], 'clicks' => 1, 'impressions' => 2, 'ctr' => 0.5, 'position' => 3.0];
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599]),
        'www.googleapis.com/webmasters/*' => Http::sequence()
            ->push(['rows' => array_map($row, range(1, 25000))])
            ->push(['rows' => array_map($row, range(25001, 25003))]),
    ]);

    $this->artisan('statamic:mt:search-console')->assertSuccessful();

    expect(SearchStat::query()->count())->toBe(25003);
    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'searchAnalytics') && $request['startRow'] === 25000);
});

test('the access token is kept per key, so a new key for the same account signs in afresh', function () {
    [$credentials] = serviceAccountKey();
    config(['marketing-toolkit.search_console' => ['credentials' => $credentials, 'property' => 'sc-domain:example.test', 'days' => 28]]);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::sequence()->push(['access_token' => 'token-1'])->push(['access_token' => 'token-2']),
        'www.googleapis.com/webmasters/*' => Http::response(['rows' => []]),
    ]);

    $this->artisan('statamic:mt:search-console')->assertSuccessful();
    $this->artisan('statamic:mt:search-console')->assertSuccessful();

    [$replaced] = serviceAccountKey();
    config(['marketing-toolkit.search_console.credentials' => $replaced]);
    $this->artisan('statamic:mt:search-console')->assertSuccessful();

    Http::assertSentCount(5);
    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'searchAnalytics') && $request->hasHeader('Authorization', 'Bearer token-2'));
});

test('an answer without an access token is not kept', function () {
    [$credentials] = serviceAccountKey();
    config(['marketing-toolkit.search_console' => ['credentials' => $credentials, 'property' => 'sc-domain:example.test', 'days' => 28]]);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::sequence()->push([])->push(['access_token' => 'token-1']),
        'www.googleapis.com/webmasters/*' => Http::response(['rows' => []]),
    ]);

    $this->artisan('statamic:mt:search-console')->assertFailed();
    $this->artisan('statamic:mt:search-console')->assertSuccessful();

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'searchAnalytics') && $request->hasHeader('Authorization', 'Bearer token-1'));
});
