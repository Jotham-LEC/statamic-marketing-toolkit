<?php

namespace JothamLec\MarketingToolkit\IndexNow;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * IndexNow (indexnow.org): tells Bing, Yandex, Naver, Seznam and the other
 * participating engines which addresses changed, so they recrawl them now
 * rather than on their next visit. Google does not take part. The addresses
 * a request changes are sent together once it has been answered, only in
 * production, one request per domain, and a failure is logged, never shown
 * to the editor.
 */
class IndexNow
{
    /** Shares the addresses with every participating engine. */
    private const string ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** @var array<string, true> */
    private array $urls = [];

    public function enabled(): bool
    {
        return (bool) config('seo.indexnow.enabled') && app()->isProduction();
    }

    /**
     * The site's key: config `seo.indexnow.key`, else one derived from the app
     * key, so it stays the same across deploys without setting anything.
     */
    public function key(): string
    {
        $key = config('seo.indexnow.key');

        return is_string($key) && preg_match('/^[A-Za-z0-9-]{8,128}$/', $key) ? $key : substr(hash('sha256', config('app.key').'|indexnow'), 0, 32);
    }

    public function queue(?string $url): void
    {
        if ($url !== null && $url !== '' && $this->enabled()) {
            $this->urls[$url] = true;
        }
    }

    /**
     * @return list<string>
     */
    public function queued(): array
    {
        return array_keys($this->urls);
    }

    /**
     * Sends the queued addresses, one request per host: IndexNow takes one
     * host per request, and each site's domain serves the key itself.
     */
    public function flush(): void
    {
        $urls = $this->queued();
        $this->urls = [];

        if ($urls === [] || ! $this->enabled()) {
            return;
        }

        $byHost = collect($urls)->groupBy(fn (string $url) => strtolower((string) parse_url($url, PHP_URL_SCHEME)).'://'.strtolower((string) parse_url($url, PHP_URL_HOST)));

        foreach ($byHost as $home => $list) {
            try {
                Http::timeout(5)->post(self::ENDPOINT, [
                    'host' => parse_url($home, PHP_URL_HOST),
                    'key' => $this->key(),
                    'keyLocation' => $home.'/'.$this->key().'.txt',
                    'urlList' => $list->values()->all(),
                ])->throw();
            } catch (Throwable $exception) {
                Log::warning('IndexNow could not be told about '.count($list).' changed addresses on '.$home.': '.$exception->getMessage());
            }
        }
    }
}
