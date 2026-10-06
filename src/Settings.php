<?php

namespace JothamLec\Seo;

use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Globals\Variables;
use Statamic\Contracts\Query\Builder;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

/**
 * The brand-and-defaults global set (config `seo.global`), read for the
 * current site. A site whose localization leaves a field empty takes its
 * origin's value. Every getter tolerates the set or the field being missing,
 * so a site works before `php please seo:install` has run.
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

    public function asset(string $key): ?Asset
    {
        $value = $this->variables()?->augmentedValue($key)?->value();
        // A field that takes more than one file augments to a query, not a list.
        $value = $value instanceof Builder ? $value->get()->first() : $value;
        $value = is_iterable($value) ? collect($value)->first() : $value;

        return $value instanceof Asset ? $value : null;
    }

    /**
     * The site's name as Statamic knows it (Settings → Sites, else APP_NAME).
     */
    public function siteName(): string
    {
        return (string) Site::current()->name();
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
            $this->variables[$site] = GlobalSet::findByHandle((string) config('seo.global'))?->in($site);
        }

        return $this->variables[$site];
    }
}
