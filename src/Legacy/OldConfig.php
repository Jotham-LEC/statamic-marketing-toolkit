<?php

namespace JothamLec\MarketingToolkit\Legacy;

/**
 * Reads a config file published up to 0.19 (when it was config/seo.php) in
 * today's keys, so it keeps working as it did. Today's key wins where a site
 * has both.
 *
 * This was added in 0.20.0 and will be removed in 1.0; see "Removed in 1.0" in docs/upgrading.md.
 */
final class OldConfig
{
    /** Maps the tracking keys renamed after 0.19 from old to new, and each is now its field's handle. */
    public const array RENAMED_TRACKING = ['gtm' => 'gtm_id', 'ga4' => 'ga4_id', 'meta_pixel' => 'meta_pixel_id', 'linkedin' => 'linkedin_partner_id'];

    /** These switches were a plain true or false up to 0.19, and are now `{key}.enabled` like the others. */
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
