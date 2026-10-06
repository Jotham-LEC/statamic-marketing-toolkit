<?php

namespace JothamLec\MarketingToolkit\Fieldtypes;

use JothamLec\MarketingToolkit\Reports\ReportSettings;
use Statamic\Fields\Fieldtype;

/**
 * The search result and the share cards this entry will produce, drawn live
 * from the publish form. It shows; it stores nothing.
 */
class SeoPreview extends Fieldtype
{
    protected static $handle = 'seo_preview';

    protected static $title = 'seo::fields.seo.seo_preview.title';

    protected $categories = ['special'];

    public function preload(): array
    {
        $settings = app(ReportSettings::class);

        return [
            'urls' => [
                'meta' => cp_route('seo.preview.meta'),
                'card' => cp_route('seo.preview.card'),
            ],
            // The report's thresholds (Tools → Addons → SEO), so the counters and the reports agree.
            'limits' => [
                'title' => [$settings->int('title_min'), $settings->int('title_max')],
                'description' => [$settings->int('description_min'), $settings->int('description_max')],
            ],
            'og' => (bool) config('seo.og.enabled'),
        ];
    }

    public function process($data)
    {
        return null;
    }

    public function preProcess($data)
    {
        return null;
    }
}
