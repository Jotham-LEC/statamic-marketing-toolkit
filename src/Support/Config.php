<?php

namespace JothamLec\MarketingToolkit\Support;

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

    /** Tracking keys renamed after 0.19, old => new: each is now its field's handle. */
    public const array RENAMED_TRACKING = ['gtm' => 'gtm_id', 'ga4' => 'ga4_id', 'meta_pixel' => 'meta_pixel_id', 'linkedin' => 'linkedin_partner_id'];

    /** Switches that were a plain true or false up to 0.19, now `{key}.enabled` like the others. */
    public const array SWITCHES = ['robots_txt', 'llms_txt', 'ads_txt'];

    /**
     * A config/seo.php published up to 0.19, in today's keys, so it keeps
     * working as it was. Today's key wins where a site has both.
     *
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    public static function upgrade(array $site): array
    {
        foreach (self::SWITCHES as $key) {
            if (is_bool($site[$key] ?? null)) {
                $site[$key] = ['enabled' => $site[$key]];
            }
        }

        foreach (self::RENAMED_TRACKING as $old => $new) {
            if (is_array($site['tracking'] ?? null) && array_key_exists($old, $site['tracking'])) {
                $site['tracking'][$new] ??= $site['tracking'][$old];
                unset($site['tracking'][$old]);
            }
        }

        return $site;
    }

    private static function isMap(mixed $value): bool
    {
        return is_array($value) && $value !== [] && ! array_is_list($value);
    }
}
