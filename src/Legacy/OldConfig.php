<?php

namespace JothamLec\MarketingToolkit\Legacy;

/**
 * Reads a config file published up to 0.19 (then config/seo.php) in
 * today's keys, so it keeps working as it was. Today's key wins where a site
 * has both.
 *
 * Added in 0.20.0. Removed in 1.0: see docs/upgrading.md, "Removed in 1.0".
 */
final class OldConfig
{
    /** Tracking keys renamed after 0.19, old => new: each is now its field's handle. */
    public const array RENAMED_TRACKING = ['gtm' => 'gtm_id', 'ga4' => 'ga4_id', 'meta_pixel' => 'meta_pixel_id', 'linkedin' => 'linkedin_partner_id'];

    /** Switches that were a plain true or false up to 0.19, now `{key}.enabled` like the others. */
    public const array SWITCHES = ['robots_txt', 'llms_txt', 'ads_txt'];

    /**
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
}
