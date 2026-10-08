<?php

namespace JothamLec\MarketingToolkit\UpdateScripts;

use Statamic\Facades\GlobalSet;

/**
 * 0.18.2 stopped adding the site name to page titles unless the new
 * "Add the site name to page titles" toggle is on, and kept it for a site
 * with a separator saved. In the control panel the toggle still showed off
 * there, so the next save of SEO & brand dropped the site name. Each
 * localization with a separator saved and no toggle gets the toggle on,
 * which is what its titles already do.
 *
 * Added in 0.18.3. Removed in 1.0: see docs/upgrading.md, "Removed in 1.0".
 */
final class KeepSiteNameInTitles extends UpdateScript
{
    public function shouldUpdate($newVersion, $oldVersion)
    {
        // Also from Co-SEO, whose docs have its sites run `updates:run 0.17.0`.
        return self::before((string) $oldVersion, '0.18.3');
    }

    public function update(): void
    {
        foreach (GlobalSet::findByHandle((string) config('marketing-toolkit.global'))?->localizations() ?? [] as $variables) {
            $data = $variables->data();

            if (filled($data->get('title_separator')) && ! $data->has('title_site_name')) {
                $variables->set('title_site_name', true)->save();
            }
        }
    }
}
