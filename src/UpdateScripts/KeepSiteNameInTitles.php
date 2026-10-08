<?php

namespace JothamLec\MarketingToolkit\UpdateScripts;

use Statamic\Facades\GlobalSet;

/**
 * Version 0.18.2 stopped adding the site name to page titles unless the new
 * "Add the site name to page titles" toggle is on, and kept it for a site
 * with a separator saved. In the control panel, the toggle still showed as off
 * there, so the next save of SEO & brand dropped the site name. This turns the
 * toggle on for each localization with a separator saved and no toggle,
 * which matches what its titles already do.
 *
 * This was added in 0.18.3 and will be removed in 1.0; see "Removed in 1.0" in docs/upgrading.md.
 */
final class KeepSiteNameInTitles extends UpdateScript
{
    public function shouldUpdate($newVersion, $oldVersion)
    {
        // This also covers Co-SEO, whose docs have its sites run `updates:run 0.17.0`.
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
