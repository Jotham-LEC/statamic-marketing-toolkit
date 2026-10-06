<?php

namespace JothamLec\Seo;

use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Globals\Variables;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

/**
 * The brand-and-defaults global set (config `seo.global`), read for the
 * current site. Every getter tolerates the set or the field being missing,
 * so a site works before `php please seo:install` has run.
 */
class Settings
{
    private ?Variables $variables = null;

    private bool $loaded = false;

    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->variables()?->get($key);

        return filled($value) && is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @return list<string>
     */
    public function list(string $key): array
    {
        $value = $this->variables()?->get($key);

        return array_values(array_filter(is_array($value) ? $value : [], fn ($item) => filled($item) && is_string($item)));
    }

    public function asset(string $key): ?Asset
    {
        $value = $this->variables()?->augmentedValue($key)?->value();
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

    private function variables(): ?Variables
    {
        if (! $this->loaded) {
            $this->loaded = true;
            $this->variables = GlobalSet::findByHandle((string) config('seo.global'))?->inCurrentSite();
        }

        return $this->variables;
    }
}
