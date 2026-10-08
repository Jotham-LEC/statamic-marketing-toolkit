<?php

namespace JothamLec\MarketingToolkit;

use JothamLec\MarketingToolkit\Support\Assets;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Globals\Variables;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

/**
 * The Brand and Marketing settings global sets (config
 * `marketing-toolkit.global` and `settings_global`), read for the current
 * site: a field is looked for in Marketing settings, then in Brand, so a site
 * whose values haven't moved to Marketing settings yet reads them as before,
 * and a value saved in Marketing settings wins over one left behind.
 * A site whose localization leaves a field empty takes its origin's value.
 * Every getter tolerates a set or a field being missing, so a site works
 * before `php please mt:install` has run.
 */
class Settings
{
    /** @var array<string, list<Variables>> site handle => its localizations of both sets */
    private array $variables = [];

    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->value($key);

        return filled($value) && is_scalar($value) ? (string) $value : $default;
    }

    /**
     * A toggle, or $default while it was never saved.
     */
    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->value($key);

        return $value === null ? $default : (bool) $value;
    }

    /**
     * @return list<string>
     */
    public function list(string $key): array
    {
        $value = $this->value($key);

        return array_values(array_filter(is_array($value) ? $value : [], fn ($item) => filled($item) && is_string($item)));
    }

    /**
     * A grid's rows, without empty values: e.g. opening hours or contact points.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $key): array
    {
        $value = $this->value($key);

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
        $variables = collect($this->variables())->first(fn (Variables $variables) => $variables->value($key) !== null);
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
        return Assets::from($container === null ? $variables?->augmentedValue($key) : $container.'::'.$path);
    }

    /**
     * The asset container an assets field of either set's blueprint names,
     * read from the blueprint as saved (an imported field isn't looked into).
     */
    private function container(string $key): ?string
    {
        $tabs = collect(self::handles())->flatMap(fn (string $handle) => Blueprint::find('globals.'.$handle)?->contents()['tabs'] ?? [])->all();

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
     * The name page titles end with: Brand's "Name in page titles", else the site's name.
     */
    public function titleName(): string
    {
        return $this->string('title_brand') ?? $this->siteName();
    }

    /**
     * A field that is a list now and was one text before (other names, areas
     * served): its items, or the text as the only one.
     *
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $value = $this->value($key);

        if (is_array($value)) {
            return $this->list($key);
        }

        $text = $this->string($key);

        return $text === null ? [] : [$text];
    }

    /**
     * The handles of the two sets, in the order a field is looked for:
     * Marketing settings, then Brand.
     *
     * @return list<string>
     */
    public static function handles(): array
    {
        return array_values(array_unique(array_filter([
            (string) config('marketing-toolkit.settings_global'),
            (string) config('marketing-toolkit.global'),
        ])));
    }

    /**
     * A field's value, from the first set that has one.
     */
    private function value(string $key): mixed
    {
        foreach ($this->variables() as $variables) {
            $value = $variables->value($key);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The current site's localizations of the sets enabled there. Kept per
     * site: the current site can change while one instance lives.
     *
     * @return list<Variables>
     */
    private function variables(): array
    {
        $site = Site::current()->handle();

        return $this->variables[$site] ??= array_values(array_filter(array_map(
            fn (string $handle) => GlobalSet::findByHandle($handle)?->in($site),
            self::handles(),
        )));
    }
}
