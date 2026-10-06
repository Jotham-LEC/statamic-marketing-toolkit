<?php

namespace JothamLec\Seo\SearchConsole;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Addon;
use Statamic\Facades\Site;
use Throwable;

/**
 * How Search Console is set up, so Tools → SEO can walk someone through it:
 * the service account key and the property, from `.env` (which wins) or
 * from the control panel. A key uploaded there is kept in
 * storage/app/private, never in git; the property is an addon setting.
 * apply() hands what the control panel saved to the `seo.search_console`
 * config keys that Client reads.
 */
class Connection
{
    public const string SETTING = 'search_console_property';

    /**
     * Fills the config keys `.env` left empty from what the control panel saved.
     */
    public static function apply(): void
    {
        $connection = new self;

        if (blank(config('seo.search_console.credentials')) && File::exists($connection->keyPath())) {
            config(['seo.search_console.credentials' => $connection->keyPath()]);
        }

        if (blank(config('seo.search_console.property')) && filled($property = $connection->savedProperty())) {
            config(['seo.search_console.property' => $property]);
        }
    }

    public function keyPath(): string
    {
        return storage_path('app/private/seo/search-console-key.json');
    }

    /**
     * Where the key comes from: `env` (the config, typically `.env`), `cp` (uploaded) or null.
     */
    public function keySource(): ?string
    {
        $value = (string) config('seo.search_console.credentials');

        return match (true) {
            $value === '' => null,
            $value === $this->keyPath() => 'cp',
            default => 'env',
        };
    }

    public function propertySource(): ?string
    {
        $value = (string) config('seo.search_console.property');

        return match (true) {
            $value === '' => null,
            $value === $this->savedProperty() => 'cp',
            default => 'env',
        };
    }

    /**
     * The service account's email, which has to be a user of the property.
     */
    public function email(): ?string
    {
        $value = (string) config('seo.search_console.credentials');
        $json = $value !== '' && is_file($value) ? (string) file_get_contents($value) : $value;

        return self::parseKey($json)['client_email'] ?? null;
    }

    /**
     * The property to suggest: the site's domain, which covers http, https and
     * every subdomain; an address prefix where there is no domain (an IP, localhost).
     */
    public function suggestedProperty(): string
    {
        $url = Site::default()->absoluteUrl();
        $host = (string) parse_url($url, PHP_URL_HOST);

        return str_contains($host, '.') && ! filter_var($host, FILTER_VALIDATE_IP)
            ? 'sc-domain:'.preg_replace('/^www\./', '', $host)
            : rtrim($url, '/').'/';
    }

    /**
     * A service account key as Google Cloud downloads it, or null.
     *
     * @return array{client_email: string, private_key: string}|null
     */
    public static function parseKey(string $json): ?array
    {
        $key = json_decode($json, true);

        return is_array($key)
            && ($key['type'] ?? 'service_account') === 'service_account'
            && is_string($key['client_email'] ?? null)
            && is_string($key['private_key'] ?? null)
            && openssl_pkey_get_private($key['private_key']) !== false
            ? $key
            : null;
    }

    public function saveKey(string $json): void
    {
        File::ensureDirectoryExists(dirname($this->keyPath()), 0700);
        File::put($this->keyPath(), $json);
        chmod($this->keyPath(), 0600);
    }

    public function forgetKey(): void
    {
        File::delete($this->keyPath());
    }

    public function savedProperty(): ?string
    {
        try {
            $value = Addon::get('jotham-lec/statamic-co-seo')?->settings()->get(self::SETTING);
        } catch (Throwable) {
            return null; // Before Statamic has booted the addon.
        }

        return filled($value) ? trim((string) $value) : null;
    }

    public function saveProperty(?string $property): void
    {
        $settings = Addon::get('jotham-lec/statamic-co-seo')->settings();
        $settings->set(self::SETTING, filled($property) ? trim($property) : null);
        $settings->save();
    }

    /**
     * Asks Google whether the key may read the property, and says what to
     * fix in words a person setting it up can act on.
     *
     * @return array{ok: bool, message: string}
     */
    public function check(Client $client): array
    {
        if (! $client->configured()) {
            return ['ok' => false, 'message' => 'Add the key and the property first.'];
        }

        $property = (string) config('seo.search_console.property');

        try {
            $client->site();
        } catch (RequestException $exception) {
            return ['ok' => false, 'message' => $this->explain($exception, $property)];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }

        return ['ok' => true, 'message' => "Connected: the key can read {$property}."];
    }

    private function explain(RequestException $exception, string $property): string
    {
        $body = $exception->response->json() ?? [];
        $reason = (string) data_get($body, 'error.details.0.reason', data_get($body, 'error.status', ''));
        $message = (string) data_get($body, 'error.message', data_get($body, 'error_description', ''));
        $email = $this->email() ?? 'the service account';

        return match (true) {
            $reason === 'SERVICE_DISABLED' || str_contains($message, 'has not been used') => 'The Google Search Console API is not enabled in the key\'s Google Cloud project. Enable it, wait a minute, and check again.',
            in_array(data_get($body, 'error'), ['invalid_grant', 'unauthorized_client'], true) => 'Google refused the key: it may have been deleted in Google Cloud. Create a new key and upload it.',
            $exception->response->status() === 403 => "{$email} is not a user of {$property}. Add it in Search Console → Settings → Users and permissions.",
            $exception->response->status() === 404 => "Search Console has no property {$property}. Check how it is named there: sc-domain:example.com for a domain, https://example.com/ for an address prefix.",
            default => 'Search Console said: '.($message ?: 'error '.$exception->response->status()),
        };
    }
}
