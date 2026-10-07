<?php

namespace JothamLec\MarketingToolkit\SearchConsole;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use JothamLec\MarketingToolkit\Support\Package;
use Statamic\Facades\Addon;
use Statamic\Facades\Site;
use Throwable;

/**
 * How Search Console is set up, so Tools → SEO can walk someone through it:
 * the service account key and the property, from `.env` (which wins) or
 * from the control panel. A key uploaded there is kept in
 * storage/app/private, encrypted with APP_KEY, never in git; the property is
 * an addon setting. apply() hands an uploaded key's path to the
 * `marketing-toolkit.search_console.credentials` config key, and readKey() turns that
 * config value into the key's JSON.
 *
 * One key serves every site (a service account can be a user of several
 * properties). The property is per site: `marketing-toolkit.search_console.property` is a
 * string (every site) or a map of site handle => property; else the control
 * panel's, saved for the default site as before and for each other site in
 * a second setting.
 */
class Connection
{
    public const string SETTING = 'search_console_property';

    /** The properties of the sites other than the default: site handle => property. */
    public const string SITES_SETTING = 'search_console_properties';

    /** Google's guide to enabling a disabled service account key. */
    public const string KEYS_GUIDE = 'https://docs.cloud.google.com/iam/docs/keys-disable-enable';

    /** Where an organization policy can stop new service account keys being created. */
    public const string KEY_POLICY = 'https://console.cloud.google.com/iam-admin/orgpolicies/iam-disableServiceAccountKeyCreation';

    /**
     * Fills the key `.env` left empty from what the control panel saved.
     */
    public static function apply(): void
    {
        $connection = new self;

        if (blank(config('marketing-toolkit.search_console.credentials')) && File::exists($connection->keyPath())) {
            config(['marketing-toolkit.search_console.credentials' => $connection->keyPath()]);
        }
    }

    /**
     * The site's property: from the config (`.env`), else as saved in the
     * control panel. Null: the current site.
     */
    public function property(?string $site = null): ?string
    {
        return $this->configuredProperty($site) ?? $this->savedProperty($site);
    }

    /**
     * The property the config gives the site: a string for every site, or the
     * site's entry in a map.
     */
    public function configuredProperty(?string $site = null): ?string
    {
        $value = config('marketing-toolkit.search_console.property');
        $value = is_array($value) ? ($value[$site ?? Site::current()->handle()] ?? null) : $value;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * The handles of the sites that have a property.
     *
     * @return list<string>
     */
    public function sitesWithProperty(): array
    {
        return array_values(array_filter(Site::all()->map->handle()->all(), fn (string $site) => $this->property($site) !== null));
    }

    public function keyPath(): string
    {
        return storage_path('app/private/marketing-toolkit/search-console-key.json');
    }

    /**
     * Where the key comes from: `env` (the config, typically `.env`), `cp` (uploaded) or null.
     */
    public function keySource(): ?string
    {
        $value = (string) config('marketing-toolkit.search_console.credentials');

        return match (true) {
            $value === '' => null,
            $value === $this->keyPath() => 'cp',
            default => 'env',
        };
    }

    public function propertySource(?string $site = null): ?string
    {
        return match (true) {
            $this->configuredProperty($site) !== null => 'env',
            $this->savedProperty($site) !== null => 'cp',
            default => null,
        };
    }

    /**
     * The service account's email, which has to be a user of the property.
     */
    public function email(): ?string
    {
        return self::parseKey($this->readKey((string) config('marketing-toolkit.search_console.credentials')))['client_email'] ?? null;
    }

    /**
     * The key's JSON from the `marketing-toolkit.search_console.credentials` value: the
     * JSON itself, or a path to it. The file the control panel saved is
     * encrypted; one saved before it was is read as it is.
     */
    public function readKey(string $value): string
    {
        if ($value === '' || ! is_file($value)) {
            return $value;
        }

        $contents = (string) file_get_contents($value);

        if ($value !== $this->keyPath()) {
            return $contents;
        }

        try {
            return Crypt::decryptString($contents);
        } catch (DecryptException) {
            return $contents;
        }
    }

    /**
     * The property to suggest: the site's domain, which covers http, https and
     * every subdomain; an address prefix where there is no domain (an IP, localhost).
     */
    public function suggestedProperty(?string $site = null): string
    {
        $url = (Site::get($site ?? Site::current()->handle()) ?? Site::default())->absoluteUrl();
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
            && filter_var($key['client_email'], FILTER_VALIDATE_EMAIL) !== false
            && is_string($key['private_key'] ?? null)
            && openssl_pkey_get_private($key['private_key']) !== false
            ? $key
            : null;
    }

    public function saveKey(string $json): void
    {
        File::ensureDirectoryExists(dirname($this->keyPath()), 0700);
        File::put($this->keyPath(), Crypt::encryptString($json));
        chmod($this->keyPath(), 0600);
    }

    public function forgetKey(): void
    {
        File::delete($this->keyPath());
    }

    public function savedProperty(?string $site = null): ?string
    {
        $site ??= Site::current()->handle();

        try {
            $addon = Addon::get(Package::NAME);
            $key = $site === Site::default()->handle() ? self::SETTING : self::SITES_SETTING;
            $value = $addon?->settings()->get($key);
            $value = $key === self::SITES_SETTING ? ((array) $value)[$site] ?? null : $value;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return filled($value) && is_scalar($value) ? trim((string) $value) : null;
    }

    public function saveProperty(?string $property, ?string $site = null): void
    {
        $site ??= Site::current()->handle();
        $property = filled($property) ? trim((string) $property) : null;
        $settings = Addon::get(Package::NAME)->settings();

        if ($site === Site::default()->handle()) {
            $settings->set(self::SETTING, $property);
        } else {
            $others = (array) $settings->get(self::SITES_SETTING);
            $others[$site] = $property;
            $settings->set(self::SITES_SETTING, array_filter($others) ?: null);
        }

        $settings->save();
    }

    /**
     * Asks Google whether the key may read the property, and says what to
     * fix in words a person setting it up can act on.
     *
     * @return array{ok: bool, message: string}
     */
    public function check(Client $client, ?string $site = null): array
    {
        if (! $client->configured($site)) {
            return ['ok' => false, 'message' => __('marketing-toolkit::cp.search_console.messages.add_first')];
        }

        $property = (string) $this->property($site);

        try {
            $client->site($site);
        } catch (RequestException $exception) {
            return ['ok' => false, 'message' => $this->explain($exception, $property)];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'message' => __('marketing-toolkit::cp.search_console.messages.unexpected')];
        }

        return ['ok' => true, 'message' => __('marketing-toolkit::cp.search_console.messages.connected', ['property' => $property])];
    }

    private function explain(RequestException $exception, string $property): string
    {
        $body = $exception->response->json() ?? [];
        $reason = (string) data_get($body, 'error.details.0.reason', data_get($body, 'error.status', ''));
        $message = (string) data_get($body, 'error.message', data_get($body, 'error_description', ''));
        $email = $this->email() ?? __('marketing-toolkit::cp.search_console.messages.the_service_account');

        $tokenError = in_array(data_get($body, 'error'), ['invalid_grant', 'unauthorized_client'], true);

        return match (true) {
            $tokenError && str_contains(strtolower((string) data_get($body, 'error_description')), 'disabled') => __('marketing-toolkit::cp.search_console.messages.key_disabled', ['url' => self::KEYS_GUIDE]),
            $reason === 'SERVICE_DISABLED' || str_contains($message, 'has not been used') => __('marketing-toolkit::cp.search_console.messages.api_disabled'),
            $tokenError => __('marketing-toolkit::cp.search_console.messages.key_refused'),
            $exception->response->status() === 403 => __('marketing-toolkit::cp.search_console.messages.not_a_user', ['email' => $email, 'property' => $property]),
            $exception->response->status() === 404 => __('marketing-toolkit::cp.search_console.messages.no_property', ['property' => $property]),
            default => __('marketing-toolkit::cp.search_console.messages.google_said', [
                'message' => $message ?: __('marketing-toolkit::cp.search_console.messages.error', ['status' => $exception->response->status()]),
            ]),
        };
    }
}
