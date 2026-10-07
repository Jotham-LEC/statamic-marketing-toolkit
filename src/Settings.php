<?php

namespace JothamLec\MarketingToolkit;

use JothamLec\MarketingToolkit\Support\Assets;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Globals\Variables;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

/**
 * The brand-and-defaults global set (config `marketing-toolkit.global`), read for the
 * current site. A site whose localization leaves a field empty takes its
 * origin's value. Every getter tolerates the set or the field being missing,
 * so a site works before `php please mt:install` has run.
 */
class Settings
{
    /** @var array<string, ?Variables> site handle => its localization */
    private array $variables = [];

    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->variables()?->value($key);

        return filled($value) && is_scalar($value) ? (string) $value : $default;
    }

    /**
     * A toggle, or $default while it was never saved.
     */
    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->variables()?->value($key);

        return $value === null ? $default : (bool) $value;
    }

    /**
     * @return list<string>
     */
    public function list(string $key): array
    {
        $value = $this->variables()?->value($key);

        return array_values(array_filter(is_array($value) ? $value : [], fn ($item) => filled($item) && is_string($item)));
    }

    /**
     * A grid's rows, without empty values: e.g. opening hours or contact points.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $key): array
    {
        $value = $this->variables()?->value($key);

        return collect(is_array($value) ? $value : [])
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => array_filter($row, fn ($cell) => filled($cell)))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The asset a field holds, found from the stored path and the field's
     * container. Augmenting the value instead would build every field of the
     * set first, tens of milliseconds on each page.
     */
    public function asset(string $key): ?Asset
    {
        $variables = $this->variables();
        $value = $variables?->value($key);
        $path = is_array($value) ? collect($value)->first() : $value;

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_contains($path, '::')) {
            return Assets::from($path);
        }

        $container = $this->container($key);

        // A field the blueprint doesn't name a container for (imported): augmented, which finds it.
        return Assets::from($container === null ? $variables->augmentedValue($key) : $container.'::'.$path);
    }

    /**
     * The asset container an assets field of the set's blueprint names, read
     * from the blueprint as saved (an imported field isn't looked into).
     */
    private function container(string $key): ?string
    {
        $tabs = Blueprint::find('globals.'.config('marketing-toolkit.global'))?->contents()['tabs'] ?? [];

        foreach ($tabs as $tab) {
            foreach ($tab['sections'] ?? [] as $section) {
                foreach ($section['fields'] ?? [] as $field) {
                    if (($field['handle'] ?? null) === $key && is_string($field['field']['container'] ?? null)) {
                        return $field['field']['container'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * The site's name as Statamic knows it (Settings → Sites, else APP_NAME).
     */
    public function siteName(): string
    {
        return (string) Site::current()->name();
    }

    /**
     * Whether page titles end with the site name. Off by default; a site set
     * up before the toggle existed, with a separator saved, keeps its titles.
     */
    public function titleSiteName(): bool
    {
        return $this->bool('title_site_name', $this->string('title_separator') !== null);
    }

    /**
     * The separator with a space on each side, however it was typed: the
     * control panel may trim a value, so `·` and ` · ` read the same.
     */
    public function separator(): string
    {
        $separator = trim((string) $this->string('title_separator'));

        return ' '.($separator === '' ? '·' : $separator).' ';
    }

    /**
     * The current site's localization, or null where the set isn't enabled.
     * Kept per site: the current site can change while one instance lives.
     */
    private function variables(): ?Variables
    {
        $site = Site::current()->handle();

        if (! array_key_exists($site, $this->variables)) {
            $this->variables[$site] = GlobalSet::findByHandle((string) config('marketing-toolkit.global'))?->in($site);
        }

        return $this->variables[$site];
    }
}
