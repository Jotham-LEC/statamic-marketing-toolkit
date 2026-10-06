<?php

namespace JothamLec\Seo\Support;

/**
 * Merges a site's config/seo.php into the addon's defaults. Laravel's own
 * merge is one level deep, so a site that set `og.templates` alone lost
 * `og.enabled` and the cards with it. Here keyed arrays merge at every depth,
 * while a list (ignore_paths, sitemap collections) or a value replaces the
 * default whole: a site's list is the list it wants.
 */
final class Config
{
    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    public static function merge(array $defaults, array $site): array
    {
        foreach ($site as $key => $value) {
            $default = $defaults[$key] ?? null;

            $defaults[$key] = self::isMap($default) && self::isMap($value)
                ? self::merge($default, $value)
                : $value;
        }

        return $defaults;
    }

    private static function isMap(mixed $value): bool
    {
        return is_array($value) && $value !== [] && ! array_is_list($value);
    }
}
