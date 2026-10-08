<?php

namespace JothamLec\MarketingToolkit;

use JothamLec\MarketingToolkit\Support\Assets;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Globals\Variables;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

/**
 * This class reads the Brand and Marketing settings global sets (config
 * `marketing-toolkit.global` and `settings_global`) for the current site.
 * A field is looked for in Marketing settings first and then in Brand, so a
 * site whose values haven't moved to Marketing settings yet reads them as
 * before, and a value saved in Marketing settings wins over one left behind.
 * A site whose localization leaves a field empty takes its origin's value.
 * Every getter tolerates a missing set or field, so a site works before
 * `php please mt:install` has run.
 */
class Settings
{
    /** @var array<string, list<Variables>> site handle => the site's localizations of both sets */
    private array $variables = [];

    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->value($key);

        return filled($value) && is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Returns a toggle's value, or $default if the toggle has never been saved.
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
     * Returns a grid's rows without their empty values, for example opening hours or contact points.
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
     * Returns the asset a field holds, found from the stored path and the
     * field's container. Augmenting the value instead would build every field
     * of the set first, which costs tens of milliseconds on each page.
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

        return $container === null ? null : Assets::from($container.'::'.$path);
    }

    /**
     * Returns the asset container that an assets field in either set's
     * blueprint names, including a field imported from a fieldset. A field
     * that names none uses the site's only container, as Statamic's assets
     * field does.
     */
    private function container(string $key): ?string
    {
        foreach (self::handles() as $handle) {
            $field = Blueprint::find('globals.'.$handle)?->field($key);

            if ($field !== null) {
                $containers = AssetContainer::all();

                return $field->get('container') ?? ($containers->count() === 1 ? $containers->first()->handle() : null);
            }
        }

        return null;
    }

    /**
     * Returns the site's name as Statamic knows it, from Settings → Sites or else APP_NAME.
     */
    public function siteName(): string
    {
        return (string) Site::current()->name();
    }

    /**
     * Determines whether page titles end with the site name. It is off by
     * default, but a site set up before the toggle existed, with a separator
     * saved, keeps its titles.
     */
    public function titleSiteName(): bool
    {
        return $this->bool('title_site_name', $this->string('title_separator') !== null);
    }

    /**
     * Returns the separator with a space on each side, however it was typed.
     * The control panel may trim a value, so `·` and ` · ` read the same.
     */
    public function separator(): string
    {
        $separator = trim((string) $this->string('title_separator'));

        return ' '.($separator === '' ? '·' : $separator).' ';
    }

    /**
     * Returns the name page titles end with, which is Brand's "Name in page titles" or else the site's name.
     */
    public function titleName(): string
    {
        return $this->string('title_brand') ?? $this->siteName();
    }

    /**
     * Reads a field that is a list now but was a single text before, such as
     * other names or areas served. It returns the list's items, or the text
     * as the only item.
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
     * Returns the handles of the two sets in the order a field is looked for,
     * which is Marketing settings first and then Brand.
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
     * Returns a field's value from the first set that has one.
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
     * Returns the current site's localizations of the sets enabled there. They
     * are kept per site because the current site can change while one
     * instance lives.
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
