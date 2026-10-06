<?php

namespace JothamLec\MarketingToolkit\Support;

use Illuminate\Support\Facades\File;

/**
 * Co-SEO saved its addon settings (the Search Console property) in
 * `resources/addons/seo.yaml`, after its slug. Marketing Toolkit's slug is
 * `marketing-toolkit`: copy the file across once, the first time the renamed
 * addon boots, and leave the old one where it is.
 */
final class LegacySettings
{
    public static function carryOver(): void
    {
        $old = resource_path('addons/seo.yaml');
        $new = resource_path('addons/marketing-toolkit.yaml');

        if (File::exists($old) && ! File::exists($new)) {
            File::copy($old, $new);
        }
    }
}
