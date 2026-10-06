<?php

namespace JothamLec\Seo\IndexNow;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\Site;
use Throwable;

/**
 * IndexNow (indexnow.org): tells Bing, Yandex, Naver, Seznam and the other
 * participating engines which addresses changed, so they recrawl them now
 * rather than on their next visit. Google does not take part. The addresses
 * a request changes are sent together once it has been answered, only in
 * production, and a failure is logged, never shown to the editor.
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

    public function flush(): void
    {
        $urls = $this->queued();
        $this->urls = [];

        if ($urls === [] || ! $this->enabled()) {
            return;
        }

        $home = rtrim(Site::default()->absoluteUrl(), '/');

        try {
            Http::timeout(5)->post(self::ENDPOINT, [
                'host' => parse_url($home, PHP_URL_HOST),
                'key' => $this->key(),
                'keyLocation' => $home.'/'.$this->key().'.txt',
                'urlList' => $urls,
            ])->throw();
        } catch (Throwable $exception) {
            Log::warning('IndexNow could not be told about '.count($urls).' changed addresses: '.$exception->getMessage());
        }
    }
}
