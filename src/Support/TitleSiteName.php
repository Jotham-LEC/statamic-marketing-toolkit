<?php

namespace JothamLec\MarketingToolkit\Support;

use Statamic\Facades\GlobalSet;
use Throwable;

/**
 * 0.18.2 stopped adding the site name to page titles unless the new
 * "Add the site name to page titles" toggle is on, and kept it for a site
 * with a separator saved. In the control panel the toggle still showed off
 * there, so the next save of SEO & brand dropped the site name. Run once by
 * a migration: each localization with a separator saved and no toggle gets
 * the toggle on, which is what its titles already do.
 */
final class TitleSiteName
{
    public static function keep(): void
    {
        try {
            $set = GlobalSet::findByHandle((string) config('seo.global', 'seo'));
        } catch (Throwable) {
            // Statamic's storage isn't ready (a fresh install): nothing to keep.
            return;
        }

        foreach ($set?->localizations() ?? [] as $variables) {
            $data = $variables->data();

            if (filled($data->get('title_separator')) && ! $data->has('title_site_name')) {
                $variables->set('title_site_name', true)->save();
            }
        }
    }
}
